<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Documentation\Data\SnapshotFile;
use App\Documentation\Exceptions\SnapshotFailed;
use App\Documentation\Storage\SnapshotStore;
use App\Documentation\Support\DocumentationUrl;
use App\Models\Project;
use App\Models\ProjectVersion;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an image, video or font out of a published snapshot.
 *
 * Snapshot storage is not publicly readable, so assets come through the
 * application, resolved against the version's published snapshot.
 */
final class DocumentationAssetController extends Controller
{
    public function __construct(
        private readonly SnapshotStore $snapshots = new SnapshotStore,
    ) {}

    public function __invoke(Project $project, string $version, string $path): StreamedResponse
    {
        $record = $project->versions()->where('version', $version)->first();

        abort_if(! $record instanceof ProjectVersion, Response::HTTP_NOT_FOUND);

        try {
            $file = $this->snapshots->openPublished(
                $record,
                $this->assetPath($project, $path),
                $this->mimeType($path),
            );
        } catch (SnapshotFailed) {
            // A path the store refuses to resolve — one climbing out of the
            // snapshot, typically. To a reader that is a missing asset, not a
            // server fault, and saying so tells an attacker nothing either.
            abort(Response::HTTP_NOT_FOUND);
        }

        abort_if(! $file instanceof SnapshotFile, Response::HTTP_NOT_FOUND);

        return $this->stream($file);
    }

    /**
     * Where the request's path sits inside the snapshot.
     *
     * Built from the project's own `docs_path` and the fixed asset root, with
     * the request contributing only the part below them. Nothing is served by
     * joining request input onto a storage path, and the store refuses a
     * relative path that tries to climb anyway.
     */
    private function assetPath(Project $project, string $path): string
    {
        return implode('/', [
            mb_trim($project->docs_path, '/'),
            DocumentationUrl::ASSET_ROOT,
            mb_trim($path, '/'),
        ]);
    }

    /**
     * The media type this extension is registered as.
     *
     * An unlisted extension is a 404 rather than a guess. Serving from an
     * allow-list is what stops a repository deciding what type its files are
     * served as on a shared origin.
     */
    private function mimeType(string $path): string
    {
        /** @var array<string, string> $types */
        $types = config('documentation.assets.types', []);

        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(isset($types[$extension]), Response::HTTP_NOT_FOUND);

        return $types[$extension];
    }

    private function stream(SnapshotFile $file): StreamedResponse
    {
        /** @var int $maxAge */
        $maxAge = config('documentation.assets.max_age', 3600);

        return response()->stream(function () use ($file): void {
            fpassthru($file->stream);

            if (is_resource($file->stream)) {
                fclose($file->stream);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => $file->mimeType,
            'Content-Length' => (string) $file->size,

            // Not `immutable`: the URL names a version rather than a commit,
            // so a later sync can publish different bytes at the same URL.
            'Cache-Control' => "public, max-age={$maxAge}",

            // An SVG is a document, and a document on this origin can carry
            // script. As an `<img>` source it never executes, but a reader who
            // opens the asset URL directly would be navigating to it — so the
            // response denies it everything, and `nosniff` keeps the declared
            // type the served type.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
