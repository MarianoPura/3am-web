<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Projects showcase.
 *
 * Reads config/projects.php today; moves to the projects table in Phase 3 with
 * the same shape, so the template will not need rewriting.
 */
final class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $projects = (array) config('projects.projects', []);

        // Build the filter from the categories actually present, ordered by the
        // configured preference. A filter offering a category with nothing
        // behind it is a dead end.
        $present = array_values(array_unique(array_filter(
            array_map(static fn (array $p): string => (string) ($p['category'] ?? ''), $projects)
        )));

        $preferred  = (array) config('projects.categories', []);
        $categories = array_values(array_merge(
            array_intersect($preferred, $present),
            array_diff($present, $preferred)
        ));

        return $this->render('pages.projects', [
            'projects'   => $projects,
            'categories' => $categories,
            'company'    => config('app.company'),
        ])->cacheFor(300);
    }
}
