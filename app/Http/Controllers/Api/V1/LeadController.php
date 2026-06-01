<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Leads\Actions\CreateLeadAction;
use App\Domain\Leads\Models\Lead;
use App\Http\Context\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Http\Responses\ApiResponse;
use App\Services\IdempotencyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;

class LeadController extends Controller
{
    public function store(
        CreateLeadRequest $request,
        RequestContext $context,
        CreateLeadAction $createLead,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $tenantId       = $context->tenantId;
        $idempotencyKey = $request->header('Idempotency-Key');
        $validated      = $request->validated();

        // Resolver source desde el contexto del cliente si no vino en el payload
        $sourceSystem  = $validated['source_system']  ?? $context->sourceSystem;
        $sourceChannel = $validated['source_channel'] ?? $context->sourceChannel;

        // Si el cliente tiene source_system fijo (TenantApiClient), el payload no puede sobreescribirlo
        if ($context->sourceSystem !== null && isset($validated['source_system'])
            && $validated['source_system'] !== $context->sourceSystem) {
            return ApiResponse::error(
                'source_system does not match the authenticated API client.',
                ['source_system' => ['Must be: '.$context->sourceSystem]],
                422,
            );
        }

        // Después de resolver, ambos son obligatorios
        $missing = [];
        if (empty($sourceSystem))  { $missing['source_system']  = ['The source_system field is required.']; }
        if (empty($sourceChannel)) { $missing['source_channel'] = ['The source_channel field is required.']; }
        if ($missing) {
            return ApiResponse::validationError($missing);
        }

        $validated['source_system']  = $sourceSystem;
        $validated['source_channel'] = $sourceChannel;

        // Hash del request para almacenamiento
        $requestHash = $idempotency->buildRequestHash(
            $request->method(),
            $request->path(),
            $validated,
        );

        // ── Nivel 1: Idempotency-Key header ──────────────────────────────────
        if ($idempotencyKey) {
            $existing = $idempotency->findActiveByKey($idempotencyKey, $tenantId);

            if ($existing) {
                // Misma clave pero diferente endpoint → 400
                if ($existing->path !== $request->path() || strtoupper($existing->method) !== $request->method()) {
                    return ApiResponse::error(
                        'Idempotency-Key was already used for a different request.',
                        ['idempotency_key' => ['Used for: '.strtoupper($existing->method).' '.$existing->path]],
                        400,
                    );
                }

                // Replay: devolver respuesta almacenada con flag idempotente
                $replayBody             = $existing->response_body;
                $replayBody['data']['idempotent_replay'] = true;
                $replayBody['request_id']                = $context->requestId;

                // Replay siempre retorna 200 (aunque el original fue 201)
                return response()->json($replayBody, 200)
                    ->header('Idempotent-Replayed', 'true')
                    ->header('X-Request-ID', $context->requestId);
            }
        }

        // ── Nivel 2: Unicidad por datos ───────────────────────────────────────
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

        // ── Crear el lead ─────────────────────────────────────────────────────
        try {
            $lead = $createLead->execute($validated, $tenantId);
        } catch (UniqueConstraintViolationException) {
            // Carrera de datos: el índice unique en leads lo capturó
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

        // ── Construir respuesta ───────────────────────────────────────────────
        $lead->load('stage');
        $resource = (new LeadResource($lead, false))->toArray($request);

        $responseBody = [
            'data'       => $resource,
            'request_id' => $context->requestId,
        ];

        // ── Guardar registro de idempotencia (si se envió clave) ──────────────
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
                // Carrera: otra petición guardó la misma clave primero → devolver ese replay
                $saved = $idempotency->findActiveByKey($idempotencyKey, $tenantId);
                if ($saved) {
                    $replayBody             = $saved->response_body;
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
}
