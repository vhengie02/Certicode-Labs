<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'class_id',
        'verification_code',
        'qr_code_path',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    /**
     * Get the user who owns this certificate.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the class associated with this certificate.
     */
    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Issue a student's certificate for a class if their progress meets the pass mark. The one
     * place certificates are created: ending a class, the scheduled auto-conclude and the
     * student's claim button all come through here. An existing certificate is returned, not
     * duplicated. 'created' says whether this call made it, so callers notify only once.
     *
     * @return array{certificate: ?Certificate, created: bool, progress: array, threshold: int}
     */
    public static function issueFor(SchoolClass $class, User $student): array
    {
        $threshold = $class->passing_threshold ?? 75;
        $progress = $class->getStudentProgress($student);
        $result = ['certificate' => null, 'created' => false, 'progress' => $progress, 'threshold' => $threshold];

        if ($progress['total'] === 0 || $progress['percent'] < $threshold) {
            return $result;
        }

        $result['certificate'] = static::where('user_id', $student->id)->where('class_id', $class->id)->first();
        if ($result['certificate']) {
            return $result;
        }

        do {
            $code = 'CERT-' . strtoupper(\Illuminate\Support\Str::random(12));
        } while (static::where('verification_code', $code)->exists());

        $result['certificate'] = static::create([
            'user_id' => $student->id,
            'class_id' => $class->id,
            'verification_code' => $code,
            'qr_code_path' => 'certificates/qr-' . $code . '.svg',
            'issued_at' => now(),
        ]);
        $result['created'] = true;

        return $result;
    }

    /**
     * Tell the certificate's owner about it. A failed notification is logged, never fatal.
     */
    public function notifyOwner(string $title, string $message): void
    {
        try {
            $this->user->notify(new \App\Notifications\ClassActivityNotification(
                $title,
                $message,
                route('certificates.show', $this->id),
                'certificate'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Certificate notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Public page anyone can open (or reach by scanning the QR code) to confirm the certificate.
     */
    public function verificationUrl(): string
    {
        return route('certificates.verify', $this->verification_code);
    }

    /**
     * A scannable QR code (inline SVG) that opens the verification page.
     */
    public function qrSvg(int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd()));
        $svg = $writer->writeString($this->verificationUrl());

        // Drop the XML declaration so the SVG can be inlined in HTML
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    /**
     * The student's record for this class, read from their graded work: every lab with the
     * best grade they earned (an instructor's override counts instead of the AI grade),
     * totals, time spent, and the competencies those labs assess.
     */
    public function record(): array
    {
        $class = $this->schoolClass->loadModuleTree(fn ($q) => $q->with('competencies:id,code,name'));
        $userId = $this->user_id;

        // Modules in syllabus order, sections right after their parent
        $ordered = [];
        $byParent = $class->modules->groupBy('parent_id');
        $walk = function ($parentId, $depth) use (&$walk, &$ordered, $byParent) {
            foreach (($byParent[$parentId] ?? collect())->sortBy('order_index') as $module) {
                $ordered[] = [$module, $depth];
                $walk($module->id, $depth + 1);
            }
        };
        $walk(null, 0);

        $labIds = $class->modules->flatMap->laboratories->pluck('id')->unique()->values();

        $sessions = LabSession::where('user_id', $userId)
            ->whereIn('lab_id', $labIds)
            ->where('status', 'completed')
            ->get(['id', 'lab_id', 'performance_score', 'instructor_grade_override', 'started_at', 'ended_at']);

        $bestByLab = $sessions->groupBy('lab_id')->map(function ($attempts) {
            $best = $attempts->sortByDesc(fn ($s) => (float) ($s->instructor_grade_override ?? $s->performance_score ?? 0))->first();

            return [
                'grade' => round((float) ($best->instructor_grade_override ?? $best->performance_score ?? 0), 1),
                'overridden' => $best->instructor_grade_override !== null,
                'completed_at' => $best->ended_at,
                'attempts' => $attempts->count(),
            ];
        });

        $modules = [];
        $competencyIds = [];
        foreach ($ordered as [$module, $depth]) {
            $labs = [];
            foreach ($module->laboratories as $lab) {
                $result = $bestByLab[$lab->id] ?? null;
                $labs[] = [
                    'title' => $lab->title,
                    'live' => $lab->isLiveLab(),
                    'result' => $result,
                ];
                foreach ($lab->competencies as $competency) {
                    $competencyIds[$competency->id] = true;
                }
            }
            $modules[] = ['title' => $module->title, 'depth' => $depth, 'labs' => $labs];
        }

        $grades = $bestByLab->pluck('grade');
        $minutes = (int) round($sessions->sum(fn ($s) => $s->started_at && $s->ended_at
            ? max(0, $s->started_at->diffInSeconds($s->ended_at)) / 60
            : 0));

        $competencies = StudentCompetency::with('competency:id,code,name,description')
            ->where('user_id', $userId)
            ->whereIn('competency_id', array_keys($competencyIds))
            ->get()
            ->sortBy(fn ($sc) => $sc->competency->code ?? '')
            ->map(fn ($sc) => [
                'code' => $sc->competency->code ?? '',
                'name' => $sc->competency->name ?? '',
                'description' => $sc->competency->description ?? null,
                'score' => round((float) $sc->score_achieved, 1),
            ])
            ->values()
            ->all();

        $total = $labIds->count();
        $completed = $bestByLab->count();

        return [
            'class' => $class,
            'modules' => $modules,
            'labs_total' => $total,
            'labs_completed' => $completed,
            'completion' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'average_grade' => $grades->isNotEmpty() ? round((float) $grades->avg(), 1) : null,
            'pass_mark' => $class->passing_threshold ?? 75,
            'minutes' => $minutes,
            'competencies' => $competencies,
            'first_activity' => $sessions->min('started_at'),
            'last_activity' => $sessions->max('ended_at'),
        ];
    }
}
