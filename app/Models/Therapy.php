<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPharmacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Therapy extends Model
{
    use BelongsToPharmacy;
    use HasFactory;
    use SoftDeletes;

    protected $table = 'jta_therapies';

    protected $fillable = [
        'pharmacy_id',
        'patient_id',
        'therapy_title',
        'therapy_description',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'pharmacy_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(TherapyReminder::class, 'therapy_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(TherapyFollowup::class, 'therapy_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(TherapyReport::class, 'therapy_id');
    }

    public function manualFollowups(): HasMany
    {
        return $this->followups()->where('entry_type', 'followup');
    }

    public function checks(): HasMany
    {
        return $this->followups()->where('entry_type', 'check');
    }

    public function conditionSurveys(): HasMany
    {
        return $this->hasMany(TherapyConditionSurvey::class, 'therapy_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(TherapyConsent::class, 'therapy_id');
    }

    public function latestConsent(): HasOne
    {
        return $this->hasOne(TherapyConsent::class, 'therapy_id')
            ->ofMany('signed_at', 'max');
    }

    public function checklistQuestions(): HasMany
    {
        return $this->hasMany(TherapyChecklistQuestion::class, 'therapy_id')
            ->where('pharmacy_id', $this->pharmacy_id)
            ->orderBy('sort_order');
    }

    public function checklistAnswers(): HasManyThrough
    {
        return $this->hasManyThrough(
            TherapyChecklistAnswer::class,
            TherapyFollowup::class,
            'therapy_id',
            'followup_id',
            'id',
            'id'
        );
    }

    public function chronicCare(): HasMany
    {
        return $this->hasMany(TherapyChronicCare::class, 'therapy_id');
    }

    public function latestSurvey(): HasOne
    {
        return $this->hasOne(TherapyConditionSurvey::class, 'therapy_id')
            ->ofMany('compiled_at', 'max');
    }

    public function currentChronicCare(): HasOne
    {
        return $this->hasOne(TherapyChronicCare::class, 'therapy_id')->latestOfMany();
    }

    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(
            Assistant::class,
            'jta_therapy_assistant',
            'therapy_id',
            'assistant_id'
        )
            ->withPivot(['role', 'preferences_json', 'consents_json', 'contact_channel'])
            ->withTimestamps();
    }
}
