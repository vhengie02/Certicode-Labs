<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class TransferSqliteData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:transfer-sqlite {--sqlite-path=database/database.sqlite : Path to the SQLite database file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transfer records from local SQLite database to the currently configured database (e.g. Supabase PostgreSQL)';

    /**
     * Tables to transfer in foreign key dependency order.
     */
    protected array $tables = [
        'users',
        'competencies',
        'school_classes',
        'class_student',
        'modules',
        'laboratories',
        'groups',
        'group_members',
        'lab_sessions',
        'telemetry_logs',
        'anomalies',
        'student_competencies',
        'certificates',
        'notifications',
        'laboratory_views',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sqlitePath = base_path($this->option('sqlite-path'));

        if (!file_exists($sqlitePath)) {
            $this->error("SQLite database file not found at: {$sqlitePath}");
            return 1;
        }

        $activeConnection = config('database.default');
        $this->info("Current active database connection: [{$activeConnection}]");

        if ($activeConnection === 'sqlite') {
            $this->warn("Warning: Current database connection is still 'sqlite'. Please configure your pgsql/Supabase credentials in .env first.");
            if (!$this->confirm('Do you wish to continue anyway?', false)) {
                return 0;
            }
        }

        $this->info("Opening SQLite source database: {$sqlitePath}");
        $sqlitePdo = new PDO("sqlite:{$sqlitePath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $driver = DB::connection()->getDriverName();
        $this->info("Target driver is: [{$driver}]");

        if ($driver === 'pgsql') {
            $this->info("Disabling PostgreSQL foreign key checks temporarily...");
            DB::statement("SET session_replication_role = 'replica';");
        }

        try {
            foreach ($this->tables as $table) {
                // Check if table exists in SQLite
                $check = $sqlitePdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetch();
                if (!$check) {
                    continue;
                }

                $rows = $sqlitePdo->query("SELECT * FROM \"{$table}\"")->fetchAll();
                $count = count($rows);

                if ($count === 0) {
                    $this->line("<comment>• {$table}:</comment> 0 rows (skipped)");
                    continue;
                }

                $this->info("• {$table}: transferring {$count} rows...");

                foreach ($rows as $row) {
                    // Normalize boolean / json columns if needed
                    foreach ($row as $key => $val) {
                        if ($val === null) {
                            continue;
                        }
                    }

                    DB::table($table)->updateOrInsert(
                        isset($row['id']) ? ['id' => $row['id']] : $row,
                        $row
                    );
                }

                // Reset sequence for PostgreSQL if table has an 'id' column
                if ($driver === 'pgsql' && isset($rows[0]['id'])) {
                    DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 1), true);");
                }

                $this->line("  <info>✓</info> {$table}: {$count} rows transferred.");
            }

            $this->info("\nAll tables transferred successfully!");

        } finally {
            if ($driver === 'pgsql') {
                $this->info("Re-enabling PostgreSQL foreign key checks...");
                DB::statement("SET session_replication_role = 'origin';");
            }
        }

        return 0;
    }
}
