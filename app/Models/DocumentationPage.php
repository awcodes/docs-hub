<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DocumentationPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The indexed metadata for one synchronized page.
 *
 * Rendered Markdown lives in the snapshot on disk; this is what has to be
 * queryable — what search reads, and what lets a table of contents render
 * without reparsing the document on every request.
 *
 * A page may exist here without appearing in `docs.yml` navigation. That is not
 * an error: an unlisted page stays routable and searchable while being absent
 * from the sidebar, which is what keeps half-written documentation previewable.
 *
 * @property int $project_version_id
 * @property string $source_path
 * @property string $slug
 * @property string $title
 * @property string | null $description
 * @property list<array{level: int, title: string, anchor: string}> | null $headings
 * @property string $content_hash
 * @property CarbonInterface | null $source_updated_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class DocumentationPage extends Model
{
    /** @use HasFactory<DocumentationPageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'project_version_id',
        'source_path',
        'slug',
        'title',
        'description',
        'headings',
        'content_hash',
        'source_updated_at',
    ];

    /** @return BelongsTo<ProjectVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }

    /** @return HasMany<DocumentationSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(DocumentationSection::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'headings' => 'array',
            'source_updated_at' => 'datetime',
        ];
    }
}
