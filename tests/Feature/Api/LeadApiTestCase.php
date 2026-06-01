<?php

namespace Tests\Feature\Api;

use App\Domain\Auth\Models\TenantApiClient;
use App\Domain\Leads\Models\Lead;
use App\Domain\Pipeline\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class LeadApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TENANT_A = 'aaaaaaaa-0000-0000-0000-000000000001';
    protected const TENANT_B = 'bbbbbbbb-0000-0000-0000-000000000002';

    protected function tokenForUser(string $tenantId = self::TENANT_A, array $abilities = []): string
    {
        $user = User::factory()->create(['tenant_id' => $tenantId]);
        $token = empty($abilities)
            ? $user->createToken('test')
            : $user->createToken('test', $abilities);

        return $token->plainTextToken;
    }

    protected function createStage(string $tenantId, array $attrs = []): PipelineStage
    {
        return PipelineStage::factory()->create(array_merge(['tenant_id' => $tenantId], $attrs));
    }

    protected function createInitialStage(string $tenantId): PipelineStage
    {
        return PipelineStage::factory()->initial()->create(['tenant_id' => $tenantId]);
    }

    protected function createWonStage(string $tenantId): PipelineStage
    {
        return PipelineStage::factory()->terminalWon()->create(['tenant_id' => $tenantId]);
    }

    protected function createLostStage(string $tenantId): PipelineStage
    {
        return PipelineStage::factory()->terminalLost()->create(['tenant_id' => $tenantId]);
    }

    protected function createLead(string $tenantId, array $attrs = []): Lead
    {
        return Lead::factory()->create(array_merge(['tenant_id' => $tenantId], $attrs));
    }

    protected function apiHeaders(array $extra = []): array
    {
        return array_merge(['Accept' => 'application/json'], $extra);
    }
}
