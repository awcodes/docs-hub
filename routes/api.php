<?php

declare(strict_types=1);

use App\Http\Controllers\Api\RepositoryController;
use Illuminate\Support\Facades\Route;

/*
| Read-only lookups for other sites, aw.codes first among them.
|
| Keyed by GitHub repository rather than hub slug, because the repository is
| the one identifier both registries already share: aw.codes slugs and hub
| slugs disagree, and a mapping kept by hand in either app would drift.
*/

Route::get('/repositories/{owner}/{repository}', RepositoryController::class)
    ->middleware(['throttle:60,1', 'cache.headers:public;max_age=300'])
    ->name('api.repository');
