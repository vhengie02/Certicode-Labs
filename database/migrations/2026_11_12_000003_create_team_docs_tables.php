<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared typing on group labs. A team attempt has one document its teammates edit
     * together. team_docs holds what lets them reach each other: the name of the realtime
     * channel and the key their edits are encrypted with. team_doc_updates keeps the edits,
     * as opaque blobs the server never reads, so a teammate who joins late can load them.
     */
    public function up(): void
    {
        Schema::create('team_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('lab_id')->constrained('laboratories')->cascadeOnDelete();
            $table->timestamp('team_started_at');
            $table->string('topic', 64)->unique();
            $table->text('key');
            // Goes up each time the channel and key are replaced (a teammate was removed), so
            // the teammates' extensions know to fetch the new ones
            $table->unsignedInteger('rotation')->default(0);
            // Total length of the stored edits, kept as they come so a size check reads one number
            $table->unsignedBigInteger('payload_chars')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'lab_id', 'team_started_at']);
        });

        Schema::create('team_doc_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_doc_id')->constrained('team_docs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_doc_updates');
        Schema::dropIfExists('team_docs');
    }
};
