<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without built assets: CI and fresh checkouts have no public/build,
        // and a missing Vite manifest turns every page into a 500.
        $this->withoutVite();

        // Several pages cache by record id in the file store, which outlives each test's
        // database. Give tests their own cache folder and start every test with it empty.
        config(['cache.stores.file.path' => storage_path('framework/testing/cache')]);
        Cache::forgetDriver('file');
        Cache::store('file')->flush();
    }

    /**
     * Act as a browser session that just passed the pre-lab camera check for this lab.
     */
    protected function withCameraCheck(int $labId, bool $lowLight = false): static
    {
        return $this->withSession(["camera_verified_lab_{$labId}" => [
            'verified_at' => now()->toIso8601String(),
            'low_light' => $lowLight,
        ]]);
    }
}
