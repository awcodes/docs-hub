<?php

declare(strict_types=1);

namespace App\Documentation\Search;

use App\Models\DocumentationPage;
use App\Models\ProjectVersion;

/**
 * Where synchronization hands pages to search.
 *
 * Defined now and implemented later. The moment after a page has
 * been parsed, with its content already in memory, is the only cheap place to
 * index it — everywhere else means reading the snapshot again. Specifying the
 * seam now means introducing a real backend does not reshape the sync pipeline.
 */
interface DocumentationIndexer
{
    /**
     * Index one page. `$content` is the raw Markdown, already read.
     */
    public function index(DocumentationPage $page, string $content): void;

    /**
     * Drop whatever the given slugs had indexed for this version.
     *
     * Called with the pages a sync removed, so deleted documentation stops
     * appearing in results rather than lingering indefinitely.
     *
     * @param  list<string>  $slugs
     */
    public function forget(ProjectVersion $version, array $slugs): void;
}
