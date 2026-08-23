<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The attachments of one submission, as a bare JSON array.
 *
 * A class of its own because $wrap does not travel the way it looks like it
 * should: setting it to null on N8nTaskFileResource governs a single resource,
 * while `Resource::collection()` builds an AnonymousResourceCollection whose
 * own static $wrap is still Laravel's 'data'. The result was a payload wrapped
 * in 'data' despite the resource saying otherwise — the kind of mismatch that
 * is invisible until a workflow node reads null.
 *
 * Declaring the collection explicitly is the fix, and it keeps the intent
 * stated in one place rather than depending on inherited statics.
 */
class N8nTaskFileCollection extends ResourceCollection
{
    public static $wrap = null;

    /** @var class-string<N8nTaskFileResource> */
    public $collects = N8nTaskFileResource::class;
}
