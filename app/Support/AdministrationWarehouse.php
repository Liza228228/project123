<?php

namespace App\Support;

use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;

class AdministrationWarehouse
{
    public const SUBDIVISION_NAME = 'Администрация';

    public const WAREHOUSE_NAME = 'Администрация офис';

    /** @var list<int> */
    public const ACCESS_ROLE_IDS = [1, 6, 2];

    public static function userCanAccess(?User $user): bool
    {
        return $user?->hasAnyRoleId(self::ACCESS_ROLE_IDS) ?? false;
    }

    public static function subdivisionId(): ?int
    {
        $id = Subdivision::query()
            ->where('name', self::SUBDIVISION_NAME)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function isAdministrationSubdivisionId(int $subdivisionId): bool
    {
        $adminId = self::subdivisionId();

        return $adminId !== null && $adminId === $subdivisionId;
    }

    public static function normalizeWarehouseName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function isReservedWarehouseName(string $name): bool
    {
        return self::normalizeWarehouseName($name) === self::normalizeWarehouseName(self::WAREHOUSE_NAME);
    }

    public static function reservedWarehouseAlreadyExists(): bool
    {
        $adminSubId = self::subdivisionId();
        if ($adminSubId === null) {
            return false;
        }

        return Warehouse::query()
            ->where('subdivision_id', $adminSubId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [self::normalizeWarehouseName(self::WAREHOUSE_NAME)])
            ->exists();
    }

    public static function isAdministrationWarehouse(Warehouse $warehouse): bool
    {
        $adminSubId = self::subdivisionId();
        if ($adminSubId === null) {
            return $warehouse->is_primary;
        }

        return (int) $warehouse->subdivision_id === $adminSubId
            && self::isReservedWarehouseName((string) $warehouse->name);
    }

    public static function isAdministrationWarehouseId(int $warehouseId): bool
    {
        $subdivisionId = Warehouse::query()->whereKey($warehouseId)->value('subdivision_id');

        return $subdivisionId !== null && self::isAdministrationSubdivisionId((int) $subdivisionId);
    }

    /**
     * @param  Builder<Subdivision>  $query
     * @return Builder<Subdivision>
     */
    public static function excludeAdministrationSubdivision(Builder $query): Builder
    {
        $adminId = self::subdivisionId();
        if ($adminId !== null) {
            $query->where('id', '!=', $adminId);
        }

        return $query;
    }

    /**
     * @param  Builder<Warehouse>  $query
     * @return Builder<Warehouse>
     */
    public static function excludeAdministrationWarehouse(Builder $query): Builder
    {
        $adminSubId = self::subdivisionId();
        if ($adminSubId !== null) {
            $query->where('subdivision_id', '!=', $adminSubId);
        }

        return $query;
    }

    public static function resolveSubdivisionWithWarehouses(): ?Subdivision
    {
        return Subdivision::query()
            ->where('name', self::SUBDIVISION_NAME)
            ->with(['warehouses' => fn ($q) => $q->orderBy('name')])
            ->first();
    }

    public static function resolvePrimaryWarehouse(): ?Warehouse
    {
        $adminSubId = self::subdivisionId();
        if ($adminSubId === null) {
            return Warehouse::query()->where('is_primary', true)->orderBy('id')->first();
        }

        return Warehouse::query()
            ->where('subdivision_id', $adminSubId)
            ->where(function (Builder $q): void {
                $q->where('is_primary', true)
                    ->orWhere('name', self::WAREHOUSE_NAME);
            })
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }
}
