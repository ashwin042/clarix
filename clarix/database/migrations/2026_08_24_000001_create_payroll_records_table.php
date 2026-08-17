<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll, phase 3 of the ERP work.
 *
 * Record-keeping, deliberately. There is no tax logic here, nothing derived
 * from attendance or hours, and no payment integration: an admin writes down
 * what somebody is being paid for a month, and later notes that it went out.
 * The money moves elsewhere.
 *
 * The month is a date pinned to the first of the month rather than a
 * year/month pair. One column instead of two, it sorts and ranges with plain
 * date comparisons, and the unique key below reads naturally. Normalising to
 * the 1st is the model's job — see PayrollRecord::setMonthAttribute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations');

            // Whose pay this is. Cascade: if the person is removed, their
            // payroll history goes with them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('month');

            /*
             * Decimal, not float. Money compared or summed as a float is money
             * that disagrees with itself; 12 digits with 2 decimal places
             * covers any figure an agency will enter without loss.
             */
            $table->decimal('base_amount', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);

            // Stored rather than computed at read time, so a finished record
            // keeps the figure it was finalised with. PayrollRecord recomputes
            // it on every save, so it cannot drift from base minus deductions.
            $table->decimal('net_amount', 12, 2);

            $table->enum('status', ['draft', 'finalized', 'paid'])->default('draft');
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();

            // Who entered it. nullOnDelete so removing a former admin does not
            // take the payroll history they recorded with them.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // One record per person per month.
            $table->unique(['user_id', 'month']);

            // The management screen reads "this agency, this month".
            $table->index(['organization_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_records');
    }
};
