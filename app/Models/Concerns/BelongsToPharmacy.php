<?php

namespace App\Models\Concerns;

use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Builder;
use App\Exceptions\CurrentPharmacyNotResolvedException;

trait BelongsToPharmacy
{
    public static function bootBelongsToPharmacy(): void
    {
        static::addGlobalScope('pharmacy', function (Builder $builder): void {
            $pharmacyId = app(CurrentPharmacy::class)->getId();

            if ($pharmacyId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->where($builder->qualifyColumn('pharmacy_id'), $pharmacyId);
        });

        static::creating(function ($model): void {
            $pharmacyId = app(CurrentPharmacy::class)->getId();

            if ($pharmacyId === null) {
                throw new CurrentPharmacyNotResolvedException();
            }

            $model->pharmacy_id = $pharmacyId;
        });
    }
}
