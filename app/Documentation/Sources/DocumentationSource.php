<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Documentation\Exceptions\DocumentationSourceFailed;
use App\Models\Project;
use App\Models\ProjectVersion;

/**
 * Where a version's documentation comes from.
 *
 * The whole point of the abstraction is that synchronization never learns which
 * one it is holding. Everything above this — manifest parsing,
 * snapshots, rendering, routing, search — is the same work whether the bytes
 * arrived over HTTP or came off a disk two directories away, and that is what
 * lets all of it be built and tested before a token exists.
 *
 * `App\Documentation` must not reach past this to Saloon or the GitHub
 * integration. An abstraction with no architecture test asserting the
 * dependency direction is a naming convention.
 */
interface DocumentationSource
{
    /**
     * The identity of the documentation this source would hand over right now.
     *
     * Snapshots are scoped by this value, so it has to change
     * exactly when the documentation does — that is what makes an unchanged
     * sync a no-op and a changed one a new snapshot.
     *
     * @throws DocumentationSourceFailed
     */
    public function resolveCommit(Project $project, ProjectVersion $version): string;

    /**
     * Place the version's documentation into `$destination`, repository-shaped.
     *
     * What lands there is the project's `docs_path` directory and the root
     * `README.md`, at those relative paths and nothing else — which is what the
     * caller needs to resolve the documentation type, `docs/` taking precedence
     * over the README.
     *
     * @throws DocumentationSourceFailed
     */
    public function retrieve(Project $project, ProjectVersion $version, string $destination): void;
}
