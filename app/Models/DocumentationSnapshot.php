<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentationSnapshotState;
use App\Enums\DocumentationType;
use Database\Factories\DocumentationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $package_version_id
 * @property string $source_commit
 * @property string $storage_disk
 * @property string $storage_prefix
 * @property DocumentationType $documentation_type
 * @property DocumentationSnapshotState $state
 */
#[Fillable(['package_version_id', 'source_commit', 'storage_disk', 'storage_prefix', 'documentation_type', 'state', 'published_at'])]
class DocumentationSnapshot extends Model
{
    /** @use HasFactory<DocumentationSnapshotFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'state' => 'building',
    ];

    /** @return BelongsTo<PackageVersion, $this> */
    public function packageVersion(): BelongsTo
    {
        return $this->belongsTo(PackageVersion::class);
    }

    /** @return HasMany<DocumentationPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentationPage::class);
    }

    /** @return HasMany<DocumentationSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(DocumentationSection::class);
    }

    /** @return HasMany<DocumentationRedirect, $this> */
    public function redirects(): HasMany
    {
        return $this->hasMany(DocumentationRedirect::class);
    }

    /** @return HasMany<DocumentationNavigationItem, $this> */
    public function navigationItems(): HasMany
    {
        return $this->hasMany(DocumentationNavigationItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'documentation_type' => DocumentationType::class,
            'state' => DocumentationSnapshotState::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
