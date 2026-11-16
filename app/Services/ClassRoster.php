<?php

namespace App\Services;

use App\Http\Controllers\ClassController;
use App\Models\ClassPendingStudent;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Who may enter a class. The instructor puts students on the roster by hand or from an Excel
 * list; there is no join code. A student with an account is enrolled at once. An email with
 * no account yet waits as a pending entry and is enrolled when someone signs in with it.
 */
class ClassRoster
{
    /** Student rows read from one file; the rest are reported as not read */
    public const MAX_IMPORT_ROWS = 1000;

    /** A stray cell far down the sheet would otherwise make the reader walk every empty row */
    private const MAX_SHEET_ROW = 5000;

    private const EMAIL_HEADINGS = ['email', 'e-mail', 'email address', 'e-mail address', 'student email', 'school email'];

    private const NAME_HEADINGS = ['name', 'full name', 'student name', 'student'];

    private const FIRST_NAME_HEADINGS = ['first name', 'firstname', 'given name'];

    private const LAST_NAME_HEADINGS = ['last name', 'lastname', 'surname', 'family name'];

    /**
     * Put one person on the roster.
     *
     * @return array{result: 'enrolled'|'pending'|'present'|'invalid', email: string, name: ?string, note: ?string}
     */
    public function add(SchoolClass $class, ?string $email, ?string $name = null, ?User $by = null): array
    {
        $email = (string) User::normalizeEmail($email);
        $name = $this->cleanName($name);
        $outcome = fn (string $result, ?string $note = null, ?string $shownName = null) => [
            'result' => $result, 'email' => $email, 'name' => $shownName ?? $name, 'note' => $note,
        ];

        if ($email === '') {
            return $outcome('invalid', 'No email address');
        }
        if (Validator::make(['email' => $email], ['email' => 'email|max:255'])->fails()) {
            return $outcome('invalid', 'Not an email address');
        }

        $student = $this->accountFor($email);
        if ($student) {
            if ($student->role !== 'student') {
                return $outcome('invalid', 'This is an instructor or admin account, not a student', $student->name);
            }
            if ($class->students()->where('users.id', $student->id)->exists()) {
                return $outcome('present', null, $student->name);
            }

            try {
                $class->students()->attach($student->id, ['status' => 'enrolled']);
            } catch (UniqueConstraintViolationException) {
                return $outcome('present', null, $student->name); // added by a request running at the same moment
            }
            ClassPendingStudent::where('class_id', $class->id)->where('email', $email)->delete();
            ClassController::clearClassesCache($student->id);
            $this->tellStudent($student, $class);

            return $outcome('enrolled', null, $student->name);
        }

        $pending = ClassPendingStudent::where('class_id', $class->id)->where('email', $email)->first();
        if ($pending) {
            if ($name !== null && $pending->name === null) {
                $pending->update(['name' => $name]);
            }

            return $outcome('present', 'No account yet', $pending->name);
        }

        try {
            ClassPendingStudent::create(['class_id' => $class->id, 'email' => $email, 'name' => $name, 'added_by' => $by?->id]);
        } catch (UniqueConstraintViolationException) {
            return $outcome('present', 'No account yet');
        }

        return $outcome('pending');
    }

    /**
     * Read an .xlsx student list and put every row on the roster. The first non-empty row
     * holds the headings: an Email column, and optionally Name (or First/Last name).
     *
     * @return array{enrolled: list<array>, pending: list<array>, present: list<array>, invalid: list<array>, truncated: bool}
     *
     * @throws ValidationException when the file can't be read or has no Email heading
     */
    public function import(SchoolClass $class, string $path, ?User $by = null): array
    {
        [$rows, $truncated] = $this->readStudentRows($path);
        $report = ['enrolled' => [], 'pending' => [], 'present' => [], 'invalid' => [], 'truncated' => $truncated];
        $seen = [];

        foreach ($rows as [$rowNumber, $email, $name]) {
            $key = User::normalizeEmail($email);
            if ($key !== '' && isset($seen[$key])) {
                $report['present'][] = ['row' => $rowNumber, 'email' => $key, 'name' => $this->cleanName($name), 'note' => "Also on row {$seen[$key]}"];

                continue;
            }
            if ($key !== '') {
                $seen[$key] = $rowNumber;
            }

            $outcome = $this->add($class, $email, $name, $by);
            $report[$outcome['result']][] = ['row' => $rowNumber] + $outcome;
        }

        return $report;
    }

