<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $table = 'jta_therapy_reminders';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('pharmacy', function (Builder $builder): void {
            $pharmacyId = app(\App\Tenancy\CurrentPharmacy::class)->getId();

            if ($pharmacyId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereHas('therapy', fn (Builder $query) => $query->where('pharmacy_id', $pharmacyId));
        });
    }

    public function therapy()
    {
        return $this->belongsTo(Therapy::class, 'therapy_id');
    }
}
