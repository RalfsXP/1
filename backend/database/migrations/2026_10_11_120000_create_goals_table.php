<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // "day" | "week" | "month" | "year" | "custom", and the first day of that period
            // (for a recurring goal: the first occurrence).
            $table->string('period', 8);
            $table->date('period_start');
            // Last day, only for "custom" periods (e.g. this Tuesday → next Friday).
            $table->date('period_end')->nullable();
            $table->string('title');
            $table->text('notes')->nullable();
            // "low" | "high" | "important"
            $table->string('priority', 12)->default('low');
            // Optional deadline earlier than the end of the period (one-off goals only).
            $table->timestamp('due_at')->nullable();
            // Recurrence: every period, or for daily goals only on these ISO weekdays (1 = Monday).
            $table->boolean('repeats')->default(false);
            $table->json('repeat_days')->nullable();
            // Last period start the series still runs on; null = forever.
            $table->date('repeat_until')->nullable();
            // Badge look in the calendar; null falls back to the user's category default.
            $table->string('badge_color', 7)->nullable();
            $table->string('badge_icon', 40)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period', 'period_start']);
        });

        // One row per completed occurrence (a one-off goal has a single occurrence).
        Schema::create('goal_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->date('occurs_on');
            $table->timestamp('completed_at');

            $table->unique(['goal_id', 'occurs_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_completions');
        Schema::dropIfExists('goals');
    }
};
