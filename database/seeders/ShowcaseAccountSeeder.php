<?php

namespace Database\Seeders;

use App\Models\Anomaly;
use App\Models\Certificate;
use App\Models\Competency;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Models\TelemetryLog;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShowcaseAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -------------------------------------------------------------
        // 1. Seed / Update Instructor (Dr. Jane Smith)
        // -------------------------------------------------------------
        $instructorAuthId = $this->getOrCreateAuthUserId('instructor@example.com', 'Dr. Jane Smith', 'instructor', 'password');

        $instructorData = [
            'name' => 'Dr. Jane Smith',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'username' => 'drjanesmith',
            'gender' => 'female',
            'password' => Hash::make('password'),
            'role' => 'instructor',
            'github_username' => 'janesmith-phd',
            'gmail' => 'instructor@example.com',
            'gmail_verified_at' => now(),
            'notify_class' => true,
            'notify_module' => true,
            'notify_lab' => true,
            'notify_certificate' => true,
            'notify_email_channel' => true,
        ];
        if ($instructorAuthId) {
            $instructorData['auth_user_id'] = $instructorAuthId;
        }

        $instructor = User::updateOrCreate(
            ['email' => 'instructor@example.com'],
            $instructorData
        );

        // -------------------------------------------------------------
        // 2. Seed Primary Showcase Student (Alex Mercer)
        // -------------------------------------------------------------
        $alexAuthId = $this->getOrCreateAuthUserId('alex.mercer@certicode.com', 'Alex Mercer', 'student', 'password');

        $alexData = [
            'name' => 'Alex Mercer',
            'first_name' => 'Alex',
            'last_name' => 'Mercer',
            'username' => 'alexmercer',
            'gender' => 'male',
            'password' => Hash::make('password'),
            'role' => 'student',
            'github_username' => 'alexmercer-dev',
            'gmail' => 'alexmercer.edu@gmail.com',
            'gmail_verified_at' => now(),
            'notify_class' => true,
            'notify_module' => true,
            'notify_lab' => true,
            'notify_certificate' => true,
            'notify_email_channel' => true,
        ];
        if ($alexAuthId) {
            $alexData['auth_user_id'] = $alexAuthId;
        }

        $showcaseStudent = User::updateOrCreate(
            ['email' => 'alex.mercer@certicode.com'],
            $alexData
        );

        // Also update existing student "ben@gmail.com" if present, so both accounts have everything
        $benStudent = User::where('email', 'ben@gmail.com')->first();
        if ($benStudent) {
            $benStudent->update([
                'name' => 'Ben Davis',
                'first_name' => 'Ben',
                'last_name' => 'Davis',
                'username' => 'bendavis',
                'gender' => 'male',
                'password' => Hash::make('password'),
                'github_username' => 'bendavis-code',
                'gmail' => 'ben@gmail.com',
                'gmail_verified_at' => now(),
                'notify_class' => true,
                'notify_module' => true,
                'notify_lab' => true,
                'notify_certificate' => true,
                'notify_email_channel' => true,
            ]);
        }

        // Secondary peer student for team collaboration
        $peerAuthId = $this->getOrCreateAuthUserId('student@example.com', 'John Doe', 'student', 'password');
        $peerData = [
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'gender' => 'male',
            'password' => Hash::make('password'),
            'role' => 'student',
            'github_username' => 'johndoe',
            'gmail' => 'student@example.com',
            'gmail_verified_at' => now(),
            'notify_class' => true,
            'notify_module' => true,
            'notify_lab' => true,
            'notify_certificate' => true,
            'notify_email_channel' => true,
        ];
        if ($peerAuthId) {
            $peerData['auth_user_id'] = $peerAuthId;
        }

        $peerStudent = User::updateOrCreate(
            ['email' => 'student@example.com'],
            $peerData
        );

        // Target student accounts to populate
        $students = collect([$showcaseStudent]);
        if ($benStudent) {
            $students->push($benStudent);
        }

        // -------------------------------------------------------------
        // 3. Competencies Definition & Scoring
        // -------------------------------------------------------------
        $compOop = Competency::updateOrCreate(
            ['code' => 'COMP-JAVA-OOP'],
            ['name' => 'Java Object-Oriented Architecture & Encapsulation']
        );
        $compConcurrency = Competency::updateOrCreate(
            ['code' => 'COMP-JAVA-CONCURRENCY'],
            ['name' => 'Thread Synchronization & Concurrency Control']
        );
        $compDsa = Competency::updateOrCreate(
            ['code' => 'COMP-DSA-TREES'],
            ['name' => 'Binary Trees & Graph Shortest Path Algorithms']
        );
        $compSec = Competency::updateOrCreate(
            ['code' => 'COMP-SEC-CRYPTO'],
            ['name' => 'Cryptographic Hashing & Defensive Secure Coding']
        );
        $compSys = Competency::updateOrCreate(
            ['code' => 'COMP-SYS-POSIX'],
            ['name' => 'POSIX Systems Architecture & Process Control']
        );

        foreach ($students as $student) {
            StudentCompetency::updateOrCreate(
                ['user_id' => $student->id, 'competency_id' => $compOop->id],
                ['score_achieved' => 98.5]
            );
            StudentCompetency::updateOrCreate(
                ['user_id' => $student->id, 'competency_id' => $compConcurrency->id],
                ['score_achieved' => 94.0]
            );
            StudentCompetency::updateOrCreate(
                ['user_id' => $student->id, 'competency_id' => $compDsa->id],
                ['score_achieved' => 92.5]
            );
            StudentCompetency::updateOrCreate(
                ['user_id' => $student->id, 'competency_id' => $compSec->id],
                ['score_achieved' => 97.0]
            );
            StudentCompetency::updateOrCreate(
                ['user_id' => $student->id, 'competency_id' => $compSys->id],
                ['score_achieved' => 89.0]
            );
        }

        // =============================================================
        // CLASS 1: Java Programming Essentials (JAVA101) - 100% COMPLETE
        // =============================================================
        $class1 = SchoolClass::updateOrCreate(
            ['code' => 'JAVA101'],
            [
                'name' => 'Java Programming Essentials',
                'instructor_id' => $instructor->id,
                'description' => 'Comprehensive immersion into Object-Oriented Programming, Encapsulation, Custom Checked/Unchecked Exception hierarchies, and Thread-Safe Concurrency in Java.',
            ]
        );

        foreach ($students as $student) {
            $class1->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);
        }
        $class1->students()->syncWithoutDetaching([$peerStudent->id => ['status' => 'enrolled']]);

        // Module 1.1: OOP & Exceptions
        $mod1_1 = Module::updateOrCreate(
            ['class_id' => $class1->id, 'order_index' => 1],
            [
                'title' => 'OOP, Encapsulation & Exceptions',
                'description' => 'Learn how to create custom exceptions and encapsulate class state in Java.',
                'content' => "# Object-Oriented Programming & Custom Exceptions\n\nEncapsulation is the bundling of data with the methods that operate on that data. In Java, this means keeping member variables `private` and exposing public getters and setters.\n\n### Exception Hierarchy\n- `Throwable`\n  - `Error` (Unrecoverable system issues)\n  - `Exception` (Checked exceptions - compiler enforced)\n    - `RuntimeException` (Unchecked exceptions, like `NullPointerException`)",
                'views_count' => 142,
            ]
        );

        $lab1_1Starter = [
            [
                'name' => 'InvalidAgeException.java',
                'content' => "// Implement InvalidAgeException extending Exception\npublic class InvalidAgeException extends Exception {\n    public InvalidAgeException(String message) {\n        super(message);\n    }\n}\n",
                'is_primary' => false,
                'is_readonly' => false,
            ],
            [
                'name' => 'Student.java',
                'content' => "// Implement Student with encapsulated name & age\npublic class Student {\n    private String name;\n    private int age;\n\n    public Student(String name, int age) throws InvalidAgeException {\n        if (age < 0 || age > 150) {\n            throw new InvalidAgeException(\"Age must be between 0 and 150. Provided: \" + age);\n        }\n        this.name = name;\n        this.age = age;\n    }\n\n    public String getName() { return name; }\n    public int getAge() { return age; }\n}\n",
                'is_primary' => false,
                'is_readonly' => false,
            ],
            [
                'name' => 'Main.java',
                'content' => "public class Main {\n    public static void main(String[] args) {\n        try {\n            Student s1 = new Student(\"Alice\", 20);\n            System.out.println(\"Student created: \" + s1.getName() + \", \" + s1.getAge());\n            Student s2 = new Student(\"Bob\", -5);\n        } catch (InvalidAgeException e) {\n            System.out.println(\"Caught exception: \" + e.getMessage());\n        }\n    }\n}\n",
                'is_primary' => true,
                'is_readonly' => false,
            ],
        ];

        $lab1_1 = Laboratory::updateOrCreate(
            ['module_id' => $mod1_1->id, 'title' => 'Java OOP: Custom Exceptions & Encapsulation'],
            [
                'description' => "Implement a custom checked exception `InvalidAgeException` and enforce age validation bounds in a `Student` class.\n\nRequirements:\n1. Extend `Exception`.\n2. Encapsulate private fields.\n3. Validate age range (0 to 150).\n4. Catch exception in `Main`.",
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Create custom InvalidAgeException class extending Exception', 'command' => 'regex:class\\s+InvalidAgeException\\s+extends\\s+Exception'],
                    ['id' => 2, 'task' => 'Create Student class with private name and age fields', 'command' => 'regex:private\\s+String\\s+name|private\\s+int\\s+age'],
                    ['id' => 3, 'task' => 'Throw InvalidAgeException if age is out of bounds (0-150)', 'command' => 'regex:throw\\s+new\\s+InvalidAgeException'],
                    ['id' => 4, 'task' => 'Catch InvalidAgeException in Main class and handle it', 'command' => 'regex:catch\\s*\\(\\s*InvalidAgeException'],
                ],
                'reference_solution' => "// Fully valid Java solution for custom exceptions\nclass InvalidAgeException extends Exception {\n    public InvalidAgeException(String m) { super(m); }\n}",
                'rubric' => "1. Correct inheritance from Exception.\n2. Private fields with proper getters.\n3. Bounds check in constructor.\n4. Graceful handling in Main.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Student created: Alice, 20'],
                    ['input' => '', 'expected' => 'Caught exception: Age must be between 0 and 150'],
                ],
                'starter_files' => $lab1_1Starter,
                'time_limit' => 45,
                'is_group_lab' => false,
                'availability_mode' => 'open',
                'views_count' => 88,
            ]
        );

        // Module 1.2: Generic Collections
        $mod1_2 = Module::updateOrCreate(
            ['class_id' => $class1->id, 'order_index' => 2],
            [
                'title' => 'Collections Framework & Custom Generics',
                'description' => 'Master Java generic type parameters, bounding, and designing reusable repositories.',
                'content' => "# Generics and Collection Abstractions\n\nGenerics allow types (classes and interfaces) to be parameters when defining classes, interfaces, and methods. Type safety without explicit casting is the key advantage.",
                'views_count' => 96,
            ]
        );

        $lab1_2Starter = [
            [
                'name' => 'Entity.java',
                'content' => "public interface Entity<K> {\n    K getId();\n}\n",
                'is_primary' => false,
                'is_readonly' => true,
            ],
            [
                'name' => 'Repository.java',
                'content' => "import java.util.*;\nimport java.util.function.Predicate;\n\npublic class Repository<T extends Entity<K>, K> {\n    private final Map<K, T> storage = new HashMap<>();\n\n    public void save(T entity) {\n        storage.put(entity.getId(), entity);\n    }\n\n    public Optional<T> findById(K id) {\n        return Optional.ofNullable(storage.get(id));\n    }\n\n    public List<T> filter(Predicate<T> predicate) {\n        List<T> result = new ArrayList<>();\n        for (T item : storage.values()) {\n            if (predicate.test(item)) result.add(item);\n        }\n        return result;\n    }\n}\n",
                'is_primary' => true,
                'is_readonly' => false,
            ],
        ];

        $lab1_2 = Laboratory::updateOrCreate(
            ['module_id' => $mod1_2->id, 'title' => 'Generic Filterable Repository Pattern'],
            [
                'description' => 'Create a generic in-memory repository with generic key K and entity T that supports predicate filtering and optional retrieval.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Define generic class Repository<T extends Entity<K>, K>', 'command' => 'regex:class\\s+Repository<T\\s+extends\\s+Entity<K>,\\s*K>'],
                    ['id' => 2, 'task' => 'Implement save method with Map backing', 'command' => 'regex:public\\s+void\\s+save\\('],
                    ['id' => 3, 'task' => 'Implement Optional<T> findById method', 'command' => 'regex:Optional<T>\\s+findById'],
                    ['id' => 4, 'task' => 'Implement filter using Predicate<T>', 'command' => 'regex:filter\\s*\\(\\s*Predicate<T>'],
                ],
                'reference_solution' => "// Generic repository with HashMap",
                'rubric' => "1. Correct type parameter bounds.\n2. Type-safe save and lookup.\n3. Functional predicate filter.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Found entity: 1'],
                ],
                'starter_files' => $lab1_2Starter,
                'time_limit' => 60,
                'is_group_lab' => false,
                'availability_mode' => 'open',
                'views_count' => 74,
            ]
        );

        // Module 1.3: Concurrency
        $mod1_3 = Module::updateOrCreate(
            ['class_id' => $class1->id, 'order_index' => 3],
            [
                'title' => 'Multithreading & Thread Synchronization',
                'description' => 'Thread safety, locks, condition variables, and solving the Producer-Consumer problem.',
                'content' => "# Concurrency and Locks\n\nThread coordination requires explicit synchronization when mutating shared mutable state.",
                'views_count' => 65,
            ]
        );

        $lab1_3 = Laboratory::updateOrCreate(
            ['module_id' => $mod1_3->id, 'title' => 'Thread-Safe Blocking Queue Implementation'],
            [
                'description' => 'Build a bounded blocking queue using synchronized methods and wait()/notifyAll().',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Implement put() with wait when full', 'command' => 'regex:synchronized.*put.*wait'],
                    ['id' => 2, 'task' => 'Implement take() with wait when empty', 'command' => 'regex:synchronized.*take.*wait'],
                    ['id' => 3, 'task' => 'Notify waiting threads on state change', 'command' => 'regex:notifyAll\\(\\)'],
                ],
                'reference_solution' => "// Bounded Blocking Queue implementation",
                'rubric' => "1. Thread safety.\n2. Bounded capacity check.\n3. Correct notifyAll behavior.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Queue processed 50 items'],
                ],
                'starter_files' => [
                    ['name' => 'BlockingQueue.java', 'content' => "public class BlockingQueue {\n    // Implement bounded queue\n}", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 50,
                'is_group_lab' => false,
                'availability_mode' => 'live',
                'live_duration_minutes' => 50,
                'live_status' => 'not_started',
                'live_started_at' => null,
                'live_elapsed_seconds' => 0,
                'views_count' => 59,
            ]
        );

        // Populate completed sessions & certificates for Class 1
        foreach ($students as $student) {
            // Lab 1.1 session (Completed)
            $sess1_1 = LabSession::updateOrCreate(
                ['lab_id' => $lab1_1->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(6),
                    'ended_at' => now()->subDays(6)->addMinutes(28),
                    'performance_score' => 98.0,
                    'completed_tasks' => [1, 2, 3, 4],
                    'diff_stats' => ['linesAdded' => 45, 'linesDeleted' => 4, 'filesModified' => 3],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Lab 1.2 session (Completed)
            $sess1_2 = LabSession::updateOrCreate(
                ['lab_id' => $lab1_2->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(4),
                    'ended_at' => now()->subDays(4)->addMinutes(35),
                    'performance_score' => 100.0,
                    'completed_tasks' => [1, 2, 3, 4],
                    'diff_stats' => ['linesAdded' => 62, 'linesDeleted' => 6, 'filesModified' => 2],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Lab 1.3 session (Completed)
            $sess1_3 = LabSession::updateOrCreate(
                ['lab_id' => $lab1_3->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(3),
                    'ended_at' => now()->subDays(3)->addMinutes(42),
                    'performance_score' => 95.0,
                    'completed_tasks' => [1, 2, 3],
                    'diff_stats' => ['linesAdded' => 54, 'linesDeleted' => 8, 'filesModified' => 2],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Add Telemetry Logs for session 1.1
            TelemetryLog::create([
                'lab_session_id' => $sess1_1->id,
                'event_type' => 'command',
                'payload' => ['command' => 'javac Main.java && java Main', 'status' => 'success'],
                'created_at' => now()->subDays(6)->addMinutes(15),
            ]);
            TelemetryLog::create([
                'lab_session_id' => $sess1_1->id,
                'event_type' => 'webcam_check',
                'payload' => ['faces_detected' => 1, 'confidence' => 0.99],
                'created_at' => now()->subDays(6)->addMinutes(20),
            ]);

            // Add Certificate for Class 1 (100% completed)
            $certCode = 'CERT-JAVA-' . strtoupper(substr(md5($student->id . $class1->id), 0, 8));
            $certificate = Certificate::updateOrCreate(
                ['user_id' => $student->id, 'class_id' => $class1->id],
                [
                    'verification_code' => $certCode,
                    'qr_code_path' => 'certificates/qr-' . $certCode . '.svg',
                    'issued_at' => now()->subDays(2),
                ]
            );

            // Add database notification directly to avoid SMTP timeout
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => ClassActivityNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $student->id,
                'data' => json_encode([
                    'title' => "Certificate Earned: {$class1->name}",
                    'message' => "Congratulations! You have completed all laboratories in '{$class1->name}' and earned your verified competency credential ({$certCode}).",
                    'url' => route('certificates.show', $certificate->id),
                    'type' => 'certificate',
                ]),
                'read_at' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
        }

        // =============================================================
        // CLASS 2: Data Structures & Algorithms (CS201) - IN PROGRESS (50%)
        // =============================================================
        $class2 = SchoolClass::updateOrCreate(
            ['code' => 'CS201'],
            [
                'name' => 'Data Structures & Algorithms in Java',
                'instructor_id' => $instructor->id,
                'description' => 'Rigorous exploration of asymptotic complexity analysis, Binary Search Trees, Graphs, Dijkstra’s shortest path algorithm, and dynamic programming optimization.',
            ]
        );

        foreach ($students as $student) {
            $class2->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);
        }

        $mod2_1 = Module::updateOrCreate(
            ['class_id' => $class2->id, 'order_index' => 1],
            [
                'title' => 'Binary Trees & Recursive Traversals',
                'description' => 'Recursive node definitions, in-order, pre-order, post-order traversals and search tree invariants.',
                'content' => "# Binary Search Trees\n\nA Binary Search Tree (BST) is a node-based binary tree data structure where the left subtree of a node contains only nodes with keys lesser than the node's key.",
                'views_count' => 110,
            ]
        );

        $lab2_1 = Laboratory::updateOrCreate(
            ['module_id' => $mod2_1->id, 'title' => 'Binary Search Tree: In-Order Traversal & Search'],
            [
                'description' => 'Implement insert, search, and in-order traversal for a Binary Search Tree in Java.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Implement insert(int val) maintaining BST ordering', 'command' => 'regex:void\\s+insert\\(int'],
                    ['id' => 2, 'task' => 'Implement search(int val) returning boolean in O(h) time', 'command' => 'regex:boolean\\s+search\\(int'],
                    ['id' => 3, 'task' => 'Implement inOrderTraversal() collecting sorted list', 'command' => 'regex:inOrderTraversal\\('],
                ],
                'reference_solution' => "// BST implementation with recursive helper methods",
                'rubric' => "1. Correct BST property on insert.\n2. Efficient logarithmic search.\n3. Sorted in-order traversal.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'In-order: [2, 5, 8, 12, 18]'],
                ],
                'starter_files' => [
                    ['name' => 'BinarySearchTree.java', 'content' => "public class BinarySearchTree {\n    // Node and BST implementation\n}", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 60,
                'is_group_lab' => false,
                'availability_mode' => 'open',
                'views_count' => 67,
            ]
        );

        $mod2_2 = Module::updateOrCreate(
            ['class_id' => $class2->id, 'order_index' => 2],
            [
                'title' => 'Graph Theory & Shortest Path (Dijkstra)',
                'description' => 'Adjacency lists, min-heaps/priority queues, edge relaxation, and path reconstruction.',
                'content' => "# Dijkstra's Algorithm\n\nFind the shortest paths between nodes in a graph with non-negative edge weights.",
                'views_count' => 84,
            ]
        );

        $lab2_2 = Laboratory::updateOrCreate(
            ['module_id' => $mod2_2->id, 'title' => 'Dijkstra\'s Shortest Path Algorithm'],
            [
                'description' => 'Implement Dijkstra algorithm using PriorityQueue to compute shortest distances from source node to all destinations.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Initialize distance array and PriorityQueue', 'command' => 'regex:PriorityQueue<Node>'],
                    ['id' => 2, 'task' => 'Implement edge relaxation loop', 'command' => 'regex:if\\s*\\(dist\\[u\\]\\s*\\+\\s*weight\\s*<\\s*dist\\[v\\]\\)'],
                    ['id' => 3, 'task' => 'Handle unreachable nodes with infinity sentinel', 'command' => 'regex:Integer\\.MAX_VALUE'],
                ],
                'reference_solution' => "// Dijkstra with PriorityQueue",
                'rubric' => "1. Optimal O((V+E)logV) runtime.\n2. Accurate shortest distances.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Distance to Node 4: 7'],
                ],
                'starter_files' => [
                    ['name' => 'Dijkstra.java', 'content' => "public class Dijkstra {\n    // Dijkstra implementation\n}", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 60,
                'is_group_lab' => false,
                'availability_mode' => 'live',
                'live_duration_minutes' => 60,
                'live_status' => 'active',
                'live_started_at' => now()->subMinutes(15),
                'live_elapsed_seconds' => 0,
                'views_count' => 52,
            ]
        );

        foreach ($students as $student) {
            // Lab 2.1 completed
            LabSession::updateOrCreate(
                ['lab_id' => $lab2_1->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(2),
                    'ended_at' => now()->subDays(2)->addMinutes(38),
                    'performance_score' => 94.0,
                    'completed_tasks' => [1, 2, 3],
                    'diff_stats' => ['linesAdded' => 51, 'linesDeleted' => 3, 'filesModified' => 2],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Lab 2.2 currently in progress!
            $sess2_2 = LabSession::updateOrCreate(
                ['lab_id' => $lab2_2->id, 'user_id' => $student->id],
                [
                    'status' => 'in_progress',
                    'started_at' => now()->subHours(1),
                    'ended_at' => null,
                    'performance_score' => 65.0,
                    'completed_tasks' => [1, 2],
                    'diff_stats' => ['linesAdded' => 34, 'linesDeleted' => 2, 'filesModified' => 1],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Telemetry & Anomaly for active session
            Anomaly::create([
                'lab_session_id' => $sess2_2->id,
                'type' => 'excessive_tab_switch',
                'severity' => 'medium',
                'description' => 'Student switched out of coding workspace 3 times within 2 minutes.',
                'resolved' => false,
            ]);
        }

        // =============================================================
        // CLASS 3: Cybersecurity Defense & Secure Coding (SEC301)
        // =============================================================
        $class3 = SchoolClass::updateOrCreate(
            ['code' => 'SEC301'],
            [
                'name' => 'Cybersecurity Defense & Secure Coding',
                'instructor_id' => $instructor->id,
                'description' => 'Applied defensive security, cryptographic salting, PBKDF2/bcrypt hashing, OWASP Top 10 mitigation, and team firewall rule architecture.',
            ]
        );

        foreach ($students as $student) {
            $class3->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);
        }

        $mod3_1 = Module::updateOrCreate(
            ['class_id' => $class3->id, 'order_index' => 1],
            [
                'title' => 'Cryptographic Hashing & Password Salting',
                'description' => 'Rainbow table prevention, cryptographic salts, PBKDF2 with HMAC-SHA256, and constant-time string equality.',
                'content' => "# Cryptographic Password Hashing\n\nNever store raw passwords or unsalted hashes. Use unique random salts per user and adaptive key stretching.",
                'views_count' => 95,
            ]
        );

        $lab3_1 = Laboratory::updateOrCreate(
            ['module_id' => $mod3_1->id, 'title' => 'Secure Password Storage & Salted Hashing'],
            [
                'description' => 'Implement cryptographic salting and constant-time byte comparison to protect against timing attacks.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Generate 16-byte SecureRandom salt', 'command' => 'regex:SecureRandom.*nextBytes'],
                    ['id' => 2, 'task' => 'Hash password using PBKDF2WithHmacSHA256', 'command' => 'regex:PBKDF2WithHmacSHA256'],
                    ['id' => 3, 'task' => 'Implement constant-time comparison MessageDigest.isEqual', 'command' => 'regex:MessageDigest\\.isEqual'],
                ],
                'reference_solution' => "// PBKDF2 salted hasher",
                'rubric' => "1. Cryptographically secure random salt.\n2. Correct iteration count.\n3. Constant-time verification.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Password verification: SUCCESS'],
                ],
                'starter_files' => [
                    ['name' => 'PasswordHasher.java', 'content' => "public class PasswordHasher {\n    // Implement secure hashing\n}", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 45,
                'is_group_lab' => false,
                'availability_mode' => 'open',
                'views_count' => 70,
            ]
        );

        // Group Lab: Collaborative Firewall Engine
        $mod3_2 = Module::updateOrCreate(
            ['class_id' => $class3->id, 'order_index' => 2],
            [
                'title' => 'Collaborative Packet Filtering & Firewall Rules',
                'description' => 'Real-time team development of a stateful firewall packet evaluation pipeline.',
                'content' => "# Stateful Inspection Firewalls\n\nTrack connection states (SYN, ESTABLISHED, FIN) and enforce IP/Port filtering matrices.",
                'views_count' => 88,
            ]
        );

        $lab3_2 = Laboratory::updateOrCreate(
            ['module_id' => $mod3_2->id, 'title' => 'Collaborative Stateful Firewall Engine'],
            [
                'description' => 'Team Laboratory: Work together in a shared workspace to build a stateful packet filter rule engine.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Implement PacketRule matcher for CIDR IP ranges', 'command' => 'regex:boolean\\s+matchesIp\\('],
                    ['id' => 2, 'task' => 'Track TCP handshake connection states in memory', 'command' => 'regex:ConnectionState'],
                    ['id' => 3, 'task' => 'Drop invalid state transitions and log security alerts', 'command' => 'regex:dropPacket'],
                ],
                'reference_solution' => "// Team Firewall rule engine",
                'rubric' => "1. Precise CIDR matching.\n2. Proper state transition table.\n3. Team code contributions.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Firewall test passed: 100 packets analyzed'],
                ],
                'starter_files' => [
                    ['name' => 'Firewall.java', 'content' => "public class Firewall {\n    // Collaborative firewall implementation\n}", 'is_primary' => true, 'is_readonly' => false],
                    ['name' => 'PacketRule.java', 'content' => "public class PacketRule {\n    // Rule criteria\n}", 'is_primary' => false, 'is_readonly' => false],
                ],
                'time_limit' => 90,
                'is_group_lab' => true,
                'availability_mode' => 'live',
                'live_duration_minutes' => 90,
                'live_status' => 'active',
                'live_started_at' => now()->subMinutes(25),
                'live_elapsed_seconds' => 0,
                'views_count' => 102,
            ]
        );

        // Team Group
        $groupAlpha = Group::updateOrCreate(
            ['name' => 'CyberShield Team Alpha', 'lab_id' => $lab3_2->id]
        );

        $groupAlpha->members()->syncWithoutDetaching([
            $showcaseStudent->id => ['contribution_score' => 88.0],
            $peerStudent->id => ['contribution_score' => 45.0],
        ]);
        if ($benStudent) {
            $groupAlpha->members()->syncWithoutDetaching([
                $benStudent->id => ['contribution_score' => 76.0],
            ]);
        }

        foreach ($students as $student) {
            // Lab 3.1 completed
            LabSession::updateOrCreate(
                ['lab_id' => $lab3_1->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(1),
                    'ended_at' => now()->subDays(1)->addMinutes(32),
                    'performance_score' => 97.0,
                    'completed_tasks' => [1, 2, 3],
                    'diff_stats' => ['linesAdded' => 42, 'linesDeleted' => 5, 'filesModified' => 2],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );

            // Lab 3.2 team lab in progress
            LabSession::updateOrCreate(
                ['lab_id' => $lab3_2->id, 'user_id' => $student->id],
                [
                    'group_id' => $groupAlpha->id,
                    'status' => 'in_progress',
                    'started_at' => now()->subMinutes(45),
                    'ended_at' => null,
                    'performance_score' => 75.0,
                    'completed_tasks' => [1],
                    'diff_stats' => ['linesAdded' => 68, 'linesDeleted' => 12, 'filesModified' => 2],
                    'code_contributions' => [
                        ['name' => $student->name, 'percentage' => 60],
                        ['name' => $peerStudent->name, 'percentage' => 40],
                    ],
                ]
            );
        }

        // =============================================================
        // CLASS 4: Full-Stack Web Architecture (WEB401) - INVITATION PENDING
        // =============================================================
        $class4 = SchoolClass::updateOrCreate(
            ['code' => 'WEB401'],
            [
                'name' => 'Full-Stack Web Architecture & Cloud APIs',
                'instructor_id' => $instructor->id,
                'description' => 'Modern microservices, RESTful API contracts, JWT session tokens, database index tuning, and high-performance caching topologies.',
            ]
        );

        // Invite students (status: 'invited') so the invitation banner appears in the dashboard!
        foreach ($students as $student) {
            $class4->students()->syncWithoutDetaching([$student->id => ['status' => 'invited']]);

            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => ClassActivityNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $student->id,
                'data' => json_encode([
                    'title' => "Class Invitation: {$class4->name}",
                    'message' => "Dr. Jane Smith invited you to join '{$class4->name}' (Join Code: {$class4->code}). Check your invitations on the Classes page to accept.",
                    'url' => route('classes.index'),
                    'type' => 'class',
                ]),
                'read_at' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ]);
        }

        // Add 1 module for Class 4
        $mod4_1 = Module::updateOrCreate(
            ['class_id' => $class4->id, 'order_index' => 1],
            [
                'title' => 'REST API Architecture & JWT Contracts',
                'description' => 'Stateless authentication, Bearer tokens, and HTTP status protocol design.',
                'content' => "# REST Architecture\n\nResource-oriented URL hierarchies and stateless token verification.",
                'views_count' => 40,
            ]
        );

        $lab4_1 = Laboratory::updateOrCreate(
            ['module_id' => $mod4_1->id, 'title' => 'JWT Authentication & Bearer Tokens'],
            [
                'description' => 'Implement signature verification and stateless token claims validation.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Validate Authorization Header format', 'command' => 'regex:Bearer\s+'],
                    ['id' => 2, 'task' => 'Verify HMAC-SHA256 signature', 'command' => 'regex:hash_hmac|verifyToken'],
                ],
                'reference_solution' => "// JWT Token Verification",
                'rubric' => "1. Secure secret key parsing.\n2. Expiration and signature checks.",
                'test_cases' => [
                    ['input' => '', 'expected' => 'JWT Verification Success: 200 OK'],
                ],
                'starter_files' => [
                    ['name' => 'auth.js', 'content' => "// Implement JWT validation\nfunction verifyToken(token) {}\n", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 45,
                'is_group_lab' => false,
                'availability_mode' => 'live',
                'live_duration_minutes' => 45,
                'live_status' => 'closed',
                'live_started_at' => now()->subHours(2),
                'live_elapsed_seconds' => 2700,
                'views_count' => 64,
            ]
        );

        // =============================================================
        // CLASS 5: Linux Systems Programming & Shell Scripting (SYS205)
        // =============================================================
        $class5 = SchoolClass::updateOrCreate(
            ['code' => 'SYS205'],
            [
                'name' => 'Linux Systems Programming & Shell Scripting',
                'instructor_id' => $instructor->id,
                'description' => 'POSIX systems calls, process forking, inter-process pipes, signal trapping, and automated Linux shell utility scripts.',
            ]
        );

        foreach ($students as $student) {
            $class5->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);
        }

        $mod5_1 = Module::updateOrCreate(
            ['class_id' => $class5->id, 'order_index' => 1],
            [
                'title' => 'POSIX Process Creation & Pipes',
                'description' => 'Forking child processes and bidirectional pipe communication.',
                'content' => "# POSIX Processes & Pipes\n\nProcess control primitives in UNIX-like systems.",
                'views_count' => 55,
            ]
        );

        $lab5_1 = Laboratory::updateOrCreate(
            ['module_id' => $mod5_1->id, 'title' => 'POSIX Process Fork & Pipe IPC'],
            [
                'description' => 'Create child processes using fork() and communicate via anonymous pipes.',
                'tasks_definition' => [
                    ['id' => 1, 'task' => 'Create pipe before calling fork()', 'command' => 'regex:pipe\\('],
                    ['id' => 2, 'task' => 'Fork child and write message to pipe', 'command' => 'regex:fork\\(\\)'],
                    ['id' => 3, 'task' => 'Parent reads message and waits for child termination', 'command' => 'regex:waitpid|wait\\('],
                ],
                'reference_solution' => "// POSIX pipe solution",
                'rubric' => "1. Proper pipe file descriptor hygiene.\n2. Avoid zombie processes with wait().",
                'test_cases' => [
                    ['input' => '', 'expected' => 'Child sent: Ping | Parent received: Ping'],
                ],
                'starter_files' => [
                    ['name' => 'main.c', 'content' => "#include <stdio.h>\n#include <unistd.h>\n\nint main() {\n    // Implement pipe IPC\n    return 0;\n}\n", 'is_primary' => true, 'is_readonly' => false]
                ],
                'time_limit' => 45,
                'is_group_lab' => false,
                'availability_mode' => 'open',
                'views_count' => 48,
            ]
        );

        foreach ($students as $student) {
            LabSession::updateOrCreate(
                ['lab_id' => $lab5_1->id, 'user_id' => $student->id],
                [
                    'status' => 'completed',
                    'started_at' => now()->subDays(5),
                    'ended_at' => now()->subDays(5)->addMinutes(25),
                    'performance_score' => 91.0,
                    'completed_tasks' => [1, 2, 3],
                    'diff_stats' => ['linesAdded' => 38, 'linesDeleted' => 4, 'filesModified' => 1],
                    'code_contributions' => [['name' => $student->name, 'percentage' => 100]],
                ]
            );
        }

        // Additional instructor anomaly to populate instructor dashboard
        $anySession = LabSession::first();
        if ($anySession) {
            Anomaly::create([
                'lab_session_id' => $anySession->id,
                'type' => 'code_paste',
                'severity' => 'low',
                'description' => 'Small snippet paste detected matching official Java documentation syntax.',
                'resolved' => true,
            ]);
        }
    }

    /**
     * Look up or insert an auth user in auth.users (Supabase PostgreSQL integration).
     */
    protected function getOrCreateAuthUserId(string $email, string $name, string $role, string $password): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return (string) Str::uuid();
        }

        try {
            $existing = DB::select("SELECT id FROM auth.users WHERE email = ? LIMIT 1", [$email]);
            if (!empty($existing)) {
                return $existing[0]->id;
            }

            $uuid = (string) Str::uuid();
            $hashed = Hash::make($password);

            DB::statement("
                INSERT INTO auth.users (
                    id, instance_id, aud, role, email, encrypted_password,
                    email_confirmed_at, raw_app_meta_data, raw_user_meta_data, created_at, updated_at
                ) VALUES (
                    '{$uuid}',
                    '00000000-0000-0000-0000-000000000000',
                    'authenticated',
                    'authenticated',
                    '{$email}',
                    '{$hashed}',
                    now(),
                    '{\"provider\":\"email\",\"providers\":[\"email\"]}'::jsonb,
                    jsonb_build_object('name', '{$name}', 'role', '{$role}'),
                    now(),
                    now()
                )
            ");

            DB::statement("
                INSERT INTO auth.identities (
                    id, user_id, identity_data, provider, provider_id, last_sign_in_at, created_at, updated_at
                ) VALUES (
                    gen_random_uuid(),
                    '{$uuid}',
                    jsonb_build_object('sub', '{$uuid}', 'email', '{$email}'),
                    'email',
                    '{$uuid}',
                    now(),
                    now(),
                    now()
                )
            ");

            return $uuid;
        } catch (\Throwable $e) {
            Log::warning("Could not sync with auth.users: " . $e->getMessage());
            return null;
        }
    }
}
