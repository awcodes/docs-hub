<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Documentation\Actions\SyncVersions;
use App\Documentation\Data\SyncAttempt;
use App\Models\Project;
use App\Models\ProjectVersion;
use Illuminate\Console\Command;

/**
 * `docs:sync` — pull documentation and publish it.
 *
 * The manual path, and for now the only one. The webhook and the scheduled
 * reconciliation, when they exist, would dispatch the same operation; they
 * decide *when* it runs, never what it does.
 *
 * A failure here is reported and survived. One version failing must not stop
 * the others, because the alternative is a single broken manifest holding up
 * every project's documentation.
 */
final class SyncDocumentationCommand extends Command
{
    protected $signature = 'docs:sync
        {project? : Limit to one project slug}
        {version? : Limit to one version of that project}
        {--force : Publish even when the commit has not changed}';

    protected $description = 'Synchronize documentation from its source and publish it';

    public function handle(SyncVersions $sync): int
    {
        $versions = $this->versions();

        // Naming something that does not exist is an error; finding nothing to
        // do when nothing was named is not. A bare `docs:sync` against an empty
        // registry has succeeded at doing nothing.
        if ($versions === null) {
            return self::FAILURE;
        }

        if ($versions === []) {
            $this->components->warn('No project versions matched.');

            return self::SUCCESS;
        }

        // Reported as each finishes rather than at the end, so a long run says
        // what it is doing while it does it.
        $summary = $sync->handle(
            $versions,
            (bool) $this->option('force'),
            fn (SyncAttempt $attempt): null => $this->report($attempt),
        );

        return $summary->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function report(SyncAttempt $attempt): null
    {
        if (! $attempt->failed()) {
            $this->components->twoColumnDetail($attempt->label(), $attempt->summary());

            return null;
        }

        $this->components->error("{$attempt->label()}: {$attempt->summary()}");

        foreach ($attempt->issues as $issue) {
            $this->components->bulletList([
                $issue->file === null ? $issue->message : "{$issue->file}: {$issue->message}",
            ]);
        }

        return null;
    }

    /**
     * The versions this invocation should synchronize.
     *
     * Null when the arguments named something the registry does not have.
     *
     * @return list<ProjectVersion>|null
     */
    private function versions(): ?array
    {
        $query = ProjectVersion::query()->with('project');

        /** @var string|null $slug */
        $slug = $this->argument('project');

        if ($slug !== null) {
            $project = Project::query()->where('slug', $slug)->first();

            if (! $project instanceof Project) {
                $this->components->error("No project is registered as `{$slug}`.");

                return null;
            }

            $query->where('project_id', $project->getKey());

            /** @var string|null $version */
            $version = $this->argument('version');

            if ($version !== null) {
                if (! $project->versions()->where('version', $version)->exists()) {
                    $this->components->error("`{$slug}` has no version `{$version}`.");

                    return null;
                }

                $query->where('version', $version);
            }
        }

        return $query->get()->all();
    }
}
