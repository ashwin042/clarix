<?php

namespace App\Http\Requests\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Who may ask the bot for the agency's units.
 *
 * Only an admin, and the reason is that nobody else has a question to ask. A PM
 * carries their unit on their own row, so the endpoint would be telling them
 * something the intake endpoint is going to ignore anyway — and handing them
 * the agency's unit names is a small disclosure with nothing on the other side
 * of it.
 *
 * Refused rather than answered with an empty list, which was the alternative.
 * An empty list is a legitimate answer meaning "your agency has no units", and
 * a workflow branching on `length === 0` would show a PM that message instead
 * of a bug. A 403 is unambiguous in a way an empty array is not.
 *
 * Deliberately isAdmin() rather than reachesEveryUnit(). A supervisor also
 * belongs to no unit and would need this to file anything, but the intake
 * endpoint does not accept their targeting either — opening the directory
 * without the write would be a picker leading to a 403. The two move together
 * or not at all.
 *
 * No rules(): chat_id is the only input, and it was validated and consumed by
 * ResolveN8nActor before this class existed.
 */
class ListN8nUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /**
     * Said plainly, because it is read in a Telegram reply by somebody who
     * cannot see the API and whose next question would otherwise be "why".
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            'Only an administrator chooses a unit. Your tasks are filed under your own.'
        );
    }
}
