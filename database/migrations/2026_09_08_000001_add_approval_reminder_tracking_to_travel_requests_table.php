<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approver reminders had no memory, so once a request passed the threshold the
 * same approver was emailed every single morning. Remember when a request was
 * last chased so the reminder can repeat on a fixed cadence instead.
 *
 * Nothing records when a request landed with its current approver, but nothing
 * needs to: that is the most recent approval action, or the submission itself
 * for the first approver. Comparing this column against that landing time is
 * also what resets the cadence when a request moves up the chain — a stamp from
 * the previous approver is simply older than the new landing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_requests', function (Blueprint $table) {
            $table->timestamp('approval_last_reminded_at')
                ->nullable()
                ->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('travel_requests', function (Blueprint $table) {
            $table->dropColumn('approval_last_reminded_at');
        });
    }
};
