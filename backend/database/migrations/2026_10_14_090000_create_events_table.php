<?php

use App\Support\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            // First (or only) occurrence; repeating events recur from here on.
            $table->date('date');
            // Optional time of day ("HH:MM"); null = all day.
            $table->string('time', 5)->nullable();
            // null = once, else week | month | year (a birthday is "year").
            $table->string('repeat', 8)->nullable();
            // Own badge; null = the user's default event badge.
            $table->string('badge_color', 7)->nullable();
            $table->string('badge_icon', 32)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });

        Rls::enable('events');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
