<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\ClassPendingStudent;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/**
 * A student enters a class only by being on its roster, which the class's instructor (or an
 * admin) fills by hand or from an Excel list. There is no join code.
 */
class ClassRosterTest extends TestCase
{
    use RefreshDatabase;

    private User $instructor;

    private User $student;

    private SchoolClass $class;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        // These pages cache by user id in the file store, which real local runs share
        config(['cache.stores.file' => ['driver' => 'array']]);

        $this->instructor = $this->user('Prof Reyes', 'prof@example.com', 'instructor');
        $this->student = $this->user('Ana Cruz', 'ana@example.com');
        $this->class = SchoolClass::create(['name' => 'Java Basics', 'instructor_id' => $this->instructor->id]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function user(string $name, string $email, string $role = 'student'): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => bcrypt('password'), 'role' => $role]);
    }

    private function isEnrolled(User $user, ?SchoolClass $class = null): bool
    {
        return DB::table('class_student')
            ->where('class_id', ($class ?? $this->class)->id)
            ->where('student_id', $user->id)
            ->where('status', 'enrolled')
            ->exists();
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(array $rows, string $name = 'students.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        $this->files[] = $path;

        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, $name, null, null, true);
    }

    private function import(UploadedFile $file, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->instructor)
            ->from(route('classes.show', $this->class->id))
            ->post(route('classes.students.import', $this->class->id), ['roster_file' => $file]);
    }

    private function lab(): Laboratory
    {
        $module = Module::create(['class_id' => $this->class->id, 'title' => 'Basics', 'content' => 'Read this']);

        return Laboratory::create(['title' => 'Hello', 'description' => 'Print hello.', 'module_id' => $module->id, 'tasks_definition' => []]);
    }

    // Join code is gone

    public function test_the_join_code_and_invitation_routes_are_gone(): void
    {
        $this->class->forceFill(['code' => 'CLASS-OLD123'])->save();

        $this->actingAs($this->student)->post('/classes/join', ['code' => 'CLASS-OLD123'])->assertStatus(405);
        $this->actingAs($this->instructor)->post("/classes/{$this->class->id}/invite", ['email' => 'ana@example.com'])->assertNotFound();
        $this->actingAs($this->student)->post("/classes/{$this->class->id}/invite-accept")->assertNotFound();

        $this->assertFalse($this->isEnrolled($this->student));
    }

    public function test_new_classes_get_no_join_code(): void
    {
        $this->actingAs($this->instructor)->post(route('classes.store'), ['name' => 'Data Structures'])->assertRedirect(route('classes.index'));

        $this->assertNull(SchoolClass::where('name', 'Data Structures')->value('code'));
    }

    public function test_students_are_told_their_instructor_adds_them(): void
    {
        $this->actingAs($this->student)->get(route('classes.index'))
            ->assertOk()
            ->assertDontSee('join_class_code', false)
            ->assertSee('Your instructor adds you to their class roster with')
            ->assertSee('ana@example.com');

        $this->actingAs($this->student)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get(route('dashboard.sections'))
            ->assertOk()
            ->assertDontSee('#join_class_code', false)
            ->assertDontSee('class code');
    }

    // Access is gated on the roster

    public function test_a_student_off_the_roster_cannot_enter_the_class_or_its_labs(): void
    {
        $lab = $this->lab();

        $this->actingAs($this->student)->get(route('classes.show', $this->class->id))->assertForbidden();
        $this->actingAs($this->student)->get(route('laboratories.show', $lab->id))->assertForbidden();
        $this->actingAs($this->student)->post(route('laboratories.start', $lab->id))->assertForbidden();
        $this->actingAs($this->student)->get(route('classes.index'))->assertOk()->assertDontSee('Java Basics');
    }

    public function test_a_student_on_the_roster_enters_the_class(): void
    {
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $this->actingAs($this->student)->get(route('classes.show', $this->class->id))->assertOk()->assertSee('Java Basics');
        $this->actingAs($this->student)->get(route('classes.index'))->assertOk()->assertSee('Java Basics');
    }

    // Adding by hand

    public function test_adding_a_student_with_an_account_enrolls_and_tells_them(): void
    {
        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => ' ANA@Example.com '])
            ->assertRedirect(route('classes.show', $this->class->id))
            ->assertSessionHas('success', 'Ana Cruz (ana@example.com) is now in the class.');

        $this->assertTrue($this->isEnrolled($this->student));
        $this->assertSame(['Added to class: Java Basics'], $this->student->fresh()->notifications->pluck('data.title')->all());
        $this->actingAs($this->student)->get(route('classes.show', $this->class->id))->assertOk();
    }

    public function test_adding_an_email_without_an_account_keeps_it_pending(): void
    {
        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'Ben@School.edu', 'name' => 'Ben Santos'])
            ->assertSessionHas('success', 'Ben Santos (ben@school.edu) has no Certicode account yet. They join the class as soon as they sign up or sign in with that email.');

        $this->assertDatabaseHas('class_pending_students', ['class_id' => $this->class->id, 'email' => 'ben@school.edu', 'name' => 'Ben Santos', 'added_by' => $this->instructor->id]);

        $this->actingAs($this->instructor)->get(route('classes.show', $this->class->id))
            ->assertOk()->assertSee('Ben Santos')->assertSee('ben@school.edu')->assertSee('No account yet');
    }

    public function test_adding_someone_already_on_the_roster_changes_nothing(): void
    {
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ben@school.edu']);

        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'ana@example.com'])
            ->assertSessionHas('success', 'Ana Cruz (ana@example.com) is already on the roster.');
        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'BEN@school.edu', 'name' => 'Ben'])
            ->assertSessionHas('success', 'Ben (ben@school.edu) is already on the roster.');

        $this->assertSame(1, DB::table('class_student')->where('class_id', $this->class->id)->count());
        $this->assertSame(1, ClassPendingStudent::count());
        $this->assertSame('Ben', ClassPendingStudent::value('name'));
        $this->assertCount(0, $this->student->fresh()->notifications);
    }

    public function test_instructor_and_admin_accounts_cannot_be_added_as_students(): void
    {
        $this->user('Other Prof', 'other@example.com', 'instructor');

        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'other@example.com'])
            ->assertSessionHas('error', "Other Prof (other@example.com) can't be added: This is an instructor or admin account, not a student.");

        $this->assertSame(0, DB::table('class_student')->count());
        $this->assertSame(0, ClassPendingStudent::count());
    }

    public function test_a_student_is_found_by_a_gmail_google_proved(): void
    {
        $this->student->forceFill(['gmail' => 'ana.cruz@gmail.com', 'gmail_verified_at' => now()])->save();
        $unverified = $this->user('Carl', 'carl@example.com');
        $unverified->forceFill(['gmail' => 'carl.x@gmail.com'])->save();

        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'ana.cruz@gmail.com']);
        $this->actingAs($this->instructor)->post(route('classes.students.add', $this->class->id), ['email' => 'carl.x@gmail.com']);

        $this->assertTrue($this->isEnrolled($this->student));
        $this->assertFalse($this->isEnrolled($unverified));
        $this->assertDatabaseHas('class_pending_students', ['email' => 'carl.x@gmail.com']);
    }

    public function test_adding_needs_a_valid_email(): void
    {
        $this->actingAs($this->instructor)->from(route('classes.show', $this->class->id))
            ->post(route('classes.students.add', $this->class->id), ['email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, ClassPendingStudent::count());
    }

    // Removing

    public function test_removing_a_student_takes_away_their_access(): void
    {
        $lab = $this->lab();
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);

        $this->actingAs($this->instructor)->delete(route('classes.students.remove', [$this->class->id, $this->student->id]))
            ->assertRedirect(route('classes.show', $this->class->id))
            ->assertSessionHas('success', 'Ana Cruz was removed from the class.');

        $this->assertFalse($this->isEnrolled($this->student));
        $this->actingAs($this->student)->get(route('classes.show', $this->class->id))->assertForbidden();
        $this->actingAs($this->student)->get(route('laboratories.show', $lab->id))->assertForbidden();
    }

    public function test_a_student_with_a_certificate_stays_on_the_roster(): void
    {
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        Certificate::create([
            'user_id' => $this->student->id,
            'class_id' => $this->class->id,
            'verification_code' => 'CERT-TEST',
            'issued_at' => now(),
        ]);

        $this->actingAs($this->instructor)->delete(route('classes.students.remove', [$this->class->id, $this->student->id]))
            ->assertSessionHas('error', 'Ana Cruz holds a certificate for this class, so they stay on the roster.');

        $this->assertTrue($this->isEnrolled($this->student));
    }

    public function test_removing_someone_not_on_this_roster_is_not_found(): void
    {
        $otherClass = SchoolClass::create(['name' => 'Other', 'instructor_id' => $this->instructor->id]);
        $otherClass->students()->attach($this->student->id, ['status' => 'enrolled']);
        $pending = ClassPendingStudent::create(['class_id' => $otherClass->id, 'email' => 'ben@school.edu']);

        $this->actingAs($this->instructor)->delete(route('classes.students.remove', [$this->class->id, $this->student->id]))->assertNotFound();
        $this->actingAs($this->instructor)->delete(route('classes.pending-students.remove', [$this->class->id, $pending->id]))->assertNotFound();

        $this->assertTrue($this->isEnrolled($this->student, $otherClass));
        $this->assertModelExists($pending);
    }

    public function test_removing_a_pending_email(): void
    {
        $pending = ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ben@school.edu']);

        $this->actingAs($this->instructor)->delete(route('classes.pending-students.remove', [$this->class->id, $pending->id]))
            ->assertSessionHas('success', 'ben@school.edu was removed from the roster.');

        $this->assertModelMissing($pending);
    }

    // Who may change a roster

    public function test_only_the_class_instructor_or_an_admin_can_change_the_roster(): void
    {
        $other = $this->user('Other Prof', 'other@example.com', 'instructor');
        $classmate = $this->user('Ben Santos', 'ben@example.com');
        $this->class->students()->attach($classmate->id, ['status' => 'enrolled']);
        $pending = ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'carl@school.edu']);
        $sheet = [['Email'], ['ana@example.com']];

        foreach ([$other, $classmate] as $outsider) {
            $this->actingAs($outsider)->post(route('classes.students.add', $this->class->id), ['email' => 'ana@example.com'])->assertForbidden();
            $this->import($this->xlsx($sheet), $outsider)->assertForbidden();
            $this->actingAs($outsider)->delete(route('classes.students.remove', [$this->class->id, $classmate->id]))->assertForbidden();
            $this->actingAs($outsider)->delete(route('classes.pending-students.remove', [$this->class->id, $pending->id]))->assertForbidden();
        }

        $this->assertFalse($this->isEnrolled($this->student));
        $this->assertTrue($this->isEnrolled($classmate));
        $this->assertModelExists($pending);

        $admin = $this->user('Admin', 'admin@example.com', 'admin');
        $this->import($this->xlsx($sheet), $admin)->assertRedirect(route('classes.show', $this->class->id));
        $this->assertTrue($this->isEnrolled($this->student));
    }

    public function test_only_staff_see_the_roster_controls(): void
    {
        $this->class->students()->attach($this->student->id, ['status' => 'enrolled']);
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ben@school.edu']);

        $this->actingAs($this->instructor)->get(route('classes.show', $this->class->id))
            ->assertOk()->assertSee('Import students')->assertSee('ben@school.edu')
            ->assertDontSee('Join code')->assertDontSee('Press and hold to unhide');

        $this->actingAs($this->student)->get(route('classes.show', $this->class->id))
            ->assertOk()->assertDontSee('Import students')->assertDontSee('ben@school.edu');
    }

    // Importing an Excel list

    public function test_import_reports_every_row(): void
    {
        $this->user('Other Prof', 'other@example.com', 'instructor');
        $enrolled = $this->user('Dee Lim', 'dee@example.com');
        $this->class->students()->attach($enrolled->id, ['status' => 'enrolled']);

        $response = $this->import($this->xlsx([
            ['Student Name', 'E-mail', 'Section'],   // 1: headings, any order
            ['Ana Cruz', 'ANA@example.com', 'A'],    // 2: account, enrolled now
            ['Ben Santos', 'ben@school.edu', 'A'],   // 3: no account yet
            ['', '', ''],                            // 4: blank, skipped
            ['Dee Lim', 'dee@example.com', 'B'],     // 5: already enrolled
            ['Ana again', 'ana@example.com', 'B'],   // 6: twice in the file
            ['Eve', 'eve@', 'B'],                    // 7: not an email
            ['Prof', 'other@example.com', 'B'],      // 8: not a student
            ['Nobody', '', 'B'],                     // 9: no email
        ]));

        $response->assertRedirect(route('classes.show', $this->class->id))
            ->assertSessionHas('success', 'Imported 7 rows: 2 added, 2 already on the roster, 3 not added.');

        $report = session('roster_report');
        $this->assertSame([[2, 'ana@example.com', 'Ana Cruz']], array_map(fn ($r) => [$r['row'], $r['email'], $r['name']], $report['enrolled']));
        $this->assertSame([[3, 'ben@school.edu', 'Ben Santos']], array_map(fn ($r) => [$r['row'], $r['email'], $r['name']], $report['pending']));
        $this->assertSame([[5, null], [6, 'Also on row 2']], array_map(fn ($r) => [$r['row'], $r['note']], $report['present']));
        $this->assertSame([
            [7, 'Not an email address'],
            [8, 'This is an instructor or admin account, not a student'],
            [9, 'No email address'],
        ], array_map(fn ($r) => [$r['row'], $r['note']], $report['invalid']));
        $this->assertFalse($report['truncated']);

        $this->assertTrue($this->isEnrolled($this->student));
        $this->assertDatabaseHas('class_pending_students', ['class_id' => $this->class->id, 'email' => 'ben@school.edu', 'name' => 'Ben Santos']);
        $this->assertSame(1, ClassPendingStudent::count());
    }

    public function test_the_class_page_shows_the_import_report(): void
    {
        $this->import($this->xlsx([['Email'], ['ana@example.com'], ['eve@']]));

        $this->actingAs($this->instructor)->get(route('classes.show', $this->class->id))
            ->assertOk()
            ->assertSee('Import report')
            ->assertSeeInOrder(['Added', 'Row 2', 'ana@example.com', 'Not added', 'Row 3', 'eve@', 'Not an email address']);
    }

    public function test_import_joins_first_and_last_name_columns(): void
    {
        $this->import($this->xlsx([['Last Name', 'First Name', 'Email Address'], ['Santos', 'Ben', 'ben@school.edu']]));

        $this->assertDatabaseHas('class_pending_students', ['email' => 'ben@school.edu', 'name' => 'Ben Santos']);
    }

    public function test_a_sheet_without_an_email_heading_is_refused(): void
    {
        $this->import($this->xlsx([['ana@example.com', 'Ana'], ['ben@school.edu', 'Ben']]))
            ->assertRedirect(route('classes.show', $this->class->id))
            ->assertSessionHasErrors(['roster_file' => 'The first row of the sheet needs a column headed "Email" (and optionally "Name").']);

        $this->assertFalse($this->isEnrolled($this->student));
        $this->assertSame(0, ClassPendingStudent::count());
    }

    public function test_an_empty_sheet_is_refused(): void
    {
        $this->import($this->xlsx([]))->assertSessionHasErrors(['roster_file' => 'That sheet is empty.']);
    }

    public function test_a_heading_with_no_rows_under_it_says_so(): void
    {
        $this->import($this->xlsx([['Email', 'Name']]))
            ->assertSessionHas('error', 'No student rows were found under the heading row.');
    }

    public function test_only_xlsx_files_are_accepted(): void
    {
        $csv = UploadedFile::fake()->createWithContent('students.csv', "Email\nana@example.com\n");
        $this->import($csv)->assertSessionHasErrors(['roster_file' => 'Use an Excel workbook (.xlsx). Save other formats as .xlsx first.']);

        $broken = UploadedFile::fake()->createWithContent('students.xlsx', 'not a workbook');
        $this->import($broken)->assertSessionHasErrors(['roster_file' => "That file couldn't be read. Save it as an Excel workbook (.xlsx) and try again."]);

        $this->import(UploadedFile::fake()->create('big.xlsx', 3000))->assertSessionHasErrors('roster_file');

        $this->assertFalse($this->isEnrolled($this->student));
    }

    public function test_long_lists_are_cut_short_and_say_so(): void
    {
        $rows = [['Email']];
        for ($i = 1; $i <= 1002; $i++) {
            $rows[] = ["s{$i}@school.edu"];
        }

        $this->import($this->xlsx($rows));

        $this->assertSame(1000, ClassPendingStudent::count());
        $this->assertTrue(session('roster_report')['truncated']);
        $this->assertDatabaseMissing('class_pending_students', ['email' => 's1001@school.edu']);
    }

    // Emails waiting for an account

    public function test_signing_up_with_a_pending_email_enrolls_the_new_student(): void
    {
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ben@school.edu', 'name' => 'Ben Santos']);

        $this->post(route('register.store'), [
            'name' => 'Ben Santos',
            'email' => 'Ben@School.edu',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            'role' => 'student',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $ben = User::where('email', 'ben@school.edu')->firstOrFail();
        $this->assertTrue($this->isEnrolled($ben));
        $this->assertSame(0, ClassPendingStudent::count());
        $this->assertContains('Added to class: Java Basics', $ben->notifications->pluck('data.title')->all());
        $this->get(route('classes.show', $this->class->id))->assertOk();
    }

    public function test_any_sign_in_enrolls_a_student_whose_email_waits_on_a_roster(): void
    {
        $other = SchoolClass::create(['name' => 'Web Dev', 'instructor_id' => $this->instructor->id]);
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ana@example.com']);
        ClassPendingStudent::create(['class_id' => $other->id, 'email' => 'ana@example.com']);

        Auth::login($this->student);

        $this->assertTrue($this->isEnrolled($this->student));
        $this->assertTrue($this->isEnrolled($this->student, $other));
        $this->assertSame(0, ClassPendingStudent::count());
    }

    public function test_a_pending_gmail_counts_only_once_google_proved_it(): void
    {
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'ana.cruz@gmail.com']);
        $this->student->forceFill(['gmail' => 'ana.cruz@gmail.com'])->save();

        Auth::login($this->student);
        $this->assertFalse($this->isEnrolled($this->student));

        $this->student->forceFill(['gmail_verified_at' => now()])->save();
        Auth::login($this->student);
        $this->assertTrue($this->isEnrolled($this->student));
    }

    public function test_a_pending_email_never_enrolls_an_instructor(): void
    {
        ClassPendingStudent::create(['class_id' => $this->class->id, 'email' => 'other@example.com']);
        $other = $this->user('Other Prof', 'other@example.com', 'instructor');

        Auth::login($other);

        $this->assertFalse($this->isEnrolled($other));
        $this->assertSame(1, ClassPendingStudent::count());
    }

    // Existing data

    public function test_the_migration_turns_invitations_into_enrollments(): void
    {
        $invited = $this->user('Ben Santos', 'ben@example.com');
        DB::table('class_student')->insert([
            ['class_id' => $this->class->id, 'student_id' => $this->student->id, 'status' => 'enrolled', 'created_at' => now(), 'updated_at' => now()],
            ['class_id' => $this->class->id, 'student_id' => $invited->id, 'status' => 'invited', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require database_path('migrations/2026_11_16_000001_enroll_invited_class_students.php');
        $migration->up();

        $this->assertTrue($this->isEnrolled($this->student));
        $this->assertTrue($this->isEnrolled($invited));
        $this->assertSame(0, DB::table('class_student')->where('status', '!=', 'enrolled')->count());
    }

    /**
     * SQLite rebuilds a table to change a column. Inside a transaction (such as this test's)
     * that rebuild cascade-deletes every class's roster, so this runs on its own file database
     * through the migrator, as `php artisan migrate` would on a local SQLite database.
     */
    public function test_making_the_join_code_optional_keeps_every_roster_on_sqlite(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Needs pdo_sqlite for a scratch database.');
        }

        $path = tempnam(sys_get_temp_dir(), 'rosterdb');
        $this->files[] = $path;
        config(['database.connections.roster_scratch' => [
            'driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);

        try {
            $schema = Schema::connection('roster_scratch');
            $schema->create('school_classes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
            });
            $schema->create('class_student', function (Blueprint $table) {
                $table->id();
                $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
                $table->unsignedBigInteger('student_id');
            });
            $db = DB::connection('roster_scratch');
            $db->table('school_classes')->insert(['id' => 1, 'name' => 'Old class', 'code' => 'CLASS-OLD123']);
            $db->table('class_student')->insert(['class_id' => 1, 'student_id' => 7]);

            $migrator = app('migrator');
            $migrator->usingConnection('roster_scratch', function () use ($migrator) {
                $migrator->getRepository()->createRepository();
                $migrator->run([database_path('migrations/2026_11_16_000002_make_class_join_code_optional.php')]);
            });

            $this->assertSame(1, $db->table('class_student')->count());
            $this->assertSame('CLASS-OLD123', $db->table('school_classes')->value('code'));
            $db->table('school_classes')->insert(['name' => 'New class', 'code' => null]);
            $this->assertNull($db->table('school_classes')->where('name', 'New class')->value('code'));
        } finally {
            DB::purge('roster_scratch');
        }
    }
}
