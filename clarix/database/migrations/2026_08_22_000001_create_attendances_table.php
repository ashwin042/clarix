<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attendance record per person per day.
 *
 * organization_id lands NOT NULL immediately, unlike every tenant column added
 * earlier this session. Those had to arrive nullable because they were bolted
 * onto tables already holding years of rows that needed backfilling first.
 * This table starts empty and every write goes through
 * BelongsToOrganization, so there is no window in which an unowned row could
 * exist and no reason to leave the door open.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // Restrict on delete, matching every other tenant table: an
            // organization holding real records should never be removable by
            // accident.
            $table->foreignId('organization_id')->constrained('organizations');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('date');

            /*
             * Both nullable. An admin marking someone absent or on leave is
             * recording a real day with no clock-in at all, so a NOT NULL
             * clock_in would force a fictitious time onto exactly the rows
             * that mean "this person was not here".
             */
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();

            $table->enum('status', ['present', 'absent', 'half_day', 'on_leave'])
                ->default('present');

            $table->text('notes')->nullable();

            $table->timestamps();

            // One record per person per day. A user belongs to exactly one
            // organization, so this is already stricter than including the
            // organization would make it.
            $table->unique(['user_id', 'date']);

            // The admin day view reads "this organization, this date", which
            // is the only query on this table that is not keyed by user.
            $table->index(['organization_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
