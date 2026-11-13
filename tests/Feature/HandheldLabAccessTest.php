<?php

namespace Tests\Feature;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\DeviceLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Labs run in VS Code, so a student on a phone or tablet can read a lab but can't start,
 * continue or retake it. Staff pages and desktop browsers are unchanged.
 */
class HandheldLabAccessTest extends TestCase
{
    use RefreshDatabase;

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1';

    private const ANDROID_PHONE = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

    private const ANDROID_TABLET = 'Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

    private const IPAD = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1';

    private const MAC = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15';

    private User $student;

    private User $instructor;

    private Module $module;

    private Laboratory $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::create(['name' => 'Student', 'email' => 'student@example.com', 'password' => bcrypt('password'), 'role' => 'student']);
        $this->instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        $class = SchoolClass::create(['name' => 'Java 101', 'code' => 'JAVA-101', 'instructor_id' => $this->instructor->id]);
        $class->students()->attach($this->student->id, ['status' => 'enrolled']);
        $this->module = Module::create(['class_id' => $class->id, 'title' => 'Module', 'content' => 'Content']);
        $this->lab = Laboratory::create(['module_id' => $this->module->id, 'title' => 'Loops', 'description' => 'Write a loop.', 'time_limit' => 45, 'is_group_lab' => false, 'tasks_definition' => []]);
    }

    private function labPage(User $user, string $userAgent)
    {
        return $this->actingAs($user)->withHeader('User-Agent', $userAgent)->get("/laboratories/{$this->lab->id}");
    }

    private function modulePage(User $user, string $userAgent)
    {
        return $this->actingAs($user)->withHeader('User-Agent', $userAgent)->get("/classes/{$this->module->class_id}/modules/{$this->module->id}");
    }

    /** Stands in for a passed camera check, so only the device decides whether the lab starts. */
    private function startWithCameraCheck(string $userAgent)
    {
        return $this->actingAs($this->student)
            ->withCameraCheck($this->lab->id)
            ->withHeader('User-Agent', $userAgent)
            ->postJson(route('laboratories.start', $this->lab->id));
    }

    public function test_phones_and_tablets_are_handheld_and_computers_are_not(): void
    {
        foreach ([self::IPHONE, self::ANDROID_PHONE, self::ANDROID_TABLET, self::IPAD] as $userAgent) {
            $this->assertTrue(DeviceLabel::isHandheld($userAgent), $userAgent);
        }

        foreach ([self::DESKTOP, self::MAC, null, ''] as $userAgent) {
            $this->assertFalse(DeviceLabel::isHandheld($userAgent), (string) $userAgent);
        }
    }

    public function test_a_student_on_a_phone_can_read_the_lab_but_gets_no_start_button(): void
    {
        $this->labPage($this->student, self::IPHONE)
            ->assertOk()
            ->assertSee('Write a loop.')
            ->assertSee('Open on a computer')
            ->assertSee("can't start this one on a phone or tablet")
            ->assertDontSee('id="start-lab-form"', false)
            ->assertDontSee('Start lab');
    }

    public function test_a_student_on_a_phone_cannot_continue_or_retake(): void
    {
        $session = LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->labPage($this->student, self::ANDROID_PHONE)
            ->assertSee("can't continue this one")
            ->assertDontSee('id="start-lab-form"', false);

        // A retake needs an attempt left over
        $this->lab->update(['max_attempts' => 3]);
        $session->update(['status' => 'completed', 'completed_at' => now()]);
        $this->labPage($this->student, self::ANDROID_PHONE)
            ->assertSee("can't retake this one")
            ->assertDontSee('id="start-lab-form"', false)
            ->assertDontSee('Retake lab');
    }

    public function test_a_student_on_a_computer_still_gets_the_start_button(): void
    {
        $this->labPage($this->student, self::DESKTOP)
            ->assertOk()
            ->assertSee('id="start-lab-form"', false)
            ->assertSee('Start lab');
    }

    public function test_the_module_page_offers_only_view_lab_on_a_phone(): void
    {
        $this->modulePage($this->student, self::IPHONE)->assertOk()->assertSee('View lab')->assertDontSee('Start lab');
        $this->modulePage($this->student, self::DESKTOP)->assertSee('Start lab');

        LabSession::create(['lab_id' => $this->lab->id, 'user_id' => $this->student->id, 'status' => 'in_progress', 'started_at' => now()]);
        $this->modulePage($this->student, self::IPHONE)->assertSee('View lab')->assertDontSee('Continue lab');
        $this->modulePage($this->student, self::DESKTOP)->assertSee('Continue lab');
    }

    public function test_the_server_refuses_to_start_a_lab_from_a_phone(): void
    {
        $this->startWithCameraCheck(self::IPHONE)->assertStatus(403)->assertJson(['error' => 'handheld_device']);
        $this->assertSame(0, LabSession::count());

        $this->actingAs($this->student)->withHeader('User-Agent', self::ANDROID_TABLET)
            ->from("/laboratories/{$this->lab->id}")
            ->post(route('laboratories.start', $this->lab->id))
            ->assertRedirect("/laboratories/{$this->lab->id}")
            ->assertSessionHas('error');
        $this->assertSame(0, LabSession::count());
    }

    public function test_the_same_start_from_a_computer_opens_the_lab(): void
    {
        $this->startWithCameraCheck(self::DESKTOP)->assertSuccessful();
        $this->assertSame(1, LabSession::count());
    }

    public function test_staff_pages_are_unchanged_on_a_phone(): void
    {
        $this->labPage($this->instructor, self::IPHONE)->assertOk()->assertSee('Open monitoring')->assertDontSee('Open on a computer');
        $this->modulePage($this->instructor, self::IPHONE)->assertOk()->assertSee('View lab');
    }
}
