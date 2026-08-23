<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * A directory list, as a bare JSON array.
 *
 * A class of its own for the same reason N8nTaskFileCollection is one: $wrap
 * does not travel the way it looks like it should. Setting it to null on
 * N8nDirectoryEntryResource governs a single resource, while
 * `Resource::collection()` builds an AnonymousResourceCollection whose own
 * static $wrap is still Laravel's 'data' — so the payload comes back wrapped
 * despite the resource saying otherwise, and the mismatch is invisible until a
 * workflow node reads null.
 */
class N8nDirectoryCollection extends ResourceCollection
{
    public static $wrap = null;

    /** @var class-string<N8nDirectoryEntryResource> */
    public $collects = N8nDirectoryEntryResource::class;
}
