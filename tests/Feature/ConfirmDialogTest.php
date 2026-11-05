<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_class_list_asks_through_the_app_dialog(): void
    {
        $instructor = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('password'), 'role' => 'instructor']);
        SchoolClass::create(['name' => "Dan's <b>Class</b>", 'code' => 'DAN-101', 'instructor_id' => $instructor->id]);

        $response = $this->actingAs($instructor)->get(route('classes.index'));

        $response->assertOk();
        $response->assertSee('id="confirm-dialog"', false);
        $response->assertSee('data-confirm-action="Delete class"', false);

        // The name sits in an attribute, so it has to arrive escaped
        $response->assertSee('data-confirm-title="Delete “Dan&#039;s &lt;b&gt;Class&lt;/b&gt;”?"', false);
    }

    public function test_no_view_uses_the_browser_confirm_box(): void
    {
        foreach (File::allFiles(resource_path('views')) as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w.$>-])(?:window\.)?(?:confirm|alert|prompt)\(/',
                $view->getContents(),
                "{$view->getRelativePathname()} uses a browser dialog. Use data-confirm or window.confirmDialog()."
            );
        }
    }
}
