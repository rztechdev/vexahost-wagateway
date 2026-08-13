<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $usage = $tenant->currentUsage();

        return $this->ok([
            'tenant' => $tenant->name,
            'status' => $tenant->status,
            'sessions' => [
                'total' => $tenant->sessions()->count(),
                'connected' => $tenant->sessions()->where('status', 'connected')->count(),
                'limit' => $tenant->max_sessions,
            ],
            'usage' => [
                'period' => $usage->period,
                'messages_sent' => $usage->messages_sent,
                'messages_received' => $usage->messages_received,
                'messages_failed' => $usage->messages_failed,
                'quota' => $tenant->is_internal ? null : $tenant->monthly_message_quota,
            ],
        ]);
    }
}
