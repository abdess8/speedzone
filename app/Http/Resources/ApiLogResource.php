<?php

namespace App\Http\Resources;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApiLog
 */
class ApiLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'status_code' => $this->status_code,
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
            'request_payload' => $this->request_payload,
            'response_payload' => $this->response_payload,
            'created_at' => $this->created_at?->toIso8601String(),
            'is_success' => $this->status_code !== null && $this->status_code >= 200 && $this->status_code < 300,
        ];
    }
}
