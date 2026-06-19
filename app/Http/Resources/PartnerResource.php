<?php

namespace App\Http\Resources;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logo_url,
            'ice_number' => $this->ice_number,
            'is_active' => (bool) $this->is_active,
            'reception_city_id' => $this->reception_city_id,
            'reception_city' => $this->whenLoaded('receptionCity', fn () => $this->receptionCity?->name),
            'api_base_url' => $this->api_base_url,
            'client_id' => $this->client_id,
            'has_client_secret' => filled($this->client_secret),
            'auth_type' => $this->auth_type?->value ?? $this->auth_type,
            'auth_type_label' => $this->auth_type instanceof \App\Enums\PartnerAuthType
                ? $this->auth_type->label()
                : null,
            'endpoint_statuses' => $this->endpoint_statuses,
            'endpoint_deliveries' => $this->endpoint_deliveries,
            'delivery_lookup_param' => $this->delivery_lookup_param,
            'endpoint_update' => $this->endpoint_update,
            'endpoint_login' => $this->endpoint_login,
            'api_key_header' => $this->api_key_header,
            'login_username_field' => $this->login_username_field,
            'login_password_field' => $this->login_password_field,
            'login_token_field' => $this->login_token_field,
            'has_access_token' => filled($this->access_token),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'sync_frequency_minutes' => (int) $this->sync_frequency_minutes,
            'sync_status' => (bool) $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'orders_count' => $this->whenCounted('orders'),
            'cities' => $this->whenLoaded(
                'cities',
                fn () => $this->cities->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])->values()
            ),
            'city_ids' => $this->whenLoaded('cities', fn () => $this->cities->pluck('id')->values()),
            'sectors' => $this->whenLoaded(
                'sectors',
                fn () => $this->sectors->map(fn ($sector) => [
                    'id' => $sector->id,
                    'name' => $sector->name,
                    'city_id' => $sector->city_id,
                    'city_name' => $sector->relationLoaded('city') ? $sector->city?->name : null,
                ])->values()
            ),
            'sector_ids' => $this->whenLoaded('sectors', fn () => $this->sectors->pluck('id')->values()),
            'status_mappings' => $this->whenLoaded(
                'statusMappings',
                fn () => StatusMappingResource::collection($this->statusMappings)->resolve($request)
            ),
            'field_mappings' => $this->whenLoaded(
                'fieldMappings',
                fn () => FieldMappingResource::collection($this->fieldMappings)->resolve($request)
            ),
            'update_field_mappings' => $this->whenLoaded(
                'updateFieldMappings',
                fn () => UpdateFieldMappingResource::collection($this->updateFieldMappings)->resolve($request)
            ),
            'users' => $this->whenLoaded(
                'users',
                fn () => $this->users->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->full_name ?? trim("{$user->first_name} {$user->last_name}") ?: $user->name,
                    'email' => $user->email,
                ])->values()
            ),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
