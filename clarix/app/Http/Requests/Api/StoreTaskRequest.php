<?php

namespace App\Http\Requests\Api;

use App\Services\TaskCreationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for a task filed over the API.
 *
 * The rules are not written here — they are TaskCreationService::rules(), the
 * same set the create-task screen validates against. Only the parts that are
 * genuinely about *this* transport live in this class: the authorization
 * check, and the unit the code has to be unique within.
 *
 * Note the class this does not extend. App\Http\Requests\StoreTaskRequest is
 * the older, unrouted form request, and it still validates a `description`
 * column that no longer exists; inheriting from it would have imported that
 * bug. It is left alone here and should be deleted separately.
 */
class StoreTaskRequest extends FormRequest
{
    /**
     * Same two conditions the screen enforces in authorizeCreate(): the
     * agency must have granted the permission, and the actor must have a unit
     * to file the work under.
     *
     * The permission is checked here as well as by the token's ability. They
     * are different questions — the ability is what this credential was minted
     * to do, the permission is what the agency currently allows the role to do
     * — and revoking the permission in the Authorization panel has to take
     * effect without anyone having to hunt down and re-mint tokens.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('tasks.create')
            && $user->unit_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return TaskCreationService::rules(
            $this->user()?->unit_id === null ? null : (int) $this->user()->unit_id
        );
    }
}
