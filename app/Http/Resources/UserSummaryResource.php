<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->relationLoaded('roles')
            ? $this->roles->first()?->name
            : ($this->relationLoaded('role') ? $this->role?->name : null);

        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone_number,
            'profile_photo_url' => $this->profile_photo_url,
            'photo_url' => $this->photo_url,
            'has_profile_photo' => $this->hasProfilePhoto(),
            'role' => $roleName,
            'role_label' => $roleName ? __('roles.'.$roleName, [], $roleName) : null,
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ] : null),
        ];
    }
}
