<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListN8nUnitPeopleRequest;
use App\Http\Requests\Api\ListN8nUnitsRequest;
use App\Http\Resources\N8nDirectoryCollection;
use App\Services\N8nDirectory;
use Illuminate\Http\JsonResponse;

/**
 * The two questions an admin's conversation has to answer before it can file
 * anything, and that a PM's conversation never asks.
 *
 * A PM carries their unit on their user row, so the pipeline already knows
 * where their work goes the moment /resolve answers. An admin belongs to no
 * unit — that is what the role means here — so the bot has to offer them the
 * agency's units, and then that unit's people, and carry both ids into the
 * intake call. These endpoints are the two lists behind those prompts.
 *
 * Read-only, and narrow on purpose: an id and a name, nothing else. They exist
 * to fill two Telegram pickers, not to be a staff directory, and everything not
 * on the wire is a thing that cannot end up in an execution log.
 *
 * Both read as though a person were signed in for the same reason
 * N8nTaskController does — ResolveN8nActor put them there and ran the request
 * inside their organization, which is what makes the unscoped-looking queries
 * in N8nDirectory reach exactly one agency.
 */
class N8nDirectoryController extends Controller
{
    public function __construct(protected N8nDirectory $directory)
    {
    }

    /**
     * The units of the acting admin's own agency.
     */
    public function units(ListN8nUnitsRequest $request): JsonResponse
    {
        return (new N8nDirectoryCollection($this->directory->units()))->response();
    }

    /**
     * The people in one unit who may be handed its work.
     *
     * The unit is resolved by the form request under the acting scope, so
     * another agency's unit is a 404 and its staff are never queried.
     */
    public function unitPeople(ListN8nUnitPeopleRequest $request): JsonResponse
    {
        return (new N8nDirectoryCollection(
            $this->directory->peopleIn($request->unit())
        ))->response();
    }
}
