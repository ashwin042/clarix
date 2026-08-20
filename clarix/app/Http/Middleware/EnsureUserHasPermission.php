<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route on a permission from the Authorization panel rather than on a
 * literal role name.
 *
 * The distinction matters because 'role:admin' and the panel were answering
 * the same question differently. The sidebar offered a link the moment a role
 * was granted units.view, and the route behind it then refused anyone who was
 * not literally an admin — the grant saved, the screen stayed shut, and the
 * only visible symptom was a 403 on a link the application had just drawn.
 *
 * Several permissions may be named, and holding any one of them is enough,
 * matching how EnsureUserHasRole treats a list of roles. Admins pass
 * everything: User::hasPermission() short-circuits for them, so replacing a
 * role gate with a permission gate never narrows what an admin can reach.
 */
class EnsureUserHasPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
