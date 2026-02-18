<?php

namespace App\Models;

use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class Assistant extends Model
{
    use HasFactory;

    protected $table = 'jta_assistants';

    protected $fillable = [
        'pharma_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'preferred_contact',
        'type',
        'relation_to_patient',
        'notes',
        'extra_info',
    ];

    protected $casts = [
        'extra_info' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('pharmacy', function (Builder $builder): void {
            $pharmacyId = app(CurrentPharmacy::class)->getId();

            if ($pharmacyId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->qualifyColumn('pharma_id'), $pharmacyId);
        });

        static::creating(function (Assistant $assistant): void {
            $pharmacyId = app(CurrentPharmacy::class)->getId();

            if ($pharmacyId === null) {
                throw new RuntimeException('Current pharmacy not resolved');
            }

            $assistant->pharma_id = $pharmacyId;
        });
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharma_id');
    }
}
