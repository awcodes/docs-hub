<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProjectKind;
use App\Enums\VersioningMode;
use Carbon\CarbonInterface;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One repository whose documentation the hub publishes.
 *
 * The registry is the hub's own list; nothing here is derived from GitHub. A
 * project is registered by a person, and its support policy stays a decision
 * rather than something inferred from branches or Composer metadata.
 *
 * @property string $slug
 * @property string $name
 * @property ProjectKind $kind
 * @property string $repository
 * @property string $docs_path
 * @property string $default_branch
 * @property string | null $description
 * @property string $group
 * @property VersioningMode $versioning_mode
 * @property bool $is_visible
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'kind',
        'repository',
        'docs_path',
        'default_branch',
        'description',
        'group',
        'versioning_mode',
        'is_visible',
    ];

    /** @return HasMany<ProjectVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProjectVersion::class);
    }

    /**
     * The version unversioned URLs resolve to.
     *
     * A project without one cannot answer `/example/installation` at all. That
     * is a registry configuration error, and it is surfaced as one rather than left to fail at request time — so this returns null and
     * lets the caller say so.
     *
     * @return HasOne<ProjectVersion, $this>
     */
    public function defaultVersion(): HasOne
    {
        return $this->hasOne(ProjectVersion::class)->where('is_default', true);
    }

    /**
     * Projects the homepage and the project selector may list.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => ProjectKind::class,
            'versioning_mode' => VersioningMode::class,
            'is_visible' => 'boolean',
        ];
    }
}
