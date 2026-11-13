<?php

namespace Database\Seeders;

use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A student account with three beginner Java labs (for loop, if/else, switch) and unlimited
 * attempts on each. Every lab hands over working code with one line left to write, so a
 * student can finish and retake them in a couple of minutes.
 *
 * Run: php artisan db:seed --class=EasyLabsAccountSeeder
 *
 * On SQLite the student's password is "password". Any other database needs
 * EASY_LABS_PASSWORD set (12+ characters): the local .env can point at the hosted database,
 * and a known password there is a known login.
 */
class EasyLabsAccountSeeder extends Seeder
{
    use RefusesProduction;

    public const STUDENT_EMAIL = 'easy.student@certicode.test';

    public const INSTRUCTOR_EMAIL = 'instructor@example.com';

    public const CLASS_CODE = 'EASY101';

    public function run(): void
    {
        $this->refuseInProduction();
        $password = $this->studentPassword();

        // firstOrCreate: rerunning never resets a password someone has since changed. Off
        // SQLite a missing instructor gets a random password: the class needs an owner, not a
        // second known login.
        $instructor = User::firstOrCreate(
            ['email' => self::INSTRUCTOR_EMAIL],
            [
                'name' => 'Dr. Jane Smith',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'password' => Hash::make($this->usesLocalDatabase() ? 'password' : Str::random(40)),
                'role' => 'instructor',
            ]
        );

        $student = User::firstOrCreate(
            ['email' => self::STUDENT_EMAIL],
            [
                'name' => 'Easy Labs Student',
                'first_name' => 'Easy',
                'last_name' => 'Student',
                'username' => 'easystudent',
                'password' => Hash::make($password),
                'role' => 'student',
            ]
        );

        $class = SchoolClass::updateOrCreate(
            ['code' => self::CLASS_CODE],
            [
                'name' => 'Java Basics: Easy Labs',
                'instructor_id' => $instructor->id,
                'description' => 'Three short practice labs on for loops, if/else and switch. Retake them as often as you like.',
            ]
        );

        $class->students()->syncWithoutDetaching([$student->id => ['status' => 'enrolled']]);

        foreach ($this->labs() as $index => $lab) {
            $module = Module::updateOrCreate(
                ['class_id' => $class->id, 'order_index' => $index + 1],
                [
                    'title' => $lab['module'],
                    'description' => $lab['module_description'],
                    'content' => $lab['module_content'],
                ]
            );

            Laboratory::updateOrCreate(
                ['module_id' => $module->id, 'title' => $lab['title']],
                [
                    'description' => $lab['description'],
                    'tasks_definition' => [
                        ['id' => 1, 'task' => $lab['task'], 'command' => $lab['check']],
                    ],
                    'reference_solution' => $lab['solution'],
                    'rubric' => "1. {$lab['task']}\n\nThis is a beginner practice lab. Give full marks when the code compiles and the TODO line is filled in. Do not deduct for style, naming or extra output.",
                    'test_cases' => [
                        ['input' => '', 'expected' => $lab['expected']],
                    ],
                    'starter_files' => [
                        ['name' => 'Main.java', 'content' => $lab['starter'], 'is_primary' => true, 'is_readonly' => false],
                    ],
                    'time_limit' => 60,
                    'is_group_lab' => false,
                    'availability_mode' => 'open',
                    'due_at' => null,
                    'max_attempts' => 0, // 0 = unlimited (Laboratory::attemptLimit)
                ]
            );
        }
    }

    private function studentPassword(): string
    {
        $password = (string) env('EASY_LABS_PASSWORD', '');

        if ($password === '' && $this->usesLocalDatabase()) {
            return 'password';
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException(static::class.' needs EASY_LABS_PASSWORD (12+ characters) on a '.DB::connection()->getDriverName().' database.');
        }

        return $password;
    }

    protected function usesLocalDatabase(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * @return list<array<string, string>>
     */
    private function labs(): array
    {
        return [
            [
                'module' => 'For Loops',
                'module_description' => 'Repeat a statement a set number of times.',
                'module_content' => "# For Loops\n\nA `for` loop repeats its body while the condition is true:\n\n```java\nfor (int i = 1; i <= 3; i++) {\n    System.out.println(i);\n}\n```\n\nThis prints 1, 2 and 3.",
                'title' => 'For Loop: Count to 5',
                'description' => "The loop is already written. Add one line inside it so the program prints the numbers 1 to 5.\n\nHint: `System.out.println(i);`",
                'task' => 'Inside the for loop, print i with System.out.println(i);',
                'check' => 'regex:println\s*\(\s*i\s*\)',
                'expected' => "1\n2\n3\n4\n5",
                'starter' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        for (int i = 1; i <= 5; i++) {
            // TODO: print i on its own line
        }
    }
}

JAVA,
                'solution' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        for (int i = 1; i <= 5; i++) {
            System.out.println(i);
        }
    }
}

JAVA,
            ],
            [
                'module' => 'If / Else',
                'module_description' => 'Choose between two paths with a condition.',
                'module_content' => "# If / Else\n\nAn `if` runs its block when the condition is true; the `else` block runs otherwise:\n\n```java\nif (age >= 18) {\n    System.out.println(\"Adult\");\n} else {\n    System.out.println(\"Minor\");\n}\n```",
                'title' => 'If / Else: Positive or Not',
                'description' => "The `if` branch is done. Fill in the `else` branch so it prints `Not positive`.\n\nHint: `System.out.println(\"Not positive\");`",
                'task' => 'In the else branch, print "Not positive" with System.out.println',
                'check' => 'regex:println\s*\(\s*"Not positive"\s*\)',
                'expected' => 'Positive',
                'starter' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        int number = 7;

        if (number > 0) {
            System.out.println("Positive");
        } else {
            // TODO: print "Not positive"
        }
    }
}

JAVA,
                'solution' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        int number = 7;

        if (number > 0) {
            System.out.println("Positive");
        } else {
            System.out.println("Not positive");
        }
    }
}

JAVA,
            ],
            [
                'module' => 'Switch Statements',
                'module_description' => 'Pick one case out of many by value.',
                'module_content' => "# Switch\n\nA `switch` jumps to the `case` that matches the value. `break` stops it falling into the next case:\n\n```java\nswitch (day) {\n    case 1:\n        System.out.println(\"Monday\");\n        break;\n    default:\n        System.out.println(\"Other day\");\n}\n```",
                'title' => 'Switch: Name the Day',
                'description' => "Cases 1 and default are done. Fill in `case 2` so it prints `Tuesday`.\n\nHint: `System.out.println(\"Tuesday\");`",
                'task' => 'In case 2, print "Tuesday" with System.out.println',
                'check' => 'regex:println\s*\(\s*"Tuesday"\s*\)',
                'expected' => 'Monday',
                'starter' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        int day = 1;

        switch (day) {
            case 1:
                System.out.println("Monday");
                break;
            case 2:
                // TODO: print "Tuesday"
                break;
            default:
                System.out.println("Other day");
        }
    }
}

JAVA,
                'solution' => <<<'JAVA'
public class Main {
    public static void main(String[] args) {
        int day = 1;

        switch (day) {
            case 1:
                System.out.println("Monday");
                break;
            case 2:
                System.out.println("Tuesday");
                break;
            default:
                System.out.println("Other day");
        }
    }
}

JAVA,
            ],
        ];
    }
}
