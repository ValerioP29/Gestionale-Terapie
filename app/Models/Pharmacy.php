<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Pharmacy extends Model
{
    use HasFactory;

    protected $table = 'jta_pharmas';

    protected $fillable = [
        'email',
        'slug_name',
        'slug_url',
        'phone_number',
        'password',
        'status_id',
        'business_name',
        'nice_name',
        'city',
        'address',
        'latlng',
        'description',
        'logo',
        'working_info',
        'prompt',
        'img_avatar',
        'img_cover',
        'img_bot',
        'is_deleted',
        'status',
        'created_at',
        'updated_at',
        'last_access',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'last_access' => 'datetime',
    ];

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class, 'pharmacy_id');
    }

    public function therapies(): HasMany
    {
        return $this->hasMany(Therapy::class, 'pharmacy_id');
    }

    public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class, 'pharmacy_id');
    }

    public function assistantsLegacy(): HasMany
    {
        return $this->hasMany(Assistant::class, 'pharma_id');
    }

    public function reminders(): HasManyThrough
    {
        return $this->hasManyThrough(
            TherapyReminder::class,
            Therapy::class,
            'pharmacy_id',
            'therapy_id',
            'id',
            'id'
        );
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TherapyReport::class, 'pharmacy_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(TherapyFollowup::class, 'pharmacy_id');
    }
}
