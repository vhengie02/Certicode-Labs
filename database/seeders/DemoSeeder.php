<?php

namespace Database\Seeders;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use App\Models\Anomaly;
use App\Models\TelemetryLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed a realistic, ready-to-test CertiCode Labs environment.
     */
    public function run(): void
    {
        $this->command->info("⚡ Seeding CertiCode Labs instant demo environment...");

        // 1. Seed Instructor
        $instructor = User::updateOrCreate(
            ['email' => 'instructor@certicode.test'],
            [
                'name' => 'Prof. Victor Heng',
                'first_name' => 'Victor',
                'last_name' => 'Heng',
                'username' => 'prof_heng',
                'password' => Hash::make('password'),
                'role' => 'instructor',
                'gmail' => 'instructor@certicode.test',
                'gmail_verified_at' => now(),
            ]
        );

        // 2. Seed Primary Testing Student
        $student1 = User::updateOrCreate(
            ['email' => 'student@certicode.test'],
            [
                'name' => 'Alex Mercer',
                'first_name' => 'Alex',
                'last_name' => 'Mercer',
                'username' => 'alex_mercer',
                'password' => Hash::make('password'),
                'role' => 'student',
                'github_username' => 'alex-mercer',
                'gmail' => 'student@certicode.test',
                'gmail_verified_at' => now(),
            ]
        );

        // Seed Peer Students for Live Leaderboard & Plagiarism Demo
        $student2 = User::updateOrCreate(
            ['email' => 'sarah@certicode.test'],
            [
                'name' => 'Sarah Connor',
                'first_name' => 'Sarah',
                'last_name' => 'Connor',
                'username' => 'sarah_c',
                'password' => Hash::make('password'),
                'role' => 'student',
            ]
        );

        $student3 = User::updateOrCreate(
            ['email' => 'bruce@certicode.test'],
            [
                'name' => 'Bruce Wayne',
                'first_name' => 'Bruce',
                'last_name' => 'Wayne',
                'username' => 'bruce_w',
                'password' => Hash::make('password'),
                'role' => 'student',
            ]
        );

        $student4 = User::updateOrCreate(
            ['email' => 'clark@certicode.test'],
            [
                'name' => 'Clark Kent',
                'first_name' => 'Clark',
                'last_name' => 'Kent',
                'username' => 'clark_k',
                'password' => Hash::make('password'),
                'role' => 'student',
            ]
        );

        // 3. Seed Class
        $class = SchoolClass::updateOrCreate(
            ['code' => 'CS202'],
            [
                'name' => 'CS202: Advanced Algorithms & Data Structures',
                'description' => 'Hands-on algorithm implementations, asymptotic analysis, and secure coding practices.',
                'instructor_id' => $instructor->id,
                'passing_threshold' => 75,
                'status' => 'active',
            ]
        );

        // Enroll all students
        $class->students()->syncWithoutDetaching([
            $student1->id => ['status' => 'enrolled'],
            $student2->id => ['status' => 'enrolled'],
            $student3->id => ['status' => 'enrolled'],
            $student4->id => ['status' => 'enrolled'],
        ]);

        // 4. Seed Module
        $module = Module::updateOrCreate(
            [
                'class_id' => $class->id,
                'title' => 'Module 1: Divide & Conquer Algorithms',
            ],
            [
                'description' => 'Master recursive partitioning, Quicksort, Mergesort, and Binary Search Trees.',
                'content' => 'Review divide-and-conquer principles before initiating the in-lab coding challenge.',
                'order_index' => 1,
            ]
        );

        // 5. Seed Starter Files Manifest
        $starterFilesLiveLab = [
            [
                'name' => 'QuickSort.php',
                'is_primary' => true,
                'is_readonly' => false,
                'content' => <<<'PHP'
<?php

class QuickSort
{
    /**
     * Sorts an integer array in ascending order using divide-and-conquer.
     *
     * @param int[] $arr
     * @return int[]
     */
    public function sort(array $arr): array
    {
        // TODO: Task 1 - Base condition for arrays of 0 or 1 elements
        if (count($arr) <= 1) {
            return $arr;
        }

        // TODO: Task 2 - Partition array around a pivot element
        $pivot = $arr[0];
        $less = [];
        $greater = [];

        for ($i = 1; $i < count($arr); $i++) {
            if ($arr[$i] <= $pivot) {
                $less[] = $arr[$i];
            } else {
                $greater[] = $arr[$i];
            }
        }

        // TODO: Task 3 - Recursively sort sub-arrays and concatenate
        return array_merge($this->sort($less), [$pivot], $this->sort($greater));
    }
}
PHP
            ],
            [
                'name' => 'test_runner.php',
                'is_primary' => false,
                'is_readonly' => true,
                'content' => <<<'PHP'
<?php

require_once __DIR__ . '/QuickSort.php';

$sorter = new QuickSort();
$sample = [38, 27, 43, 3, 9, 82, 10];
$sorted = $sorter->sort($sample);

echo "Sorted Output: " . implode(', ', $sorted) . "\n";
if ($sorted === [3, 9, 10, 27, 38, 43, 82]) {
    echo "VERIFICATION_SUCCESS\n";
} else {
    echo "VERIFICATION_FAILED\n";
}
PHP
            ]
        ];

        // 6. Seed Lab 1: Live Lab (Synchronized Countdown)
        $liveLab = Laboratory::updateOrCreate(
            [
                'module_id' => $module->id,
                'title' => 'Live Exam: Quicksort & Recursive Partitioning (Live Lab)',
            ],
            [
                'description' => "Synchronized Live Lab Exam on Quicksort.\n\nAll students share the same 60-minute countdown window. Starter files are verified in real time by the VS Code extension.\n\nInstructions:\n1. Implement base case in `QuickSort.php`\n2. Partition elements relative to pivot\n3. Recursively conquer partitions and merge.",
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Implement base condition: return array if count <= 1', 'command' => 'regex:if\s*\(\s*count\s*\(\s*\$arr\s*\)\s*<=\s*1\s*\)'],
                    ['id' => 2, 'task' => 'Select pivot and partition elements into $less and $greater arrays', 'command' => 'regex:\$pivot\s*=|\$less\s*\[\]|\$greater\s*\[\]'],
                    ['id' => 3, 'task' => 'Recursively sort sub-arrays and merge with array_merge', 'command' => 'regex:array_merge\s*\(\s*\$this->sort'],
                ],
                'starter_files' => $starterFilesLiveLab,
                'availability_mode' => 'live',
                'live_status' => 'active',
                'live_duration_minutes' => 60,
                'live_started_at' => now()->subMinutes(10), // Started 10 minutes ago, 50m left
                'live_elapsed_seconds' => 600,
                'time_limit' => 60,
                'is_group_lab' => false,
            ]
        );

        // 7. Seed Lab 2: Open Lab (Self-Paced Practice)
        $openLab = Laboratory::updateOrCreate(
            [
                'module_id' => $module->id,
                'title' => 'Self-Paced Lab: Binary Search Tree Search (Open Lab)',
            ],
            [
                'description' => "Self-paced Open Lab on Binary Search Trees.\n\nTimer starts individually when each student clicks Start Lab. Available anytime during the course term.",
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Define BinaryNode class with left, right, and value properties', 'command' => 'regex:class\s+BinaryNode'],
                    ['id' => 2, 'task' => 'Implement search method with O(log n) average time complexity', 'command' => 'regex:function\s+search'],
                ],
                'starter_files' => [
                    [
                        'name' => 'BST.php',
                        'is_primary' => true,
                        'is_readonly' => false,
                        'content' => "<?php\n\nclass BST {\n    // TODO: Implement binary search\n}\n",
                    ]
                ],
                'availability_mode' => 'open',
                'time_limit' => 45,
                'is_group_lab' => false,
            ]
        );

        // 8. Seed Active Lab Session for Primary Student (Ready to Connect in VS Code!)
        $primarySession = LabSession::updateOrCreate(
            [
                'lab_id' => $liveLab->id,
                'user_id' => $student1->id,
            ],
            [
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(10),
                'performance_score' => 66.7,
                'completed_tasks' => [1, 2],
                'wpm' => 54,
                'keystroke_count' => 840,
                'focus_lost_count' => 1,
                'paste_anomaly_count' => 0,
                'submitted_code' => $starterFilesLiveLab[0]['content'],
                'diff_stats' => ['added' => 14, 'deleted' => 2],
            ]
        );

        // 9. Seed Student 2 (Completed Session with 100% Score)
        LabSession::updateOrCreate(
            [
                'lab_id' => $liveLab->id,
                'user_id' => $student2->id,
            ],
            [
                'status' => 'completed',
                'started_at' => now()->subMinutes(35),
                'ended_at' => now()->subMinutes(5),
                'performance_score' => 100.0,
                'completed_tasks' => [1, 2, 3],
                'wpm' => 72,
                'keystroke_count' => 1240,
                'focus_lost_count' => 0,
                'paste_anomaly_count' => 0,
                'submitted_code' => <<<'PHP'
<?php

class QuickSort {
    public function sort(array $arr): array {
        if (count($arr) <= 1) return $arr;
        $pivot = $arr[0];
        $left = [];
        $right = [];
        for ($i = 1; $i < count($arr); $i++) {
            if ($arr[$i] < $pivot) $left[] = $arr[$i];
            else $right[] = $arr[$i];
        }
        return array_merge($this->sort($left), [$pivot], $this->sort($right));
    }
}
PHP,
                'diff_stats' => ['added' => 20, 'deleted' => 4],
            ]
        );

        // 10. Seed Student 3 & Student 4 with Copied Code (To showcase Cohort Plagiarism Detector!)
        $plagiarizedCodeOriginal = <<<'PHP'
