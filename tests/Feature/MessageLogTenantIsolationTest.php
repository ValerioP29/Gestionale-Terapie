<?php

namespace Tests\Feature;

use App\Filament\Resources\MessageLogResource;
use App\Models\MessageLog;
use App\Models\User;
use App\Policies\MessageLogPolicy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageLogTenantIsolationTest extends TestCase
{
    protected object $currentPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('message_logs');
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

        Schema::create('message_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('pharma_id');
            $table->string('to', 30);
            $table->text('body');
            $table->string('status', 20)->default('queued');
            $table->string('provider', 20)->default('baileys');
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

    public function test_resource_query_returns_only_current_tenant_logs(): void
    {
        $logTenantOne = MessageLog::query()->create([
            'pharma_id' => 1,
            'to' => '+39000001',
            'body' => 'A',
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        MessageLog::query()->create([
            'pharma_id' => 2,
            'to' => '+39000002',
            'body' => 'B',
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        $this->currentPharmacy->setId(1);

        $this->assertCount(1, MessageLogResource::getEloquentQuery()->get());
        $this->assertSame($logTenantOne->id, MessageLogResource::getEloquentQuery()->firstOrFail()->id);
        $this->assertNull(MessageLogResource::getEloquentQuery()->find(2));
    }

    public function test_policy_denies_view_of_other_tenant_record(): void
    {
        $userTenantOne = new User([
            'name' => 'Tenant One',
            'email' => 'one@example.com',
            'pharmacy_id' => 1,
        ]);

        $ownLog = new MessageLog([
            'pharma_id' => 1,
            'to' => '+39000001',
            'body' => 'own',
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        $foreignLog = new MessageLog([
            'pharma_id' => 2,
            'to' => '+39000002',
            'body' => 'foreign',
            'status' => 'queued',
            'provider' => 'baileys',
        ]);

        $this->currentPharmacy->setId(1);

        $policy = app(MessageLogPolicy::class);

        $this->assertTrue($policy->viewAny($userTenantOne));
        $this->assertTrue($policy->view($userTenantOne, $ownLog));
        $this->assertFalse($policy->view($userTenantOne, $foreignLog));
    }
}
