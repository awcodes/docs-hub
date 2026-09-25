<?php

declare(strict_types=1);

use App\Http\Controllers\DocumentationAssetController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', ProjectController::class)->name('documentation.home');

/*
| Documentation sits at the root of the site, so `{project}` shares its
| position with every other top-level path. The constraint below excludes the
| paths the application has already spoken for, rather than leaving it to the
| order service providers happen to register routes in.
|
| `{path}` is greedy because a page reference may be several segments deep and
| the version segment may or may not be there — deciding which is which is the
| registry's job, not the router's.
*/

/** @var list<string> $reserved */
$reserved = config('documentation.reserved_paths', []);

// Not a prefix test: `admin` is reserved, `administration` is a perfectly good
// project. The segment has to end where the reserved word does.
$notReserved = '(?!(?:'.implode('|', array_map(preg_quote(...), $reserved)).')(?:/|$))[^/]+';

Route::get('/assets/{project}/{version}/{path}', DocumentationAssetController::class)
    ->where('path', '.*')
    ->name('documentation.asset');

Route::where(['project' => $notReserved])->group(function (): void {
    Route::get('/{project}', DocumentationController::class)
        ->name('documentation.project');

    Route::get('/{project}/{path}', DocumentationController::class)
        ->where('path', '.*')
        ->name('documentation.page');
});
