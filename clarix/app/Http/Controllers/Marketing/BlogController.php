<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\TechNewsFeed;
use Illuminate\Contracts\View\View;

/**
 * The public /blog page.
 *
 * The only marketing route that is not a plain Route::view, because it is the
 * only one whose content comes from somewhere other than the repository. The
 * feed never throws and never returns anything but a list, so there is nothing
 * to guard here — an empty list is the view's cue to show its fallback.
 */
class BlogController extends Controller
{
    public function __invoke(TechNewsFeed $feed): View
    {
        return view('marketing.resources.blog', [
            'articles' => $feed->articles(),
            'feedConfigured' => $feed->isConfigured(),
        ]);
    }
}
