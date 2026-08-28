<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'repository', 'docs_path', 'description', 'group', 'is_visible'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'docs_path' => 'docs',
        'is_visible' => true,
    ];

    /**
     * @return HasMany<PackageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PackageVersion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }
}
