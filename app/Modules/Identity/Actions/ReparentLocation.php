<?php

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Location;
use App\Modules\Identity\Models\Tenant;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReparentLocation
{
    public function handle(Location $location, ?Location $parent, int $expectedVersion): Location
    {
        if ($parent !== null && $parent->tenant_id !== $location->tenant_id) {
            throw ValidationException::withMessages(['parent_location_id' => 'Lokasi induk harus berada dalam organisasi yang sama.']);
        }

        $connection = $location->getConnection();

        return $connection->transaction(function () use ($location, $parent, $expectedVersion, $connection): Location {
            Tenant::on($connection->getName())->whereKey($location->tenant_id)->lockForUpdate()->firstOrFail();
            $current = Location::on($connection->getName())->where('tenant_id', $location->tenant_id)->whereKey($location->id)->lockForUpdate()->firstOrFail();

            if ($current->version !== $expectedVersion) {
                throw new ConflictHttpException('Versi lokasi sudah berubah. Muat data terbaru sebelum melanjutkan.');
            }

            $ancestorId = $parent?->id;
            $visited = [];

            while ($ancestorId !== null) {
                if ($ancestorId === $current->id || isset($visited[$ancestorId])) {
                    throw ValidationException::withMessages(['parent_location_id' => 'Hierarki lokasi tidak boleh membentuk siklus.']);
                }

                $visited[$ancestorId] = true;
                $ancestorId = Location::on($connection->getName())->where('tenant_id', $location->tenant_id)->whereKey($ancestorId)->firstOrFail()->parent_location_id;
            }

            if ($current->parent_location_id !== $parent?->id) {
                $current->parent_location_id = $parent?->id;
                $current->version++;
                $current->save();
            }

            return $current;
        });
    }
}
