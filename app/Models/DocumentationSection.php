<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentationSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One heading section of one page, which is what search matches against.
 *
 * The granularity is deliberate: a page-level record dilutes
 * relevance and tells a reader nothing about *where* in a long page their term
 * appears. A section-level record makes the breadcrumb a property of the index
 * rather than something reconstructed at display time, and lets a result
 * deep-link straight to the heading anchor.
 *
 * @property int $documentation_page_id
 * @property int $project_version_id
 * @property string | null $heading
 * @property string | null $anchor
 * @property int $level
 * @property int $position
 * @property string $content
 */
class DocumentationSection extends Model
{
    /** @use HasFactory<DocumentationSectionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'documentation_page_id',
        'project_version_id',
        'heading',
        'anchor',
        'level',
        'position',
        'content',
    ];

    /** @return BelongsTo<DocumentationPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(DocumentationPage::class, 'documentation_page_id');
    }

    /** @return BelongsTo<ProjectVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }
}
