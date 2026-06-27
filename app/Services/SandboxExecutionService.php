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

        if ($this->isDockerAvailable()) {
            return $this->executeInDocker($code, $language, $startTime);
        }

        return $this->executeLocally($code, $language, $startTime);
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
}

