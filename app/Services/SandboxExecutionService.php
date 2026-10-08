<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class SandboxExecutionService
{
    /**
     * Execute student code submissions.
     *
     * Detects if Docker is available and secure.
     * Falls back to a local isolated sub-process with resource restrictions and timeouts.
     *
     * @param string $code
     * @param string $language
     * @return array
     */
    public function executeCodeMock(string $code, string $language): array
    {
        return $this->execute($code, $language);
    }

    /**
     * Execute the code securely.
     */
    public function execute(string $code, string $language): array
    {
        $language = strtolower(trim($language));
        $startTime = microtime(true);

        // Serverless hosts (Vercel) have no Docker or JDK: run code on a Judge0 server when one is configured.
        if ($this->isRemoteExecutionConfigured()) {
            return $this->executeRemotely($code, $language, $startTime);
        }

        if ($language === 'java') {
            return $this->executeJava($code, $startTime);
        }

        if ($this->isDockerAvailable()) {
            return $this->executeInDocker($code, $language, $startTime);
        }

        return $this->executeLocally($code, $language, $startTime);
    }

    /**
     * Judge0 language ids (Judge0 CE): https://ce.judge0.com/languages
     */
    private const JUDGE0_LANGUAGES = [
        'java' => 62,
        'python' => 71,
        'javascript' => 63,
        'nodejs' => 63,
        'php' => 68,
        'bash' => 46,
        'shell' => 46,
        'sh' => 46,
        'c' => 50,
        'cpp' => 54,
    ];

    public function isRemoteExecutionConfigured(): bool
    {
        return !empty(config('services.judge0.url'));
    }

    /**
     * Run code on a Judge0 server (self-hosted, or hosted via RapidAPI when JUDGE0_KEY is set).
     */
    protected function executeRemotely(string $code, string $language, float $startTime): array
    {
        $languageId = self::JUDGE0_LANGUAGES[$language] ?? null;
        if ($languageId === null) {
            return $this->executionResult('', "Language '{$language}' is not supported for execution.", $startTime, 'error');
        }

        if ($language === 'java') {
            $code = $this->prepareJavaForJudge0($code);
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($key = config('services.judge0.key')) {
            if ($host = config('services.judge0.rapidapi_host')) {
                $headers['X-RapidAPI-Key'] = $key;
                $headers['X-RapidAPI-Host'] = $host;
            } else {
                $headers['X-Auth-Token'] = $key;
            }
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders($headers)
                ->timeout(25)
                ->post(rtrim(config('services.judge0.url'), '/') . '/submissions?base64_encoded=true&wait=true', [
                    'source_code' => base64_encode($code),
                    'language_id' => $languageId,
                    'cpu_time_limit' => 5,
                    'wall_time_limit' => 10,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Judge0 request failed: ' . $e->getMessage());
            return $this->executionResult('', 'The code execution service is unreachable right now. Please try again.', $startTime, 'unavailable');
        }

        if (!$response->successful()) {
            Log::warning('Judge0 returned HTTP ' . $response->status() . ': ' . $response->body());
            return $this->executionResult('', 'The code execution service returned an error. Please try again.', $startTime, 'unavailable');
        }

        $decode = fn ($value) => $value === null ? '' : (string) base64_decode($value);
        $data = $response->json();
        $statusId = (int) ($data['status']['id'] ?? 0);
        $stdout = $decode($data['stdout'] ?? null);

        // 3 = Accepted; 5 = Time Limit Exceeded; 6 = Compilation Error; 7-12 = runtime errors; 13+ = server-side errors
        return match (true) {
            $statusId === 3 => $this->executionResult($stdout, null, $startTime, 'success'),
            $statusId === 5 => $this->executionResult($stdout, 'Execution Timed Out (Maximum execution limit of 5.0 seconds reached).', $startTime, 'error'),
            $statusId === 6 => $this->executionResult('', ($language === 'java' ? "Java Compilation Error:\n" : "Compilation Error:\n") . $decode($data['compile_output'] ?? null), $startTime, 'error'),
            $statusId >= 7 && $statusId <= 12 => $this->executionResult($stdout, $decode($data['stderr'] ?? null) ?: ($data['status']['description'] ?? 'Runtime error'), $startTime, 'error'),
            default => $this->executionResult('', 'The code execution service could not run this submission (' . ($data['status']['description'] ?? 'unknown status') . ').', $startTime, 'unavailable'),
        };
    }

    /**
     * Judge0 compiles Java as Main.java. Drop `public` from top-level types (a public class must
     * match its file name) and, if there is no Main class, add one that calls the student's main().
     */
    protected function prepareJavaForJudge0(string $code): string
    {
        $code = preg_replace('/^public\s+((?:final\s+|abstract\s+|sealed\s+|non-sealed\s+)*(?:class|interface|enum|record)\s)/m', '$1', $code);

        if (preg_match('/^(?:final\s+|abstract\s+)*class\s+Main\b/m', $code)) {
            return $code;
        }

        // Find the top-level (unindented) class that declares main(String[] ...), if any
        $entryClass = null;
        if (preg_match_all('/^(?:final\s+|abstract\s+)*class\s+(\w+)/m', $code, $classes, PREG_OFFSET_CAPTURE)
            && preg_match('/static\s+void\s+main\s*\(\s*(?:final\s+)?String\s*(?:\[\]\s*\w+|\.\.\.\s*\w+|\w+\s*\[\])\s*\)/', $code, $main, PREG_OFFSET_CAPTURE)) {
            foreach ($classes[1] as [$name, $offset]) {
                if ($offset < $main[0][1]) {
                    $entryClass = $name;
                }
            }
        }

        $body = $entryClass ? "{$entryClass}.main(args);" : '';
        return rtrim($code) . "\n\nclass Main {\n    public static void main(String[] args) throws Exception {\n        {$body}\n    }\n}\n";
    }

    private function executionResult(string $output, ?string $errors, float $startTime, string $status): array
    {
        return [
            'output' => $output,
            'errors' => $errors,
            'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            'status' => $status,
        ];
    }

    /**
     * Check if Docker is available on the system.
     */
    public function isDockerAvailable(): bool
    {
        if (app()->runningUnitTests() && !env('TEST_DOCKER_SANDBOX')) {
            return false;
        }

        try {
            $process = new Process(['docker', 'info']);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get docker image and interpreter configuration for each language.
     */
    private function getDockerConfig(string $language): ?array
    {
        $configs = [
            'python' => [
                'image' => 'python:3.10-slim',
                'command' => 'python',
                'extension' => 'py'
            ],
            'javascript' => [
                'image' => 'node:18-slim',
                'command' => 'node',
                'extension' => 'js'
            ],
            'nodejs' => [
                'image' => 'node:18-slim',
                'command' => 'node',
                'extension' => 'js'
            ],
            'php' => [
                'image' => 'php:8.2-cli',
                'command' => 'php',
                'extension' => 'php'
            ],
            'bash' => [
                'image' => 'bash:5.2',
                'command' => 'bash',
                'extension' => 'sh'
            ],
            'shell' => [
                'image' => 'bash:5.2',
                'command' => 'bash',
                'extension' => 'sh'
            ],
            'sh' => [
                'image' => 'bash:5.2',
                'command' => 'bash',
                'extension' => 'sh'
            ]
        ];

        return $configs[$language] ?? null;
    }

    /**
     * Get host local executable configuration for each language.
     */
    private function getLocalConfig(string $language): ?array
    {
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;

        $configs = [
            'python' => [
                'executables' => $isWindows ? ['python.exe', 'py.exe', 'python3.exe'] : ['python3', 'python'],
                'extension' => 'py'
            ],
            'javascript' => [
                'executables' => ['node'],
                'extension' => 'js'
            ],
            'nodejs' => [
                'executables' => ['node'],
                'extension' => 'js'
            ],
            'php' => [
                'executables' => [PHP_BINARY, 'php'],
                'extension' => 'php'
            ],
            'bash' => [
                'executables' => $isWindows ? ['bash.exe', 'sh.exe', 'cmd.exe', 'powershell.exe'] : ['bash', 'sh'],
                'extension' => 'sh'
            ],
            'shell' => [
                'executables' => $isWindows ? ['bash.exe', 'sh.exe', 'cmd.exe', 'powershell.exe'] : ['bash', 'sh'],
                'extension' => 'sh'
            ],
            'sh' => [
                'executables' => $isWindows ? ['bash.exe', 'sh.exe', 'cmd.exe', 'powershell.exe'] : ['bash', 'sh'],
                'extension' => 'sh'
            ]
        ];

        return $configs[$language] ?? null;
    }

    /**
     * Execute student code securely in a Docker container.
     */
    private function executeInDocker(string $code, string $language, float $startTime): array
    {
        $config = $this->getDockerConfig($language);
        if (!$config) {
            return [
                'output' => '',
                'errors' => "Unsupported language for Docker sandbox: {$language}",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        }

        $tempDir = sys_get_temp_dir();
        $fileName = 'certicode_' . uniqid() . '.' . $config['extension'];
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . $fileName;

        file_put_contents($tempFile, $code);

        try {
            $containerPath = '/app/' . $fileName;
            $mountPath = str_replace('\\', '/', $tempFile);
            
            $dockerCommand = [
                'docker', 'run', '--rm',
                '--network', 'none',
                '--memory', '128m',
                '--cpus', '0.5',
                '--read-only',
                '--tmpfs', '/tmp',
                '-v', "{$mountPath}:{$containerPath}:ro",
                $config['image'],
                $config['command'], $containerPath
            ];

            $process = new Process($dockerCommand);
            $process->setTimeout(5.0); // 5 seconds execution limit
            
            $process->run();

            $executionTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($process->isSuccessful()) {
                return [
                    'output' => $process->getOutput(),
                    'errors' => null,
                    'execution_time_ms' => $executionTime,
                    'status' => 'success'
                ];
            } else {
                $errorOutput = $process->getErrorOutput();
                if (empty($errorOutput)) {
                    $errorOutput = $process->getOutput();
                }
                
                if ($process->getExitCode() === null) {
                    $errorOutput = "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).";
                }

                return [
                    'output' => $process->getOutput(),
                    'errors' => $errorOutput ?: 'Unknown error occurred.',
                    'execution_time_ms' => $executionTime,
                    'status' => 'error'
                ];
            }
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            return [
                'output' => '',
                'errors' => "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } catch (\Exception $e) {
            return [
                'output' => '',
                'errors' => "Docker sandbox execution failed: " . $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Fallback local sandbox execution with strict timeouts and path resolution.
     */
    private function executeLocally(string $code, string $language, float $startTime): array
    {
        $config = $this->getLocalConfig($language);
        if (!$config) {
            return [
                'output' => '',
                'errors' => "Unsupported language for local runner: {$language}",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        }

        $executable = $this->resolveLocalExecutable($config['executables']);
        if (!$executable) {
            return [
                'output' => '',
                'errors' => "Could not locate interpreter executable for language: {$language}. Ensure it is installed and on the system PATH.",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        }

        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'certicode_' . uniqid() . '.' . $config['extension'];
        
        // Build the command and prepare the file based on the interpreter resolved
        $resolvedName = basename(strtolower($executable));
        if ($resolvedName === 'cmd.exe' || $resolvedName === 'cmd') {
            $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'certicode_' . uniqid() . '.bat';
            file_put_contents($tempFile, "@echo off\r\n" . $code);
            $command = [$executable, '/c', $tempFile];
        } elseif ($resolvedName === 'powershell.exe' || $resolvedName === 'powershell') {
            $tempFile = $tempDir . DIRECTORY_SEPARATOR . 'certicode_' . uniqid() . '.ps1';
            file_put_contents($tempFile, $code);
            $command = [$executable, '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $tempFile];
        } else {
            file_put_contents($tempFile, $code);
            $command = [$executable, $tempFile];
        }

        try {
            $process = new Process($command);
            $process->setTimeout(5.0); // 5 seconds limit
            
            $process->run();

            $executionTime = (int) ((microtime(true) - $startTime) * 1000);

            if ($process->isSuccessful()) {
                return [
                    'output' => $process->getOutput(),
                    'errors' => null,
                    'execution_time_ms' => $executionTime,
                    'status' => 'success'
                ];
            } else {
                $errorOutput = $process->getErrorOutput();
                if (empty($errorOutput)) {
                    $errorOutput = $process->getOutput();
                }

                if ($process->getExitCode() === null) {
                    $errorOutput = "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).";
                }

                return [
                    'output' => $process->getOutput(),
                    'errors' => $errorOutput ?: 'Unknown local execution error.',
                    'execution_time_ms' => $executionTime,
                    'status' => 'error'
                ];
            }
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            return [
                'output' => '',
                'errors' => "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } catch (\Exception $e) {
            return [
                'output' => '',
                'errors' => "Local execution process crashed: " . $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function resolveLocalExecutable(array $names): ?string
    {
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;

        foreach ($names as $name) {
            $resolved = null;
            if (file_exists($name) && is_executable($name)) {
                $resolved = $name;
            } else {
                $command = $isWindows ? "where.exe {$name}" : "which {$name}";
                $output = [];
                $code = 1;
                
                try {
                    @exec($command, $output, $code);
                    if ($code === 0 && isset($output[0]) && !empty(trim($output[0]))) {
                        $candidate = trim($output[0]);
                        if (file_exists($candidate)) {
                            $resolved = $candidate;
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }

            if ($resolved) {
                // If it is a bash/sh shell interpreter, double-check that it is working 
                // (e.g. to avoid broken/unconfigured WSL bash on Windows)
                $base = basename(strtolower($resolved));
                if (in_array($base, ['bash.exe', 'bash', 'sh.exe', 'sh'])) {
                    try {
                        $proc = new Process([$resolved, '-c', 'echo 1']);
                        $proc->run();
                        if (!$proc->isSuccessful()) {
                            continue; // Skip this interpreter if it fails to execute simple commands
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                
                return $resolved;
            }
        }

        return null;
    }

    /**
     * Compiles and executes Java code.
     */
    protected function executeJava(string $code, float $startTime): array
    {
        $className = 'Main';
        if (preg_match('/public\s+class\s+(\w+)/', $code, $matches)) {
            $className = $matches[1];
        }

        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'certicode_java_' . uniqid();
        if (!mkdir($tempDir)) {
            return [
                'output' => '',
                'errors' => 'Failed to create temporary directory for Java execution.',
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        }

        $tempFile = $tempDir . DIRECTORY_SEPARATOR . $className . '.java';
        file_put_contents($tempFile, $code);

        try {
            if ($this->isDockerAvailable()) {
                // Docker compilation and execution
                $mountPath = str_replace('\\', '/', $tempDir);
                
                // Compile in docker
                $compileProcess = new Process([
                    'docker', 'run', '--rm',
                    '-v', "{$mountPath}:/app",
                    '-w', '/app',
                    'openjdk:17-slim',
                    'javac', "{$className}.java"
                ]);
                $compileProcess->run();

                if (!$compileProcess->isSuccessful()) {
                    $errors = $compileProcess->getErrorOutput() ?: $compileProcess->getOutput();
                    return [
                        'output' => '',
                        'errors' => "Java Compilation Error:\n" . $errors,
                        'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                        'status' => 'error'
                    ];
                }

                // Run in docker
                $runProcess = new Process([
                    'docker', 'run', '--rm',
                    '--network', 'none',
                    '--memory', '128m',
                    '--cpus', '0.5',
                    '-v', "{$mountPath}:/app",
                    '-w', '/app',
                    'openjdk:17-slim',
                    'java', $className
                ]);
                $runProcess->setTimeout(5.0);
                $runProcess->run();

                $executionTime = (int) ((microtime(true) - $startTime) * 1000);

                if ($runProcess->isSuccessful()) {
                    return [
                        'output' => $runProcess->getOutput(),
                        'errors' => null,
                        'execution_time_ms' => $executionTime,
                        'status' => 'success'
                    ];
                } else {
                    $errors = $runProcess->getErrorOutput() ?: $runProcess->getOutput();
                    if ($runProcess->getExitCode() === null) {
                        $errors = "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).";
                    }
                    return [
                        'output' => $runProcess->getOutput(),
                        'errors' => $errors,
                        'execution_time_ms' => $executionTime,
                        'status' => 'error'
                    ];
                }
            } else {
                // Local compilation and execution
                $javac = $this->resolveLocalExecutable(['javac']);
                $java = $this->resolveLocalExecutable(['java']);
                if (!$javac || !$java) {
                    // e.g. Vercel: no Docker and no JDK. Say so instead of reporting a fake compile error.
                    return $this->executionResult('', 'Code execution is not set up on this server, so your code was not compiled. Your submission is still graded.', $startTime, 'unavailable');
                }

                $compileProcess = new Process([$javac, "{$className}.java"], $tempDir);
                $compileProcess->run();

                if (!$compileProcess->isSuccessful()) {
                    $errors = $compileProcess->getErrorOutput() ?: $compileProcess->getOutput();
                    return [
                        'output' => '',
                        'errors' => "Java Compilation Error:\n" . $errors,
                        'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                        'status' => 'error'
                    ];
                }

                $runProcess = new Process([$java, $className], $tempDir);
                $runProcess->setTimeout(5.0);
                $runProcess->run();

                $executionTime = (int) ((microtime(true) - $startTime) * 1000);

                if ($runProcess->isSuccessful()) {
                    return [
                        'output' => $runProcess->getOutput(),
                        'errors' => null,
                        'execution_time_ms' => $executionTime,
                        'status' => 'success'
                    ];
                } else {
                    $errors = $runProcess->getErrorOutput() ?: $runProcess->getOutput();
                    if ($runProcess->getExitCode() === null) {
                        $errors = "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).";
                    }
                    return [
                        'output' => $runProcess->getOutput(),
                        'errors' => $errors,
                        'execution_time_ms' => $executionTime,
                        'status' => 'error'
                    ];
                }
            }
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            return [
                'output' => '',
                'errors' => "Execution Timed Out (Maximum execution limit of 5.0 seconds reached).",
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } catch (\Throwable $e) {
            return [
                'output' => '',
                'errors' => "Java execution failed: " . $e->getMessage(),
                'execution_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error'
            ];
        } finally {
            $this->cleanupDir($tempDir);
        }
    }

    /**
     * Recursively delete a directory and its contents.
     */
    protected function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->cleanupDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

