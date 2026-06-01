<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\ApiAbility;
use App\Domain\Auth\Models\TenantApiClient;
use App\Domain\Leads\Actions\AssignLeadAction;
use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Actions\MarkLeadLostAction;
use App\Domain\Leads\Actions\MarkLeadWonAction;
use App\Domain\Leads\Actions\RegisterLeadContactAction;
use App\Domain\Leads\Actions\ScheduleFollowupAction;
use App\Domain\Leads\Actions\UpdateLeadStageAction;
use App\Domain\Leads\Enums\CauserType;
use App\Domain\Leads\Models\Lead;
use App\Http\Context\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AssignLeadRequest;
use App\Http\Requests\Api\CreateLeadRequest;
use App\Http\Requests\Api\MarkLeadLostRequest;
use App\Http\Requests\Api\MarkLeadWonRequest;
use App\Http\Requests\Api\RegisterContactRequest;
use App\Http\Requests\Api\ScheduleFollowupRequest;
use App\Http\Requests\Api\UpdateLeadStageRequest;
use App\Http\Resources\LeadResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\IdempotencyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    // ── GET /api/v1/leads ─────────────────────────────────────────────────────

    public function index(Request $request, RequestContext $context): JsonResponse
    {
        if (! $context->hasAbility(ApiAbility::LeadsRead->value)) {
            return ApiResponse::forbidden('You do not have permission to read leads.');
        }

        $query = Lead::with('stage')->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('stage_id')) {
            $query->where('stage_id', $request->string('stage_id'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_user_id', $request->string('assigned_to'));
        }
        if ($request->filled('source_system')) {
            $query->where('source_system', $request->string('source_system'));
        }
        if ($request->filled('source_channel')) {
            $query->where('source_channel', $request->string('source_channel'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('followup_from')) {
            $query->where('followup_at', '>=', $request->string('followup_from'));
        }
        if ($request->filled('followup_to')) {
            $query->where('followup_at', '<=', $request->string('followup_to'));
        }
        if ($request->boolean('overdue')) {
            $query->where('status', 'active')
                ->whereNotNull('followup_at')
                ->where('followup_at', '<', now());
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('customer_phone', 'like', $term);
            });
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $leads   = $query->paginate($perPage);

        return ApiResponse::paginated(
            LeadResource::collection($leads)->toArray($request),
            [
                'current_page' => $leads->currentPage(),
                'per_page'     => $leads->perPage(),
                'total'        => $leads->total(),
                'last_page'    => $leads->lastPage(),
                'from'         => $leads->firstItem(),
                'to'           => $leads->lastItem(),
            ]
        );
    }

    // ── GET /api/v1/leads/{lead} ──────────────────────────────────────────────

    public function show(string $lead, RequestContext $context): JsonResponse
    {
        if (! $context->hasAbility(ApiAbility::LeadsRead->value)) {
            return ApiResponse::forbidden('You do not have permission to read leads.');
        }

        // Lookup explícito para que TenantScope se aplique (context ya fue establecido)
        $leadModel = Lead::findOrFail($lead);
        $leadModel->load([
            'stage',
            'notes'        => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
            'activityLogs' => fn ($q) => $q->orderBy('created_at', 'desc')->limit(10),
        ]);

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── PATCH /api/v1/leads/{lead}/stage ─────────────────────────────────────

    public function updateStage(
        UpdateLeadStageRequest $request,
        string $lead,
        RequestContext $context,
        UpdateLeadStageAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsUpdate->value)) {
            return ApiResponse::forbidden('You do not have permission to update leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── PATCH /api/v1/leads/{lead}/assign ────────────────────────────────────

    public function assign(
        AssignLeadRequest $request,
        string $lead,
        RequestContext $context,
        AssignLeadAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsAssign->value)) {
            return ApiResponse::forbidden('You do not have permission to assign leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));
        $leadModel->load('stage');

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── PATCH /api/v1/leads/{lead}/followup ──────────────────────────────────

    public function followup(
        ScheduleFollowupRequest $request,
        string $lead,
        RequestContext $context,
        ScheduleFollowupAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsUpdate->value)) {
            return ApiResponse::forbidden('You do not have permission to update leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));
        $leadModel->load('stage');

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── POST /api/v1/leads/{lead}/contact ────────────────────────────────────

    public function contact(
        RegisterContactRequest $request,
        string $lead,
        RequestContext $context,
        RegisterLeadContactAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsUpdate->value)) {
            return ApiResponse::forbidden('You do not have permission to update leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));
        $leadModel->load('stage');

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── PATCH /api/v1/leads/{lead}/won ───────────────────────────────────────

    public function won(
        MarkLeadWonRequest $request,
        string $lead,
        RequestContext $context,
        MarkLeadWonAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsUpdate->value)) {
            return ApiResponse::forbidden('You do not have permission to update leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── PATCH /api/v1/leads/{lead}/lost ──────────────────────────────────────

    public function lost(
        MarkLeadLostRequest $request,
        string $lead,
        RequestContext $context,
        MarkLeadLostAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsUpdate->value)) {
            return ApiResponse::forbidden('You do not have permission to update leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $leadModel = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));

        return ApiResponse::success((new LeadResource($leadModel))->toArray(request()));
    }

    // ── POST /api/v1/leads (existente) ───────────────────────────────────────

    public function store(
        CreateLeadRequest $request,
        RequestContext $context,
        CreateLeadAction $createLead,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $tenantId       = $context->tenantId;
        $idempotencyKey = $request->header('Idempotency-Key');
        $validated      = $request->validated();

        $sourceSystem  = $validated['source_system']  ?? $context->sourceSystem;
        $sourceChannel = $validated['source_channel'] ?? $context->sourceChannel;

        if ($context->sourceSystem !== null && isset($validated['source_system'])
            && $validated['source_system'] !== $context->sourceSystem) {
            return ApiResponse::error(
                'source_system does not match the authenticated API client.',
                ['source_system' => ['Must be: '.$context->sourceSystem]],
                422,
            );
        }

        $missing = [];
        if (empty($sourceSystem)) {
            $missing['source_system'] = ['The source_system field is required.'];
        }
        if (empty($sourceChannel)) {
            $missing['source_channel'] = ['The source_channel field is required.'];
        }
        if ($missing) {
            return ApiResponse::validationError($missing);
        }

        $validated['source_system']  = $sourceSystem;
        $validated['source_channel'] = $sourceChannel;

        $requestHash = $idempotency->buildRequestHash(
            $request->method(),
            $request->path(),
            $validated,
        );

        if ($idempotencyKey) {
            $existing = $idempotency->findActiveByKey($idempotencyKey, $tenantId);

            if ($existing) {
                if ($existing->path !== $request->path() || strtoupper($existing->method) !== $request->method()) {
                    return ApiResponse::error(
                        'Idempotency-Key was already used for a different request.',
                        ['idempotency_key' => ['Used for: '.strtoupper($existing->method).' '.$existing->path]],
                        400,
                    );
                }

                $replayBody                              = $existing->response_body;
                $replayBody['data']['idempotent_replay'] = true;
                $replayBody['request_id']                = $context->requestId;

                return response()->json($replayBody, 200)
                    ->header('Idempotent-Replayed', 'true')
                    ->header('X-Request-ID', $context->requestId);
            }
        }

        $externalRefId = $validated['external_reference_id'] ?? null;

        if ($externalRefId !== null) {
            $existingLead = Lead::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('source_system', $sourceSystem)
                ->where('external_reference_id', $externalRefId)
                ->whereNull('deleted_at')
                ->first();

            if ($existingLead) {
                return ApiResponse::error(
                    'A lead with this external_reference_id already exists for this source_system.',
                    ['external_reference_id' => ['Already exists. lead_id: '.$existingLead->id]],
                    409,
                );
            }
        }

        try {
            $lead = $createLead->execute($validated, $tenantId);
        } catch (UniqueConstraintViolationException) {
            $existingLead = Lead::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('source_system', $sourceSystem)
                ->where('external_reference_id', $externalRefId)
                ->whereNull('deleted_at')
                ->first();

            return ApiResponse::error(
                'A lead with this external_reference_id already exists for this source_system.',
                ['external_reference_id' => ['Already exists. lead_id: '.($existingLead?->id ?? 'unknown')]],
                409,
            );
        }

        $lead->load('stage');
        $resource = (new LeadResource($lead, false))->toArray($request);

        $responseBody = [
            'data'       => $resource,
            'request_id' => $context->requestId,
        ];

        if ($idempotencyKey) {
            try {
                $idempotency->store(
                    key:                 $idempotencyKey,
                    tenantId:            $tenantId,
                    requestHash:         $requestHash,
                    method:              $request->method(),
                    path:                $request->path(),
                    sourceSystem:        $sourceSystem,
                    sourceChannel:       $sourceChannel,
                    externalReferenceId: $externalRefId,
                    leadId:              $lead->id,
                    responseStatus:      201,
                    responseBody:        $responseBody,
                );
            } catch (UniqueConstraintViolationException) {
                $saved = $idempotency->findActiveByKey($idempotencyKey, $tenantId);
                if ($saved) {
                    $replayBody                              = $saved->response_body;
                    $replayBody['data']['idempotent_replay'] = true;
                    $replayBody['request_id']                = $context->requestId;

                    return response()->json($replayBody, 200)
                        ->header('Idempotent-Replayed', 'true')
                        ->header('X-Request-ID', $context->requestId);
                }
            }
        }

        return response()->json($responseBody, 201)
            ->header('X-Request-ID', $context->requestId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function causerFromContext(RequestContext $context): array
    {
        $client = $context->client;

        if ($client instanceof User) {
            return [
                'causer_id'            => (string) $client->id,
                'causer_name_snapshot' => $client->name,
                'causer_type'          => CauserType::User,
            ];
        }

        return [
            'causer_id'            => null,
            'causer_name_snapshot' => $client instanceof TenantApiClient ? $client->name : 'Sistema',
            'causer_type'          => CauserType::ApiClient,
        ];
    }
}
