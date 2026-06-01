<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\ApiAbility;
use App\Domain\Auth\Models\TenantApiClient;
use App\Domain\Leads\Actions\CreateLeadNoteAction;
use App\Domain\Leads\Enums\CauserType;
use App\Domain\Leads\Models\Lead;
use App\Http\Context\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateLeadNoteRequest;
use App\Http\Resources\LeadNoteResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadNoteController extends Controller
{
    // ── GET /api/v1/leads/{lead}/notes ────────────────────────────────────────

    public function index(string $lead, Request $request, RequestContext $context): JsonResponse
    {
        if (! $context->hasAbility(ApiAbility::LeadsRead->value)) {
            return ApiResponse::forbidden('You do not have permission to read leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $perPage   = min((int) $request->query('per_page', 25), 100);

        $notes = $leadModel->notes()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ApiResponse::paginated(
            LeadNoteResource::collection($notes)->toArray($request),
            [
                'current_page' => $notes->currentPage(),
                'per_page'     => $notes->perPage(),
                'total'        => $notes->total(),
                'last_page'    => $notes->lastPage(),
                'from'         => $notes->firstItem(),
                'to'           => $notes->lastItem(),
            ]
        );
    }

    // ── POST /api/v1/leads/{lead}/notes ──────────────────────────────────────

    public function store(
        CreateLeadNoteRequest $request,
        string $lead,
        RequestContext $context,
        CreateLeadNoteAction $action,
    ): JsonResponse {
        if (! $context->hasAbility(ApiAbility::LeadsNotesCreate->value)) {
            return ApiResponse::forbidden('You do not have permission to create notes.');
        }

        $leadModel = Lead::findOrFail($lead);
        $note      = $action->execute($leadModel, $request->validated(), $this->causerFromContext($context));

        return ApiResponse::created((new LeadNoteResource($note))->toArray($request));
    }

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
