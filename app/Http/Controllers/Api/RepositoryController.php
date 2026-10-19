<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepositoryResource;
use App\Models\Project;
use Symfony\Component\HttpFoundation\Response;

/**
 * The registered project behind one GitHub repository.
 *
 * A 404 is an answer, not a failure: it tells the caller the hub does not
 * document this repository. Hidden projects answer the same way, because a
 * project still being set up should not be presented elsewhere as finished.
 */
final class RepositoryController extends Controller
{
    public function __invoke(string $owner, string $repository): RepositoryResource
    {
        // GitHub treats repository names case-insensitively, and the registry
        // was typed by hand — `awcodes/Matinee` is a real entry.
        $project = Project::query()
            ->visible()
            ->whereRaw('lower(repository) = ?', [mb_strtolower("{$owner}/{$repository}")])
            ->first();

        abort_unless($project instanceof Project, Response::HTTP_NOT_FOUND);

        return new RepositoryResource($project);
    }
}
