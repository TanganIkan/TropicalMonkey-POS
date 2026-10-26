<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Catat aktivitas penting karyawan untuk halaman Monitoring.
 * User & outlet diambil dari sesi login bila tidak diberikan.
 */
class ActivityLogger
{
    public function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null, ?int $outletId = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $userId ?? auth()->id(),
            'outlet_id' => $outletId ?? (session('current_outlet_id') ?: null),
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
