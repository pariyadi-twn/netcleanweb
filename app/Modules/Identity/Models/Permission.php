<?php

namespace App\Modules\Identity\Models;

use App\Support\Database\HasPublicUuid;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'description'])]
#[UseFactory(PermissionFactory::class)]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use HasPublicUuid;
}
