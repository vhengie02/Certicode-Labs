<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * A student may have one open (in_progress) attempt per lab. Checking that in code alone
     * let two starts at the same moment both create one. Before the index can exist, any
     * student who already has several open attempts on a lab keeps the newest; the older
     * ones are marked abandoned (logged), as starting a lab does with an expired attempt.
     */
    public function up(): void
    {
        $duplicates = DB::table('lab_sessions')
            ->select('lab_id', 'user_id', DB::raw('max(id) as keep_id'))
            ->where('status', 'in_progress')
            ->whereNotNull('user_id')
            ->groupBy('lab_id', 'user_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $ids = DB::table('lab_sessions')
                ->where('lab_id', $duplicate->lab_id)
                ->where('user_id', $duplicate->user_id)
                ->where('status', 'in_progress')
                ->where('id', '<>', $duplicate->keep_id)
                ->pluck('id');

            DB::table('lab_sessions')->whereIn('id', $ids)->update(['status' => 'abandoned', 'ended_at' => now(), 'updated_at' => now()]);
            Log::warning("Abandoned duplicate open attempts {$ids->implode(', ')} (kept {$duplicate->keep_id}) for lab {$duplicate->lab_id}, user {$duplicate->user_id}.");
        }

        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS lab_sessions_one_open_attempt ON lab_sessions (lab_id, user_id) WHERE status = 'in_progress'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lab_sessions_one_open_attempt');
    }
};
