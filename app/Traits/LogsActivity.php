<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                $userId = Auth::id(); // actor
                if (isset($model->skipNextLog) && $model->skipNextLog) return;
                $before = $event === 'updated' ? $model->getOriginal() : null;
                $after = in_array($event, ['created', 'updated']) ? $model->getAttributes() : null;

                // Only for updates, calculate changes
                if ($event === 'updated') {
                    $changes = $model->getChanges();
                    if (!empty($changes)) {
                        $before = array_intersect_key($before, $changes);
                        $after = $changes;
                        // Detect soft-delete via is_deleted flag
                        if (isset($after['is_deleted']) && $after['is_deleted']) {
                            $event = 'deleted';
                        }
                    } else {
                        // No changes, skip logging
                        return;
                    }
                }

                // Metadata: IP, User-Agent, device
                $userAgent = request()->header('User-Agent', 'unknown');
                $device = 'desktop';
                if (stripos($userAgent, 'mobile') !== false) $device = 'mobile';
                elseif (stripos($userAgent, 'tablet') !== false) $device = 'tablet';

                $metadata = [
                    'ip' => request()->ip(),
                    'user_agent' => $userAgent,
                    'device' => $device,
                ];

                // Dynamic ref_code detection
                $refCode = $model->getAttribute('qr_code') 
                    ?? $model->getAttribute('trx_code') 
                    ?? $model->getAttribute('code') 
                    ?? null;
                $insertData = [
                    'user_id'   => $userId,
                    'action'    => $event,
                    'module'    => $model->getTable(),
                    'ref_id'    => $model->id,
                    'ref_code'  => $refCode,
                    'before'    => $before,
                    'after'     => $after,
                    'metadata'  => $metadata,
                ];
                self::logActivity($insertData);
            });
        }
    }

    /**
     * Manual logging method
     */
    public static function logActivity(array $data)
    {
        $metadata = $data['metadata'] ?? [
            'ip' => request()->ip(),
            'user_agent' => request()->header('User-Agent', 'unknown'),
            'device' => stripos(request()->header('User-Agent', ''), 'mobile') !== false
                ? 'mobile'
                : (stripos(request()->header('User-Agent', ''), 'tablet') !== false ? 'tablet' : 'desktop'),
        ];

        ActivityLog::create([
            'user_id'   => $data['user_id'] ?? Auth::id(),
            'group'     => $data['group'] ?? null,
            'action'    => $data['action'] ?? 'unknown',
            'module'    => $data['module'] ?? null,
            'ref_id'    => $data['ref_id'] ?? null,
            'ref_code'  => $data['ref_code'] ?? null,
            'before'    => $data['before'] ?? null,
            'after'     => $data['after'] ?? null,
            'metadata'  => $metadata,
        ]);
    }
}
