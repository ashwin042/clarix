<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave management, phase 2 of the ERP work.
 *
 * Two tables, not three. A leave_balances table was considered and left out:
 * allocated and used days are both derivable — allocation from the leave
 * type, usage from the approved requests themselves — and a stored copy has to
 * be kept correct on approve, reject, cancel and every future edit path. The
 * first path that forgets leaves a balance that is quietly wrong, which is
 * worse than one that is recomputed. See LeaveBalance for the derivation.
 *
 * organization_id is NOT NULL on both from the start, like attendances: these
 * tables begin empty and every write goes through BelongsToOrganization, so
 * there is no backfill window that would need a nullable column first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('name');

            /*
             * Null means "not tracked" rather than zero. An agency that has no
             * formal allowance for a category still wants the category, and a
             * zero would read as "nobody may ever take this".
             */
            $table->unsignedSmallInteger('default_annual_allowance')->nullable();

            $table->timestamps();

            // Each agency names its own categories, and names them once.
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Restrict, not cascade: a leave type that people have booked
            // against should not be removable out from under their history.
            $table->foreignId('leave_type_id')->constrained('leave_types');

            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');

            // Who decided, and when. Null while the request is still pending.
            // nullOnDelete so removing a former manager does not take the
            // history of their decisions with them.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();

            $table->timestamps();

            // The approval queue reads "this agency, still pending".
            $table->index(['organization_id', 'status']);

            // A person's own history, and the overlap check on submission.
            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
    }
};
