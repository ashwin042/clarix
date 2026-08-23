<?php

namespace App\Http\Requests\Api;

use App\Models\Unit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Who may ask the bot who is in a unit, and which unit they are allowed to ask
 * about.
 *
 * The unit is loaded here rather than by route-model binding, for the reason
 * AttachN8nTaskFilesRequest loads its task here: implicit binding resolves the
 * model before anything has established who is acting, so a bound unit would be
 * fetched with no tenant context and OrganizationScope would filter nothing —
 * another agency's unit id in the path would resolve happily, and this endpoint
 * would hand back the names of its staff. Loaded here, inside the acting-as
 * context ResolveN8nActor established, the scope does the work.
 *
 * The order of the two checks is load-bearing and is asserted by a test. The
 * role is settled first, so a PM is refused identically whether or not the id
 * in the path exists; checking the unit first would turn the 403/404 difference
 * into a way for any linked user to enumerate their agency's unit ids.
 */
class ListN8nUnitPeopleRequest extends FormRequest
{
    protected ?Unit $resolved = null;

    /** Same rule, and the same reasoning, as ListN8nUnitsRequest. */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            'Only an administrator chooses who a task belongs to.'
        );
    }

    /**
     * The unit in the path, under the acting organization's scope.
     *
     * A miss is a 404 rather than a 403, and the distinction is not cosmetic: a
     * unit in another agency and a unit that never existed must be
     * indistinguishable from outside, or the endpoint reports whether an id is
     * in use across the whole platform.
     */
    public function unit(): Unit
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $unit = Unit::query()->whereKey($this->route('unit'))->first();

        if ($unit === null) {
            throw new NotFoundHttpException('No such unit.');
        }

        return $this->resolved = $unit;
    }
}
