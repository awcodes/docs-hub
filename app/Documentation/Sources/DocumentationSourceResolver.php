<?php

declare(strict_types=1);

namespace App\Documentation\Sources;

use App\Models\Project;

/**
 * Which source a project's documentation comes from.
 *
 * A project mounted under `documentation.local_sources` is read from that
 * checkout; everything else comes from GitHub. Mounting is therefore how a
 * developer previews a documentation change before pushing it, and it is
 * deliberately opt-in per project — in production nothing is mounted and
 * everything comes from the remote.
 */
final readonly class DocumentationSourceResolver
{
    public function __construct(
        private LocalFilesystemDocumentationSource $local = new LocalFilesystemDocumentationSource,
        private GitHubDocumentationSource $github = new GitHubDocumentationSource,
    ) {}

    public function for(Project $project): DocumentationSource
    {
        /** @var array<string, string> $mounted */
        $mounted = config('documentation.local_sources', []);

        if (isset($mounted[$project->slug])) {
            return $this->local;
        }

        return $this->github;
    }
}
