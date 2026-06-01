<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\ApiAbility;
use App\Domain\Leads\Models\Lead;
use App\Http\Context\RequestContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeadActivityLogResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadActivityController extends Controller
{
    // ── GET /api/v1/leads/{lead}/activity ─────────────────────────────────────

    public function index(string $lead, Request $request, RequestContext $context): JsonResponse
    {
        if (! $context->hasAbility(ApiAbility::LeadsRead->value)) {
            return ApiResponse::forbidden('You do not have permission to read leads.');
        }

        $leadModel = Lead::findOrFail($lead);
        $perPage   = min((int) $request->query('per_page', 25), 100);

        $query = $leadModel->activityLogs()->orderBy('created_at', 'desc');

        if ($request->filled('event')) {
            $query->where('event_type', $request->string('event'));
        }

        $logs = $query->paginate($perPage);

        return ApiResponse::paginated(
            LeadActivityLogResource::collection($logs)->toArray($request),
            [
                'current_page' => $logs->currentPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'last_page'    => $logs->lastPage(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ]
        );
    }
}
