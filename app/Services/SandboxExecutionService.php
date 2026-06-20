<?php

namespace App\Services;

class SandboxExecutionService
{
    /**
     * Execute student code submissions (Mock Sandbox implementation).
     *
     * In Phase 2, this will be replaced with actual Docker container execution.
     *
     * @param string $code
     * @param string $language
     * @return array
     */
    public function executeCodeMock(string $code, string $language): array
    {
        // Simple mock sandbox logic
        return [
            'output' => "Hello, World! (Executed via Mock Sandbox in Certicode Labs)\nLanguage: " . ucfirst($language) . "\n-------------------------\nCode received:\n" . $code,
            'errors' => null,
            'execution_time_ms' => rand(100, 200),
            'status' => 'success'
        ];
    }
}
