<?php

namespace App\Modules\Saas\Models;

use Database\Factories\SaasPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'revision', 'name', 'base_price_rupiah', 'features', 'is_active'])]
#[UseFactory(SaasPlanFactory::class)]
class SaasPlan extends Model
{
    /** @use HasFactory<SaasPlanFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'base_price_rupiah' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
