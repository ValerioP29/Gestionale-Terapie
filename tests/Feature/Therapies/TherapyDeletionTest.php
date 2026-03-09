<?php

namespace Tests\Feature\Therapies;

use App\Filament\Resources\TherapyResource;
use App\Models\Patient;
use App\Models\Therapy;
use App\Models\User;
use App\Policies\TherapyPolicy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TherapyDeletionTest extends TestCase
{
    protected object $currentPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_therapies');
        Schema::dropIfExists('jta_patients');
        Schema::dropIfExists('users');
        Schema::dropIfExists('jta_pharmas');

        Schema::create('jta_pharmas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('business_name')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('pharmacy_id')->nullable();
            $table->timestamps();
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
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $this->currentPharmacy = new class {
            public ?int $id = null;

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(?int $id): void
            {
                $this->id = $id;
            }
        };

        app()->instance(CurrentPharmacy::class, $this->currentPharmacy);
    }

    public function test_authorized_user_can_delete_therapy_with_soft_delete(): void
    {
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia A',
            'status' => 'active',
        ]);

        $user = new User(['name' => 'Pharma 1', 'email' => 'p1@example.com', 'pharmacy_id' => 1]);
        $this->currentPharmacy->setId(1);

        $policy = app(TherapyPolicy::class);

        $this->assertTrue($policy->delete($user, $therapy));

        $therapy->delete();

        $this->assertSoftDeleted('jta_therapies', ['id' => $therapy->id]);
    }

    public function test_non_authorized_user_cannot_delete_other_tenant_therapy(): void
    {
        $user = new User(['name' => 'Pharma 1', 'email' => 'p1@example.com', 'pharmacy_id' => 1]);
        $foreignTherapy = new Therapy(['pharmacy_id' => 2, 'therapy_title' => 'Foreign']);

        $this->currentPharmacy->setId(1);

        $policy = app(TherapyPolicy::class);

        $this->assertFalse($policy->delete($user, $foreignTherapy));
    }

    public function test_deleted_therapy_is_hidden_from_resource_query(): void
    {
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $visible = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Visibile',
            'status' => 'active',
        ]);

        $deleted = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Eliminata',
            'status' => 'active',
        ]);

        $deleted->delete();

        $this->currentPharmacy->setId(1);

        $queryIds = TherapyResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($visible->id, $queryIds);
        $this->assertNotContains($deleted->id, $queryIds);
    }
}
