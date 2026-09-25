<?php

declare(strict_types=1);

namespace App\Documentation\Actions;

use App\Documentation\Data\SyncAttempt;
use App\Documentation\Data\SyncSummary;
use App\Documentation\Exceptions\SyncFailed;
use App\Models\ProjectVersion;
use Throwable;

/**
 * Synchronize many versions, surviving each one's failure.
 *
 * `SyncProjectVersion` does one version and throws. This is the loop around it,
 * and it is shared rather than written twice: `docs:sync` and the panel's sync
 * actions must agree about what a failure is, what gets reported and what keeps
 * going, or the same broken manifest reads as two different problems depending
 * on where it was noticed.
 *
 * Nothing here decides *when* a sync runs. The webhook and the scheduled
 * reconciliation, when they exist, would call exactly this.
 */
final readonly class SyncVersions
{
    public function __construct(
        private SyncProjectVersion $sync = new SyncProjectVersion,
    ) {}

    /**
     * @param  iterable<ProjectVersion>  $versions
     * @param  (callable(SyncAttempt): void)|null  $onAttempt  Called as each one finishes, so a
     *                                                         long console run can report as it goes
     *                                                         rather than all at the end.
     */
    public function handle(iterable $versions, bool $force = false, ?callable $onAttempt = null): SyncSummary
    {
        $attempts = [];

        foreach ($versions as $version) {
            $attempt = $this->attempt($version, $force);

            $attempts[] = $attempt;

            if ($onAttempt !== null) {
                $onAttempt($attempt);
            }
        }

        return new SyncSummary($attempts);
    }

    private function attempt(ProjectVersion $version, bool $force): SyncAttempt
    {
        try {
            return new SyncAttempt($version, $this->sync->handle($version, $force));
        } catch (SyncFailed $failure) {
            // Carries its validation issues, which are the whole value of the
            // message: "did not pass validation" without naming the file is
            // not something anyone can act on.
            return new SyncAttempt($version, failure: $failure, issues: $failure->issues);
        } catch (Throwable $failure) {
            // Reported rather than rethrown, so it lands in the application
            // log.
            report($failure);

            return new SyncAttempt($version, failure: $failure);
        }
    }
}
