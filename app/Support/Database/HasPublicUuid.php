<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

trait HasPublicUuid
{
    use HasUuids;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
