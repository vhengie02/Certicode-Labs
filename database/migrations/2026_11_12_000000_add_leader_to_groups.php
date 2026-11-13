<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A team's leader is the only member who can submit a group lab. leader_vote is the
     * teammate a member currently wants as leader; enough votes replace the leader.
     */
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->foreignId('leader_vote')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leader_vote');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leader_id');
        });
    }
};
