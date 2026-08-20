<?php

namespace App\Services;

use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The states a payroll record moves through, and the moves that are legal.
 *
 *   draft     -> finalized    the figures stop changing
 *   finalized -> paid         the money went out, recorded after the fact
 *   finalized -> draft        a deliberate correction
 *   paid      -> (nothing)    terminal
 *
 * Kept out of the Livewire component so the rules hold for anything that
 * touches a record later — a console command, an import, a second screen — and
 * so that "paid is final" is stated once rather than implied by which buttons
 * happen to be drawn.
 *
 * Paid is terminal on purpose. Once an agency has recorded that money left,
 * editing the figure it left as would make the record disagree with the
 * payment it describes; the correction belongs in the following month.
 */
class PayrollLifecycle
{
    /**
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'draft'     => ['finalized'],
        'finalized' => ['paid', 'draft'],
        'paid'      => [],
    ];

    public function canTransition(PayrollRecord $record, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$record->status] ?? [], true);
    }

    /**
     * @throws ValidationException
     */
    public function transition(PayrollRecord $record, string $to, User $actor): PayrollRecord
    {
        if (! array_key_exists($to, PayrollRecord::STATUSES)) {
            throw ValidationException::withMessages(['payroll' => 'Unknown payroll status.']);
        }

        if (! $this->canTransition($record, $to)) {
            throw ValidationException::withMessages([
                'payroll' => $record->isPaid()
                    ? 'A paid record cannot be changed. Correct it in the following month instead.'
                    : "A {$record->status} record cannot become {$to}.",
            ]);
        }

        $record->status = $to;

        // paid_at is the record of when the money went out, so it is set on
        // the way to paid and cleared if the record is ever walked back.
        $record->paid_at = $to === 'paid' ? now() : null;

        $record->save();

        return $record;
    }
}
