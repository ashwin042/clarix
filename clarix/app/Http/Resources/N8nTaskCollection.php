<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Services\N8nTaskQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/**
 * A page of tasks, plus the two figures a Telegram reply needs to describe it.
 *
 * $wrap is null for the reason every other resource in this integration sets
 * it: n8n addresses fields by path in a visual editor, and a 'data.' prefix is
 * one more thing for each downstream node to get wrong. Declaring it on a
 * collection class rather than on N8nTaskResource matters — a resource's $wrap
 * governs one resource, while Resource::collection() builds an
 * AnonymousResourceCollection whose own static $wrap is still Laravel's
 * default, so the payload comes back wrapped despite the resource saying
 * otherwise.
 *
 * The envelope carries three keys rather than a bare array:
 *
 *   tasks      at most N8nTaskQuery::LIMIT of them, newest first
 *   count      how many matched in total, which is *not* count($tasks)
 *   truncated  whether the two differ, so the bot need not compare them
 *
 * count being the true total is the whole reason this class exists. "How many
 * tasks are pending" is one of the three questions the endpoint answers, and a
 * count that stopped at the page size would answer it wrongly while looking
 * entirely correct — the sort of bug that is only ever found by an admin who
 * happens to know the real number.
 *
 * truncated is derived here rather than left to the caller because the
 * comparison is easy to get wrong in an n8n expression, and getting it wrong
 * means a reply that says "you have 50 pending" to someone who has 213.
 */
class N8nTaskCollection extends ResourceCollection
{
    public static $wrap = null;

    /** @var class-string<N8nTaskResource> */
    public $collects = N8nTaskResource::class;

    /**
     * @param  Collection<int, Task>  $resource
     * @param  int  $total  Rows matching the query before the page limit.
     */
    public function __construct($resource, protected int $total)
    {
        parent::__construct($resource);
    }

    /**
     * The whole envelope, built here rather than split across toArray() and
     * with().
     *
     * with() would have been the natural home for `limit`, and it is a trap:
     * ResourceResponse wraps the payload in 'data' whenever a resource has no
     * $wrap *and* returns anything from with() or additional(). So adding one
     * informational key there silently re-wraps the entire response — the exact
     * failure the other collection classes in this integration carry warnings
     * about, arrived at from the opposite direction. Everything the caller sees
     * is assembled in this one method for that reason.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tasks'     => $this->collection->map->toArray($request)->values()->all(),
            'count'     => $this->total,
            'truncated' => $this->total > $this->collection->count(),

            // Stated on the wire so a workflow author reading one response can
            // see the page size without going to the source, and so `truncated`
            // explains itself.
            'limit'     => N8nTaskQuery::LIMIT,
        ];
    }
}
