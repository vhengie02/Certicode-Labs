<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staff read a student's submitted code on the attempt's results page, and in full in the
 * similarity compare view. Students don't get the viewer, and an instructor only reaches
 * the code of classes they manage.
 */
class SubmittedFilesViewerTest extends TestCase
{
    use RefreshDatabase;

    protected User $instructor;

    protected User $student;

    protected SchoolClass $schoolClass;

    protected Laboratory $laboratory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->instructor = $this->user('Instructor Doe', 'instructor');
        $this->student = $this->user('Student Jane', 'student');

        $this->schoolClass = SchoolClass::create([
            'name' => 'Java Basics',
            'code' => 'JAVA101',
            'instructor_id' => $this->instructor->id,
            'passing_threshold' => 60,
        ]);
        $this->schoolClass->students()->attach($this->student->id, ['status' => 'enrolled']);

        $module = Module::create([
            'class_id' => $this->schoolClass->id,
            'title' => 'Exceptions',
            'content' => 'Syllabus content',
        ]);

        $this->laboratory = Laboratory::create([
            'title' => 'Custom Exceptions',
            'description' => 'Throw your own.',
            'time_limit' => 60,
            'module_id' => $module->id,
            'tasks_definition' => [
                ['id' => 1, 'task' => 'Create InvalidAgeException', 'command' => 'regex:class\s+InvalidAgeException'],
            ],
        ]);
    }

    private function user(string $name, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    private function finishedSession(array $attributes = []): LabSession
    {
        return LabSession::create(array_merge([
            'lab_id' => $this->laboratory->id,
            'user_id' => $this->student->id,
            'status' => 'completed',
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(20),
            'performance_score' => 80,
            'completed_tasks' => [1],
            'ai_grade_summary' => ['summary' => 'Fine.'],
        ], $attributes));
    }

    private function twoFiles(): array
    {
        return [
            ['name' => 'Helper.java', 'content' => 'class Helper {}', 'is_primary' => false],
            ['name' => 'Main.java', 'content' => "class Main {\n}", 'is_primary' => true],
        ];
    }

    public function test_instructor_reads_every_file_with_the_main_file_first(): void
    {
        $session = $this->finishedSession(['submitted_code' => "class Main {\n}", 'submitted_files' => $this->twoFiles()]);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Submitted files')
            ->assertSee('The code that was graded.')
            ->assertSee('Main file')
            ->assertSee('2 lines')
            ->assertSeeInOrder(['Main.java', 'Helper.java'])
            ->assertSee('class Helper {}');
    }

    public function test_code_is_escaped_on_the_page(): void
    {
        $session = $this->finishedSession([
            'submitted_files' => [['name' => '<b>x</b>.html', 'content' => '<script>alert(1)</script>', 'is_primary' => true]],
        ]);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<b>x</b>.html', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_a_submission_without_files_shows_the_submitted_code(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class FromEditor {}', 'submitted_files' => []]);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('Submitted code')
            ->assertSee('class FromEditor {}');
    }

    public function test_malformed_file_entries_are_skipped(): void
    {
        $session = $this->finishedSession([
            'submitted_files' => [
                'not a file',
                ['name' => 'NoContent.java'],
                ['name' => ['bad'], 'content' => 'class Unnamed {}'],
            ],
        ]);

        $this->assertSame(
            [['name' => 'Untitled file', 'content' => 'class Unnamed {}', 'is_primary' => false]],
            $session->submittedFileList()
        );

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))->assertOk();
    }

    public function test_code_from_an_unsubmitted_attempt_is_marked_as_possibly_not_final(): void
    {
        $session = $this->finishedSession(['ai_grade_summary' => null, 'submitted_code' => 'class Partial {}']);

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('This attempt was not submitted.')
            ->assertSee('class Partial {}');
    }

    public function test_an_attempt_with_no_code_says_so(): void
    {
        $session = $this->finishedSession();

        $this->actingAs($this->instructor)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertSee('No code was saved for this attempt.');
    }

    public function test_student_does_not_get_the_viewer(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);

        $this->actingAs($this->student)->get(route('sessions.show', $session->id))
            ->assertOk()
            ->assertDontSee('Submitted files')
            ->assertDontSee('class Helper {}');
    }

    public function test_instructor_of_another_class_cannot_read_the_code(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);
        $other = $this->user('Other Teacher', 'instructor');

        $this->actingAs($other)->get(route('sessions.show', $session->id))->assertForbidden();
        $this->actingAs($other)->getJson(route('instructor.sessions.files', $session->id))->assertForbidden();
    }

    public function test_compare_view_gets_the_full_code_past_the_preview_length(): void
    {
        $long = str_repeat('int value = 1;'."\n", 100);
        $session = $this->finishedSession(['submitted_code' => $long, 'submitted_files' => [['name' => 'Main.java', 'content' => $long, 'is_primary' => true]]]);

        $this->actingAs($this->instructor)->getJson(route('instructor.sessions.files', $session->id))
            ->assertOk()
            ->assertExactJson(['files' => [['name' => 'Main.java', 'content' => $long, 'is_primary' => true]]]);
    }

    public function test_student_cannot_fetch_the_files_endpoint(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}']);

        $this->actingAs($this->student)->getJson(route('instructor.sessions.files', $session->id))->assertForbidden();
    }

    public function test_monitoring_row_links_to_the_code_only_once_there_is_some_to_read(): void
    {
        $withCode = $this->finishedSession(['submitted_code' => 'class Main {}']);
        $otherStudent = $this->user('Student Sam', 'student');
        $this->schoolClass->students()->attach($otherStudent->id, ['status' => 'enrolled']);
        $withoutCode = $this->finishedSession(['user_id' => $otherStudent->id]);

        $this->actingAs($this->instructor)
            ->get(route('classes.telemetry', ['class_id' => $this->schoolClass->id, 'lab' => $this->laboratory->id]))
            ->assertOk()
            ->assertSee(route('sessions.show', $withCode->id).'#submitted-files', false)
            ->assertDontSee(route('sessions.show', $withoutCode->id).'#submitted-files', false);
    }

    /** The entries of a downloaded zip, as name => content. */
    private function zipEntries($response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $zip->open($path);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($path);

        return $entries;
    }

    public function test_instructor_downloads_one_file_under_its_own_name(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.sessions.files.download', [$session->id, 1]))
            ->assertOk()
            ->assertDownload('Helper.java');

        $this->assertSame('class Helper {}', $response->streamedContent());
    }

    public function test_instructor_downloads_every_file_as_a_zip(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => [
            ...$this->twoFiles(),
            ['name' => 'src/util/Tool.java', 'content' => 'class Tool {}', 'is_primary' => false],
        ]]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.sessions.files.download', $session->id))
            ->assertOk()
            ->assertDownload('custom-exceptions-student-jane-files.zip');

        $this->assertSame([
            'Main.java' => "class Main {\n}",
            'Helper.java' => 'class Helper {}',
            'src/util/Tool.java' => 'class Tool {}',
        ], $this->zipEntries($response));
    }

    public function test_a_file_name_cannot_escape_the_download_folder_or_the_header(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'x', 'submitted_files' => [
            ['name' => '../../evil.sh', 'content' => 'one', 'is_primary' => true],
            ['name' => '..\\..\\windows.bat', 'content' => 'two', 'is_primary' => false],
            ['name' => "a\"b\r\nX-Evil: 1.java", 'content' => 'three', 'is_primary' => false],
            ['name' => '..', 'content' => 'four', 'is_primary' => false],
        ]]);

        $response = $this->actingAs($this->instructor)->get(route('instructor.sessions.files.download', $session->id));

        $this->assertSame(['evil.sh', 'windows.bat', 'a_b__X-Evil_ 1.java', 'file.txt'], array_keys($this->zipEntries($response)));

        $this->actingAs($this->instructor)
            ->get(route('instructor.sessions.files.download', [$session->id, 2]))
            ->assertOk()
            ->assertHeaderMissing('X-Evil')
            ->assertDownload('a_b__X-Evil_ 1.java');
    }

    public function test_files_sharing_a_name_are_all_kept_in_the_zip(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'x', 'submitted_files' => [
            ['name' => 'Main.java', 'content' => 'one', 'is_primary' => true],
            ['name' => './Main.java', 'content' => 'two', 'is_primary' => false],
        ]]);

        $response = $this->actingAs($this->instructor)->get(route('instructor.sessions.files.download', $session->id));

        $this->assertSame(['Main.java' => 'one', 'Main (2).java' => 'two'], $this->zipEntries($response));
    }

    public function test_code_without_a_file_name_downloads_as_a_text_file(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class FromEditor {}', 'submitted_files' => []]);

        $response = $this->actingAs($this->instructor)
            ->get(route('instructor.sessions.files.download', [$session->id, 0]))
            ->assertDownload('submitted-code.txt');

        $this->assertSame('class FromEditor {}', $response->streamedContent());
    }

    public function test_only_the_class_manager_can_download_the_files(): void
    {
        $session = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);
        $url = route('instructor.sessions.files.download', [$session->id, 0]);

        $this->actingAs($this->student)->get($url)->assertForbidden();
        $this->actingAs($this->user('Other Teacher', 'instructor'))->get($url)->assertForbidden();
    }

    public function test_a_missing_file_is_not_found(): void
    {
        $withFiles = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);
        $withoutCode = $this->finishedSession();

        $this->actingAs($this->instructor)->get(route('instructor.sessions.files.download', [$withFiles->id, 9]))->assertNotFound();
        $this->actingAs($this->instructor)->get(route('instructor.sessions.files.download', $withoutCode->id))->assertNotFound();
    }

    public function test_the_results_page_offers_each_file_and_a_zip_for_several(): void
    {
        $several = $this->finishedSession(['submitted_code' => 'class Main {}', 'submitted_files' => $this->twoFiles()]);
        $single = $this->finishedSession(['submitted_code' => 'class Main {}']);

        $this->actingAs($this->instructor)->get(route('sessions.show', $several->id))
            ->assertSee(route('instructor.sessions.files.download', [$several->id, 1]), false)
            ->assertSee('Download all');

        $this->actingAs($this->instructor)->get(route('sessions.show', $single->id))
            ->assertSee(route('instructor.sessions.files.download', [$single->id, 0]), false)
            ->assertDontSee('Download all');
    }
}
