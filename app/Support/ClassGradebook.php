<?php

namespace App\Support;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Comment\Comment;
use OpenSpout\Common\Entity\Comment\TextRun;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Every enrolled student's grade on every lab of a class, for the instructor's gradebook page
 * and its Excel export. The grades follow the same rules as the rest of the app:
 *
 * - A lab's grade is the student's best completed attempt, by effective score (the
 *   instructor's regrade if there is one, else the AI score), as on the results page.
 * - A grade still waiting for the instructor's review shows, but is marked provisional and
 *   doesn't count as passed, as in the certificate check.
 * - Without a completed attempt the cell says whether the student is working on the lab or
 *   their attempt ended unsubmitted; a lab never opened is blank.
 * - On a team lab each student has their own attempt, which the team's submit grades.
 *
 * Loads in a fixed number of queries whatever the class size: the module tree, the students,
 * and their attempts (with teams and regraders), never one per student or lab.
 */
class ClassGradebook
{
    public const GRADED = 'graded';

    public const REVIEW = 'review';

    public const IN_PROGRESS = 'in_progress';

    public const TIMED_OUT = 'timed_out';

    public const NOT_STARTED = 'not_started';

    /** What a cell without a grade says, on the page and in the sheet */
    public const STATE_LABELS = [
        self::IN_PROGRESS => 'In progress',
        self::TIMED_OUT => 'Timed out',
        self::NOT_STARTED => '',
    ];

    /**
     * @param  Collection<int, Laboratory>  $labs  in syllabus order, each with its module
     * @param  list<array{student: User, cells: array<int, array<string, mixed>>, average: ?float, passed: int, percent: int|float, meets: bool}>  $rows
     */
    private function __construct(
        public readonly SchoolClass $class,
        public readonly Collection $labs,
        public readonly array $rows,
        public readonly int $labPassScore,
        public readonly int $threshold,
    ) {}

    public static function for(SchoolClass $class): self
    {
        $class->loadModuleTree();
        $labs = self::labsInSyllabusOrder($class);
        $labIds = $labs->pluck('id')->all();

        $students = $class->students()
            ->wherePivot('status', 'enrolled')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.email']);

        $attempts = $labIds && $students->isNotEmpty()
            ? LabSession::query()
                ->whereIn('lab_id', $labIds)
                ->whereIn('user_id', DB::table('class_student')
                    ->where('class_id', $class->id)
                    ->where('status', 'enrolled')
                    ->select('student_id'))
                // Only what the grade needs: never the submitted code or telemetry figures
                ->select(['id', 'lab_id', 'user_id', 'group_id', 'status', 'performance_score', 'ai_grade_summary',
                    'instructor_grade_override', 'instructor_override_reason', 'instructor_overridden_at', 'overridden_by'])
                ->with(['group:id,name', 'overriddenByUser:id,name'])
                ->get()
                ->groupBy(['user_id', 'lab_id'])
            : collect();

        $labPassScore = (int) ($class->lab_pass_score ?? SchoolClass::DEFAULT_LAB_PASS_SCORE);
        $threshold = (int) ($class->passing_threshold ?? 75);

        $rows = [];
        foreach ($students as $student) {
            $cells = [];
            $passedLabIds = [];
            $total = 0.0;
            foreach ($labs as $lab) {
                $theirs = $attempts->get($student->id)?->get($lab->id) ?? collect();
                $cells[$lab->id] = self::cell($theirs);
                $total += $cells[$lab->id]['score'] ?? 0;

                // Passed as the certificate counts it: a final grade at the lab pass score
                if ($theirs->contains(fn (LabSession $attempt) => $attempt->status === 'completed'
                    && ! $attempt->needsGradeReview()
                    && $attempt->effective_score >= $labPassScore)) {
                    $passedLabIds[$lab->id] = $lab->id;
                }
            }

            $progress = LabSession::progressOver($labIds, $passedLabIds);
            $rows[] = [
                'student' => $student,
                'cells' => $cells,
                // Over every lab in the class, a lab without a grade counting as 0
                'average' => $labs->isEmpty() ? null : round($total / $labs->count(), 2),
                'passed' => $progress['completed'],
                'percent' => $progress['percent'],
                'meets' => $progress['total'] > 0 && $progress['percent'] >= $threshold,
            ];
        }

        return new self($class, $labs, $rows, $labPassScore, $threshold);
    }

    /**
     * The labs in the order the syllabus lists them: each module's own labs, then its
     * sections', depth first. Reads the tree loadModuleTree() already loaded.
     *
     * @return Collection<int, Laboratory>
     */
    private static function labsInSyllabusOrder(SchoolClass $class): Collection
    {
        $walk = function (Module $module) use (&$walk): array {
            $labs = $module->laboratories->sortBy('id')->each(fn (Laboratory $lab) => $lab->setRelation('module', $module))->all();
            foreach ($module->children as $child) {
                $labs = array_merge($labs, $walk($child));
            }

            return $labs;
        };

        $labs = [];
        foreach ($class->modules->whereNull('parent_id')->sortBy('order_index') as $module) {
            $labs = array_merge($labs, $walk($module));
        }

        return collect($labs)->unique('id')->values();
    }

    /**
     * One student's cell for one lab, from their attempts at it.
     *
     * @param  Collection<int, LabSession>  $attempts
     * @return array{state: string, score: ?float, session_id: ?int, overridden: bool, ai_score: ?float, team: ?string, regrade: ?array{by: string, at: mixed, reason: ?string}}
     */
    private static function cell(Collection $attempts): array
    {
        $empty = ['score' => null, 'session_id' => null, 'overridden' => false, 'ai_score' => null, 'team' => null, 'regrade' => null];

        // Best grade first; between equal grades a final one, then the newest
        $best = $attempts->where('status', 'completed')
            ->sortBy(fn (LabSession $a) => [-$a->effective_score, $a->needsGradeReview() ? 1 : 0, -$a->id])
            ->first();

        if (! $best) {
            $open = $attempts->firstWhere('status', 'in_progress');
            $ended = $attempts->where('status', 'abandoned')->sortByDesc('id')->first();
            $shown = $open ?? $ended;

            return array_merge($empty, [
                'state' => $open ? self::IN_PROGRESS : ($ended ? self::TIMED_OUT : self::NOT_STARTED),
                'session_id' => $shown?->id,
                'team' => $shown?->group?->name,
            ]);
        }

        return [
            'state' => $best->needsGradeReview() ? self::REVIEW : self::GRADED,
            'score' => round($best->effective_score, 2),
            'session_id' => $best->id,
            'overridden' => $best->isGradeOverridden(),
            'ai_score' => $best->performance_score !== null ? round($best->performance_score, 2) : null,
            'team' => $best->group?->name,
            'regrade' => $best->isGradeOverridden() ? [
                'by' => $best->overriddenByUser?->name ?? 'Instructor',
                'at' => $best->instructor_overridden_at,
                'reason' => $best->instructor_override_reason,
            ] : null,
        ];
    }

    /** Whether any shown grade is an instructor's regrade */
    public function hasRegrades(): bool
    {
        foreach ($this->rows as $row) {
            foreach ($row['cells'] as $cell) {
                if ($cell['overridden']) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The export's file name: the class name in letters, digits and dashes, and the date */
    public function fileName(): string
    {
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $this->class->name), '-') ?: 'class';

        return "{$name}-gradebook-".now()->format('Y-m-d').'.xlsx';
    }

    /**
     * Write the gradebook as an .xlsx workbook to a path or stream (php://output to stream it).
     * Sheet "Gradebook" holds one row per student; grades are numbers, other cells are text.
     * A regraded grade is shaded blue and a provisional one yellow, each with a note saying
     * why; sheet "Regrades" lists every regrade shown, with the AI score and the reason.
     */
    public function writeXlsx(string $target): void
    {
        $writer = new Writer;
        $writer->openToFile($target);

        $bold = (new Style)->setFontBold();
        $regraded = (new Style)->setBackgroundColor('DDEBF7');
        $provisional = (new Style)->setBackgroundColor('FFF2CC');

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Gradebook');
        $sheet->setSheetView((new SheetView)->setFreezeRow(2)->setFreezeColumn('C'));
        $sheet->setColumnWidth(28, 1);
        $sheet->setColumnWidth(32, 2);
        $lastColumn = 2 + $this->labs->count() + 2;
        $sheet->setColumnWidthForRange(14, 3, $lastColumn + 1);

        $header = ['Student', 'Email', ...$this->labs->pluck('title')->all(), 'Average', "Labs passed (at {$this->labPassScore}%)", "Meets {$this->threshold}% threshold"];
        $writer->addRow($this->textRow($header, $bold));

        foreach ($this->rows as $row) {
            $cells = [self::text($row['student']->name), self::text($row['student']->email)];
            foreach ($this->labs as $lab) {
                $cells[] = $this->sheetCell($row['cells'][$lab->id], $regraded, $provisional);
            }
            $cells[] = Cell::fromValue($row['average']);
            $cells[] = self::text("{$row['passed']}/{$this->labs->count()}");
            $cells[] = self::text($row['meets'] ? 'Yes' : 'No');
            $writer->addRow(new Row($cells));
        }

        if ($this->rows) {
            $sheet->setAutoFilter(new AutoFilter(0, 1, $lastColumn, count($this->rows) + 1));
        }

        $writer->addNewSheetAndMakeItCurrent()->setName('Regrades');
        $writer->addRow($this->textRow(['Student', 'Email', 'Lab', 'Grade', 'AI score', 'Regraded by', 'Regraded at', 'Reason'], $bold));
        foreach ($this->rows as $row) {
            foreach ($this->labs as $lab) {
                $cell = $row['cells'][$lab->id];
                if ($cell['regrade']) {
                    $writer->addRow(new Row([
                        self::text($row['student']->name),
                        self::text($row['student']->email),
                        self::text($lab->title),
                        Cell::fromValue($cell['score']),
                        Cell::fromValue($cell['ai_score']),
                        self::text($cell['regrade']['by']),
                        self::text($cell['regrade']['at'] ? DisplayTime::format($cell['regrade']['at'], true) : ''),
                        self::text($cell['regrade']['reason'] ?? ''),
                    ]));
                }
            }
        }

        $writer->setCurrentSheet($sheet);
        $writer->close();
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function sheetCell(array $cell, Style $regraded, Style $provisional): Cell
    {
        if ($cell['score'] === null) {
            return self::text(self::STATE_LABELS[$cell['state']]);
        }

        if ($cell['overridden']) {
            $sheetCell = Cell::fromValue($cell['score'], $regraded);
            $ai = $cell['ai_score'] !== null ? "{$cell['ai_score']}%" : 'none';
            $sheetCell->comment = $this->note("Regraded by {$cell['regrade']['by']} (AI score {$ai}).".($cell['regrade']['reason'] ? " {$cell['regrade']['reason']}" : ''));

            return $sheetCell;
        }

        if ($cell['state'] === self::REVIEW) {
            $sheetCell = Cell::fromValue($cell['score'], $provisional);
            $sheetCell->comment = $this->note('Provisional grade from automatic checks, waiting for your review.');

            return $sheetCell;
        }

        return Cell::fromValue($cell['score']);
    }

    /**
     * A text cell. Cell::fromValue() makes any text starting with "=" a formula, so a student
     * named "=HYPERLINK(...)" would put a live formula in the instructor's sheet; names,
     * titles and reasons always go in as plain text.
     */
    private static function text(string $value, ?Style $style = null): Cell
    {
        return $value === '' ? Cell::fromValue(null, $style) : new StringCell($value, $style);
    }

    /**
     * @param  list<string>  $values
     */
    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (string $value) => self::text($value, $style), $values));
    }

    private function note(string $text): Comment
    {
        $comment = new Comment;
        $comment->addTextRun(new TextRun($text));

        return $comment;
    }
}
