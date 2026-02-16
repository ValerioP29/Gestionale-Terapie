<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_therapies');
        Schema::dropIfExists('jta_patients');
        Schema::dropIfExists('jta_pharmas');

        Schema::create('jta_pharmas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('business_name')->nullable();
        });

        Schema::create('jta_patients', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('patient_id')->nullable();
            $table->string('therapy_title')->nullable();
            $table->timestamps();
        });
    }

    public function test_tenant_scope_isolation_on_patients_and_therapies(): void
    {
        $patientA = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);
        $patientB = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 2, 'first_name' => 'Luigi']);

        $therapyA = Therapy::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'patient_id' => $patientA->id, 'therapy_title' => 'A']);
        $therapyB = Therapy::withoutGlobalScopes()->create(['pharmacy_id' => 2, 'patient_id' => $patientB->id, 'therapy_title' => 'B']);

        app(CurrentPharmacy::class)->setId(1);

        $this->assertCount(1, Patient::all());
        $this->assertSame($patientA->id, Patient::firstOrFail()->id);
        $this->assertCount(1, Therapy::all());
        $this->assertSame($therapyA->id, Therapy::firstOrFail()->id);

        app(CurrentPharmacy::class)->setId(2);

        $this->assertCount(1, Patient::all());
        $this->assertSame($patientB->id, Patient::firstOrFail()->id);
        $this->assertCount(1, Therapy::all());
        $this->assertSame($therapyB->id, Therapy::firstOrFail()->id);
    }

    public function test_standard_find_cannot_retrieve_cross_tenant_record(): void
    {
        $patientA = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);
        $patientB = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 2, 'first_name' => 'Luigi']);

        app(CurrentPharmacy::class)->setId(1);

        $this->assertNotNull(Patient::find($patientA->id));
        $this->assertNull(Patient::find($patientB->id));
    }

    public function test_tenant_scope_returns_zero_results_when_current_pharmacy_is_null(): void
    {
        app(CurrentPharmacy::class)->setId(1);

        $patient = Patient::create(['first_name' => 'Mario']);
        Therapy::create(['patient_id' => $patient->id, 'therapy_title' => 'A']);

        app(CurrentPharmacy::class)->setId(null);

        $this->assertSame(0, Patient::count());
        $this->assertSame(0, Therapy::count());
    }

    public function test_creating_tenantized_model_without_current_pharmacy_throws(): void
    {
        app(CurrentPharmacy::class)->setId(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Current pharmacy not resolved');

        Patient::create(['first_name' => 'Mario']);
    }
}
