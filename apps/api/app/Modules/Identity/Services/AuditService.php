<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\AuditLog;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an audit log event.
     */
    public function log(
        string $action,
        ?User $actor = null,
        ?Model $target = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $actor = $actor ?: auth()->user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_email' => $actor?->email,
            'action' => $action,
            'auditable_type' => $target ? get_class($target) : null,
            'auditable_id' => $target?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }
}
