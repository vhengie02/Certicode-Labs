<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
    ],

    // AI grading with Claude. Without a key the rule-based mock grader is used.
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'grader_model' => env('ANTHROPIC_GRADER_MODEL', 'claude-opus-5-5'),
        'grader_effort' => env('ANTHROPIC_GRADER_EFFORT', 'medium'), // low | medium | high | xhigh | max
    ],

    // Remote code execution (Judge0). Needed where Docker/JDK are unavailable, e.g. Vercel.
    // Self-hosted: JUDGE0_URL (+ optional JUDGE0_KEY sent as X-Auth-Token).
    // RapidAPI: JUDGE0_URL=https://judge0-ce.p.rapidapi.com, JUDGE0_KEY, JUDGE0_RAPIDAPI_HOST=judge0-ce.p.rapidapi.com
    'judge0' => [
        'url' => env('JUDGE0_URL'),
        'key' => env('JUDGE0_KEY'),
        'rapidapi_host' => env('JUDGE0_RAPIDAPI_HOST'),
    ],

    // Without Judge0, student code would run on this server itself (Docker, or the host's
    // php/python/node/javac) with the app's environment, database and API secrets included.
    // Unset: allowed only in local and testing. SANDBOX_HOST_EXECUTION=false forbids it everywhere;
    // =true allows it anywhere, which is only safe on a machine with nothing worth stealing.
    'sandbox' => [
        'host_execution' => env('SANDBOX_HOST_EXECUTION'),
    ],

    // Certicode VS Code extension. Builds older than min_version (or old builds that send
    // no version) are flagged as outdated to the student. Bump it with every release that
    // the web app depends on.
    'vscode_extension' => [
        // Older builds get a warning to update
        'min_version' => env('VSCODE_EXTENSION_MIN_VERSION', '1.6.0'),
        // Older builds are refused with 426 (RequireSupportedExtension); keep at or below min_version
        'required_version' => env('VSCODE_EXTENSION_REQUIRED_VERSION', '1.5.0'),
        'download_path' => 'downloads/certicode-labs.vsix',
    ],

];
