<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentationType;
use App\Enums\VersionStatus;
use Carbon\CarbonInterface;
use Database\Factories\ProjectVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One documentation version of a project, read from one Git ref.
 *
 * Documentation follows the lifecycle of the code it documents: the `1.x`
 * branch owns the `1.x` documentation, which is what lets a fix be
 * cherry-picked between supported branches without rewriting a URL.
 * A rolling project has exactly one of these.
 *
 * The lower half of this record belongs to synchronization rather than the
 * registry: `active_snapshot` names the published snapshot directory, and it is
 * always read from here rather than implied by what is on disk.
 *
 * @property int $project_id
 * @property string $version
 * @property string $git_ref
 * @property VersionStatus $status
 * @property bool $is_default
 * @property CarbonInterface | null $last_synced_at
 * @property string | null $source_commit
 * @property string | null $active_snapshot
 * @property DocumentationType $documentation_type
 * @property array<string, string> | null $redirects
 * @property list<array{label?: string, page?: string, children?: list<string>}> | null $navigation
 * @property string | null $manifest_title
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class ProjectVersion extends Model
{
    /** @use HasFactory<ProjectVersionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'version',
        'git_ref',
        'status',
        'is_default',
        'last_synced_at',
        'source_commit',
        'active_snapshot',
        'documentation_type',
        'redirects',
        'navigation',
        'manifest_title',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<DocumentationPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentationPage::class);
    }

    /**
     * Whether this version has ever published anything a reader can open.
     *
     * Registered and synchronized are different things: a version exists in the
     * registry the moment someone adds it, and has no snapshot until the first
     * sync succeeds.
     */
    public function isPublished(): bool
    {
        return $this->active_snapshot !== null
            && $this->documentation_type->hasPages();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VersionStatus::class,
            'documentation_type' => DocumentationType::class,
            'redirects' => 'array',
            'navigation' => 'array',
            'is_default' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
