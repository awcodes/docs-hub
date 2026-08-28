<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentationPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['documentation_snapshot_id', 'source_path', 'slug', 'route_path', 'title', 'description', 'headings', 'content_hash', 'is_listed', 'navigation_order', 'source_updated_at'])]
class DocumentationPage extends Model
{
    /** @use HasFactory<DocumentationPageFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'headings' => '[]',
        'is_listed' => false,
    ];

    /** @return BelongsTo<DocumentationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentationSnapshot::class, 'documentation_snapshot_id');
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
            'is_listed' => 'boolean',
            'source_updated_at' => 'immutable_datetime',
        ];
    }
}
