<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Admin → System: runtime settings, plus a read-only view of which services are configured.
 * Never shows secret values, only whether they are set.
 */
class SystemSettingsController extends Controller
{
    public function edit()
    {
        $settings = [];
        foreach (array_keys(SystemSetting::DEFINITIONS) as $key) {
            $settings[$key] = SystemSetting::read($key);
        }

        $services = [
            ['AI grading', filled(config('services.anthropic.key')), filled(config('services.anthropic.key'))
                ? 'Claude, model '.config('services.anthropic.grader_model', 'default')
                : 'No API key; the rule-based grader is used'],
            ['Code execution', filled(config('services.judge0.url')), filled(config('services.judge0.url'))
                ? 'Judge0 server configured'
                : 'No Judge0 server; code is graded without running it'],
            ['Realtime updates', ! in_array(config('broadcasting.default'), [null, '', 'log', 'null'], true),
                'Driver: '.(config('broadcasting.default') ?: 'none').(in_array(config('broadcasting.default'), ['log', 'null', null, ''], true) ? '; pages poll instead' : '')],
            ['Email', ! in_array(config('mail.default'), ['log', 'array', null, ''], true),
                'Mailer: '.(config('mail.default') ?: 'none').(in_array(config('mail.default'), ['log', 'array'], true) ? '; emails are not delivered' : '')],
            ['Google sign-in', filled(config('services.google.client_id')), filled(config('services.google.client_id'))
                ? 'OAuth configured' : 'Uses the emailed-code fallback'],
            ['GitHub sign-in', filled(config('services.github.client_id')), filled(config('services.github.client_id'))
                ? 'OAuth configured' : 'Not available in production'],
        ];

        $environment = [
            'Environment' => app()->environment(),
            'Debug mode' => config('app.debug') ? 'On' : 'Off',
            'Database' => config('database.default'),
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
        ];

        return view('admin.system.edit', compact('settings', 'services', 'environment'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'default_pass_mark' => 'required|integer|min:1|max:100',
            'default_lab_minutes' => 'required|integer|min:5|max:300',
            'announcement' => 'nullable|string|max:300',
        ]);

        SystemSetting::write([
            'registration_open' => $request->boolean('registration_open'),
            'instructor_requests_open' => $request->boolean('instructor_requests_open'),
            'default_pass_mark' => $validated['default_pass_mark'],
            'default_lab_minutes' => $validated['default_lab_minutes'],
            'announcement' => trim((string) ($validated['announcement'] ?? '')),
        ]);

        return back()->with('success', 'System settings saved.');
    }
}
