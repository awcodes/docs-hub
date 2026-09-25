<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Documentation\Actions\ValidateDocumentation;
use App\Documentation\Data\ValidationIssue;
use Illuminate\Console\Command;

/**
 * `docs:validate` — the pre-merge check that makes strict publication fair.
 *
 * Its real home is each documented repository's CI, where a mistyped
 * navigation entry costs a pull request comment. Without it the author finds
 * out through hub-side monitoring, after the change has merged and quietly
 * stopped every documentation update on that branch — the failure landing as
 * far as possible from the person who caused it.
 *
 * It therefore takes a path and needs nothing else: no database, no network, no
 * registry. That is what lets it run as a pull request check inside each
 * documented repository, or move into a standalone package later.
 */
final class ValidateDocumentationCommand extends Command
{
    protected $signature = 'docs:validate
        {path? : The documentation root to check, defaulting to ./docs}';

    protected $description = 'Check a documentation root against the repository contract';

    public function handle(ValidateDocumentation $validate): int
    {
        $root = $this->argument('path') ?? getcwd().'/docs';

        $result = $validate->handle((string) $root);

        foreach ($result->errors() as $issue) {
            $this->components->error($this->describe($issue));
        }

        foreach ($result->warnings() as $issue) {
            $this->components->warn($this->describe($issue));
        }

        if ($result->failed()) {
            $this->components->error(sprintf(
                '%s failed validation with %d error(s).',
                $root,
                count($result->errors()),
            ));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s is valid%s.',
            $root,
            $result->warnings() === []
                ? ''
                : sprintf(' with %d warning(s)', count($result->warnings())),
        ));

        return self::SUCCESS;
    }

    /** Filename first: it is the first thing an author reading CI output needs. */
    private function describe(ValidationIssue $issue): string
    {
        return $issue->file === null
            ? $issue->message
            : "{$issue->file}: {$issue->message}";
    }
}
