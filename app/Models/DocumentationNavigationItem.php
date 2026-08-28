<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentationNavigationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['documentation_snapshot_id', 'parent_id', 'title', 'route_path', 'depth', 'sort_order', 'position_path'])]
class DocumentationNavigationItem extends Model
{
    /** @use HasFactory<DocumentationNavigationItemFactory> */
    use HasFactory;

    /** @return BelongsTo<DocumentationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentationSnapshot::class, 'documentation_snapshot_id');
    }

    /** @return BelongsTo<DocumentationNavigationItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<DocumentationNavigationItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
