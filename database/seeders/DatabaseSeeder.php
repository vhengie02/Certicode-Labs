<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Instructor
        $instructor = User::firstOrCreate(
            ['email' => 'instructor@example.com'],
            [
                'name' => 'Dr. Jane Smith',
                'password' => bcrypt('password'),
                'role' => 'instructor',
            ]
        );

        // 2. Seed Student
        $student = User::firstOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'John Doe',
                'password' => bcrypt('password'),
                'role' => 'student',
                'github_username' => 'johndoe',
            ]
        );

        // 3. Seed Class
        $class = \App\Models\SchoolClass::firstOrCreate(
            ['code' => 'JAVA101'],
            [
                'name' => 'Java Programming Essentials',
                'instructor_id' => $instructor->id,
                'description' => 'Introduction to Object-Oriented Programming and Exception Handling in Java.',
            ]
        );

        // Enroll Student
        $class->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);

        // 4. Seed Module
        $module = \App\Models\Module::firstOrCreate(
            [
                'class_id' => $class->id,
                'title' => 'OOP, Encapsulation & Exceptions',
            ],
            [
                'description' => 'Learn how to create custom exceptions and encapsulate class state in Java.',
                'content' => 'This module covers Java exceptions hierarchy, custom exceptions, and fields encapsulation.',
                'order_index' => 1,
            ]
        );

        // 5. Seed Java Laboratory
        $javaReferenceCode = <<<'JAVA'
// InvalidAgeException.java
class InvalidAgeException extends Exception {
    public InvalidAgeException(String message) {
        super(message);
    }
}

// Student.java
class Student {
    private String name;
    private int age;

    public Student(String name, int age) throws InvalidAgeException {
        if (age < 0 || age > 150) {
            throw new InvalidAgeException("Age must be between 0 and 150. Provided: " + age);
        }
        this.name = name;
        this.age = age;
    }

    public String getName() {
        return name;
    }

    public int getAge() {
        return age;
    }
}

// Main.java
public class Main {
    public static void main(String[] args) {
        try {
            System.out.println("Attempting to create valid student...");
            Student s1 = new Student("Alice", 20);
            System.out.println("Student created: " + s1.getName() + ", " + s1.getAge());

            System.out.println("Attempting to create invalid student...");
            Student s2 = new Student("Bob", -5);
        } catch (InvalidAgeException e) {
            System.out.println("Caught exception: " + e.getMessage());
        }
    }
}
JAVA;

        $tasks = [
            [
                'id' => 1,
                'task' => 'Create custom InvalidAgeException class extending Exception',
                'command' => 'regex:class\s+InvalidAgeException\s+extends\s+Exception',
            ],
            [
                'id' => 2,
                'task' => 'Create Student class with private name and age fields',
                'command' => 'regex:private\s+String\s+name|private\s+int\s+age',
            ],
            [
                'id' => 3,
                'task' => 'Throw InvalidAgeException if age is out of bounds (0-150)',
                'command' => 'regex:throw\s+new\s+InvalidAgeException',
            ],
            [
                'id' => 4,
                'task' => 'Catch InvalidAgeException in Main class and handle it',
                'command' => 'regex:catch\s*\(\s*InvalidAgeException',
            ],
        ];

        $rubric = "1. Correct Exception inheritance: InvalidAgeException must extend Exception class.\n2. Proper encapsulation: fields name (String) and age (int) in Student class must be private.\n3. Input validation: constructor must validate age range (0 to 150 inclusive) and throw InvalidAgeException with descriptive message.\n4. Correct exception handling: use try-catch inside Main class to catch the custom exception and print the exception message.";

        $testCases = [
            [
                'input' => '',
                'expected' => 'Student created: Alice, 20',
            ],
            [
                'input' => '',
                'expected' => 'Caught exception: Age must be between 0 and 150',
            ],
        ];

        $laboratory = \App\Models\Laboratory::updateOrCreate(
            [
                'module_id' => $module->id,
                'title' => 'Java OOP: Custom Exceptions & Encapsulation',
            ],
            [
                'description' => 'In this lab, you will implement a custom exception named `InvalidAgeException` and enforce encapsulation in a `Student` class.\n\nInstructions:\n1. Implement `InvalidAgeException` extending `Exception`.\n2. Implement `Student` with private fields `name` and `age`.\n3. Validate age in constructor, throwing `InvalidAgeException` if out of bounds (0-150).\n4. Try instantiating Student with invalid age in `Main`, catch it, and print the exception message.',
                'tasks_definition' => $tasks,
                'reference_solution' => $javaReferenceCode,
                'rubric' => $rubric,
                'test_cases' => $testCases,
                'time_limit' => 30,
                'is_group_lab' => false,
                'availability_mode' => 'open',
            ]
        );

        // 6. Seed initial in-progress lab session for testing
        \App\Models\LabSession::firstOrCreate(
            [
                'lab_id' => $laboratory->id,
                'user_id' => $student->id,
            ],
            [
                'status' => 'in_progress',
                'started_at' => now(),
                'performance_score' => 0.0,
                'completed_tasks' => [],
            ]
        );

        // 7. Seed full showcase accounts, classes, modules, labs, certificates, and competencies
        $this->call(ShowcaseAccountSeeder::class);
    }
}
