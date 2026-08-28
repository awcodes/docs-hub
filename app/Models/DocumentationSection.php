<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentationSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['documentation_snapshot_id', 'documentation_page_id', 'heading_path', 'heading', 'anchor', 'body', 'sort_order'])]
class DocumentationSection extends Model
{
    /** @use HasFactory<DocumentationSectionFactory> */
    use HasFactory;

    /** @return BelongsTo<DocumentationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentationSnapshot::class, 'documentation_snapshot_id');
    }

    /** @return BelongsTo<DocumentationPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(DocumentationPage::class, 'documentation_page_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'heading_path' => 'array',
        ];
    }
}
