<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Several pages cache by record id in the file store, which outlives each test's
        // database. Give tests their own cache folder and start every test with it empty.
        config(['cache.stores.file.path' => storage_path('framework/testing/cache')]);
        Cache::forgetDriver('file');
        Cache::store('file')->flush();
    }
}
