<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentationType;
use App\Enums\PackageVersionStatus;
use Database\Factories\PackageVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['package_id', 'version', 'git_ref', 'status', 'is_default', 'last_synced_at', 'last_attempted_sync_at', 'source_commit', 'active_snapshot_id', 'documentation_type', 'last_sync_error'])]
class PackageVersion extends Model
{
    /** @use HasFactory<PackageVersionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_default' => false,
    ];

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return BelongsTo<DocumentationSnapshot, $this> */
    public function activeSnapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentationSnapshot::class, 'active_snapshot_id');
    }

    /** @return HasMany<DocumentationSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(DocumentationSnapshot::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PackageVersionStatus::class,
            'documentation_type' => DocumentationType::class,
            'is_default' => 'boolean',
            'last_synced_at' => 'immutable_datetime',
            'last_attempted_sync_at' => 'immutable_datetime',
        ];
    }
}