    /**
     * Enroll a student in every class whose roster lists their email, or their Gmail once
     * Google has proved they own it. Runs on each sign-in.
     */
    public function enrollPending(User $user): int
    {
        if ($user->role !== 'student') {
            return 0;
        }

        $emails = array_values(array_filter(array_unique([
            User::normalizeEmail($user->email),
            $user->gmail_verified_at ? User::normalizeEmail($user->gmail) : null,
        ])));
        if ($emails === []) {
            return 0;
        }

        $entries = ClassPendingStudent::whereIn('email', $emails)->with('schoolClass')->get();
        foreach ($entries as $entry) {
            $class = $entry->schoolClass;
            DB::transaction(function () use ($entry, $class, $user) {
                if ($class && ! $class->students()->where('users.id', $user->id)->exists()) {
                    $class->students()->attach($user->id, ['status' => 'enrolled']);
                }
                $entry->delete();
            });
            if ($class) {
                $this->tellStudent($user, $class);
            }
        }

        if ($entries->isNotEmpty()) {
            ClassController::clearClassesCache($user->id);
        }

        return $entries->count();
    }

    /**
     * The account an email on a list belongs to: its sign-in email, or a Gmail Google proved.
     */
    public function accountFor(string $email): ?User
    {
        return User::whereEmailIs($email)->first()
            ?? User::whereGmailIs($email)->whereNotNull('gmail_verified_at')->first();
    }

    /**
     * The data rows of the first sheet as [row number, email, name], at most MAX_IMPORT_ROWS,
     * and whether rows were left out past that limit or MAX_SHEET_ROW.
     *
     * @return array{list<array{int, string, ?string}>, bool}
     *
     * @throws ValidationException
     */
    private function readStudentRows(string $path): array
    {
        $options = new Options;
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true; // so the row numbers match Excel's
        $reader = new Reader($options);
        $rows = [];
        $columns = null;
        $truncated = false;

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    if ($rowNumber > self::MAX_SHEET_ROW) {
                        $truncated = true;
                        break;
                    }

                    $cells = array_map(fn ($value) => $this->cellText($value), $row->toArray());
                    if (implode('', $cells) === '') {
                        continue;
                    }

                    if ($columns === null) {
                        $columns = $this->headingColumns($cells);
                        if (! isset($columns['email'])) {
                            throw ValidationException::withMessages(['roster_file' => 'The first row of the sheet needs a column headed "Email" (and optionally "Name").']);
                        }

                        continue;
                    }

                    if (count($rows) >= self::MAX_IMPORT_ROWS) {
                        $truncated = true;
                        break;
                    }

                    $rows[] = [$rowNumber, $cells[$columns['email']] ?? '', $this->nameFrom($cells, $columns)];
                }

                break; // only the first sheet
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::info('Roster import could not read a file: '.$e->getMessage());

            throw ValidationException::withMessages(['roster_file' => "That file couldn't be read. Save it as an Excel workbook (.xlsx) and try again."]);
        } finally {
            $reader->close();
        }

        if ($columns === null) {
            throw ValidationException::withMessages(['roster_file' => 'That sheet is empty.']);
        }

        return [$rows, $truncated];
    }

    /**
     * Where each known heading sits in the first row.
     *
     * @param  list<string>  $cells
     * @return array<string, int>
     */
    private function headingColumns(array $cells): array
    {
        $columns = [];
        foreach ($cells as $index => $text) {
            $heading = preg_replace('/[\s_]+/', ' ', mb_strtolower(trim($text)));
            $field = match (true) {
                in_array($heading, self::EMAIL_HEADINGS, true) => 'email',
                in_array($heading, self::NAME_HEADINGS, true) => 'name',
                in_array($heading, self::FIRST_NAME_HEADINGS, true) => 'first',
                in_array($heading, self::LAST_NAME_HEADINGS, true) => 'last',
                default => null,
            };
            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }

        return $columns;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int>  $columns
     */
    private function nameFrom(array $cells, array $columns): ?string
    {
        if (isset($columns['name'])) {
            return $cells[$columns['name']] ?? null;
        }

        $parts = array_filter([
            isset($columns['first']) ? ($cells[$columns['first']] ?? '') : '',
            isset($columns['last']) ? ($cells[$columns['last']] ?? '') : '',
        ], fn ($part) => $part !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }

    private function cellText(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_string($value) => trim($value),
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            is_int($value), is_float($value) => (string) $value,
            default => '',
        };
    }

    private function cleanName(?string $name): ?string
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        return $name === '' ? null : mb_substr($name, 0, 255);
    }

    private function tellStudent(User $student, SchoolClass $class): void
    {
        try {
            $student->notify(new ClassActivityNotification(
                "Added to class: {$class->name}",
                "You're now enrolled in '{$class->name}'".($class->instructor ? " with {$class->instructor->name}." : '.'),
                route('classes.show', $class->id),
                'class'
            ));
        } catch (\Throwable $e) {
            Log::warning('Class roster notification failed: '.$e->getMessage());
        }
    }
}
