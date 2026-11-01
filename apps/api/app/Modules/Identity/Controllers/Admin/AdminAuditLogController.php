<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminAuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->input('action') . '%');
        }

        if ($request->filled('user_email')) {
            $query->where('user_email', 'like', '%' . $request->input('user_email') . '%');
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return AuditLogResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/admin/audit-logs/{auditLog}
     */
    public function show(AuditLog $auditLog): AuditLogResource
    {
        return new AuditLogResource($auditLog);
    }
}
