<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DocumentationRedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['documentation_snapshot_id', 'source_route', 'destination_route'])]
class DocumentationRedirect extends Model
{
    /** @use HasFactory<DocumentationRedirectFactory> */
    use HasFactory;

    /** @return BelongsTo<DocumentationSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(DocumentationSnapshot::class, 'documentation_snapshot_id');
    }
}