<?php

class QuickSort {
    public function sort(array $arr): array {
        if (count($arr) <= 1) {
            return $arr;
        }
        $pivotElement = $arr[0];
        $smallerElements = [];
        $largerElements = [];
        foreach (array_slice($arr, 1) as $item) {
            if ($item <= $pivotElement) {
                $smallerElements[] = $item;
            } else {
                $largerElements[] = $item;
            }
        }
        return array_merge($this->sort($smallerElements), [$pivotElement], $this->sort($largerElements));
    }
}
PHP;

        $plagiarizedCodeCopied = <<<'PHP'
<?php

/* Renamed variables and slight whitespace rearrangement */
class QuickSort {
    public function sort(array $data): array {
        if (count($data) <= 1) {
            return $data;
        }
        $pivotVal = $data[0];
        $lessBucket = [];
        $moreBucket = [];
        foreach (array_slice($data, 1) as $val) {
            if ($val <= $pivotVal) {
                $lessBucket[] = $val;
            } else {
                $moreBucket[] = $val;
            }
        }
        return array_merge($this->sort($lessBucket), [$pivotVal], $this->sort($moreBucket));
    }
}
PHP;

        LabSession::updateOrCreate(
            [
                'lab_id' => $liveLab->id,
                'user_id' => $student3->id,
            ],
            [
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(12),
                'performance_score' => 100.0,
                'completed_tasks' => [1, 2, 3],
                'wpm' => 88,
                'keystroke_count' => 950,
                'focus_lost_count' => 2,
                'paste_anomaly_count' => 1,
                'submitted_code' => $plagiarizedCodeOriginal,
                'diff_stats' => ['added' => 18, 'deleted' => 0],
            ]
        );

        LabSession::updateOrCreate(
            [
                'lab_id' => $liveLab->id,
                'user_id' => $student4->id,
            ],
            [
                'status' => 'in_progress',
                'started_at' => now()->subMinutes(11),
                'performance_score' => 100.0,
                'completed_tasks' => [1, 2, 3],
                'wpm' => 92,
                'keystroke_count' => 980,
                'focus_lost_count' => 3,
                'paste_anomaly_count' => 1,
                'submitted_code' => $plagiarizedCodeCopied,
                'diff_stats' => ['added' => 18, 'deleted' => 0],
            ]
        );

        // Seed Sample Proctoring Anomalies for Bruce & Clark
        Anomaly::updateOrCreate(
            [
                'lab_session_id' => $primarySession->id,
                'type' => 'focus_lost',
            ],
            [
                'severity' => 'low',
                'description' => 'VS Code lost OS-level focus for 8 seconds.',
            ]
        );

        $session3 = LabSession::where('lab_id', $liveLab->id)->where('user_id', $student3->id)->first();
        if ($session3) {
            Anomaly::updateOrCreate(
                [
                    'lab_session_id' => $session3->id,
                    'type' => 'paste_anomaly',
                ],
                [
                    'severity' => 'medium',
                    'description' => 'Multi-line block (16 lines) pasted without typing velocity ramp-up.',
                    'metadata' => [
                        'snippet' => 'public function sort(array $arr): array { ... }',
                        'length' => 450,
                    ]
                ]
            );
        }

        // Print Summary Table to CLI
        $this->command->newLine();
        $this->command->info("🎉 Instant Demo Environment Seeded Successfully!");
        $this->command->table(
            ['Role', 'Name', 'Email', 'Password', 'Notes'],
            [
                ['Instructor', 'Prof. Victor Heng', 'instructor@certicode.test', 'password', 'Can monitor Live Lab & inspect Plagiarism Matrix'],
                ['Student (Primary)', 'Alex Mercer', 'student@certicode.test', 'password', "Active Live Lab Session ID: #{$primarySession->id} (Ready for VS Code)"],
                ['Student (Peer)', 'Sarah Connor', 'sarah@certicode.test', 'password', 'Completed Live Lab (100% Score)'],
                ['Student (Peer)', 'Bruce Wayne', 'bruce@certicode.test', 'password', 'Active in Live Lab (Plagiarism flagged with Clark)'],
                ['Student (Peer)', 'Clark Kent', 'clark@certicode.test', 'password', 'Active in Live Lab (Plagiarism flagged with Bruce)'],
            ]
        );
        $this->command->newLine();
        $this->command->line("📍 <fg=cyan>Instructor Live Monitoring:</> <href=http://localhost:8000/laboratories/{$liveLab->id}/monitoring>http://localhost:8000/laboratories/{$liveLab->id}/monitoring</>");
        $this->command->line("📍 <fg=cyan>Active Live Lab Workspace:</> <href=http://localhost:8000/laboratories/{$liveLab->id}>http://localhost:8000/laboratories/{$liveLab->id}</>");
        $this->command->line("📍 <fg=cyan>VS Code Connect Session ID:</> <fg=yellow;options=bold>{$primarySession->id}</>");
        $this->command->newLine();
    }
}
