<?php

namespace App\Modules\Identity\Controllers\Admin;

use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Resources\AuditLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AdminAuditLogController extends Controller
{
    private function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user('sanctum') ?? $request->user();

        abort_unless(
            $user !== null && ($user->can($permission) || $user->isSuperAdmin()),
            403,
            'You do not have permission to view audit logs.'
        );
    }

    /**
     * GET /api/admin/audit-logs
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, 'audit.view');

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

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('auditable_type', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return AuditLogResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/admin/audit-logs/{auditLog}
     */
    public function show(Request $request, AuditLog $auditLog): AuditLogResource
    {
        $this->authorizePermission($request, 'audit.view');

        return new AuditLogResource($auditLog);
    }
}
