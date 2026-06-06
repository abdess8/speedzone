<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncUserRolesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function update(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $roleIds = $request->validated()['role_ids'];
        $user->roles()->sync($roleIds);
        $user->update(['role_id' => $roleIds[0] ?? null]);

        return response()->json($user->load('roles:id,name'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->roles()->detach($data['role_ids']);

        $primaryRoleId = $user->roles()->pluck('roles.id')->first();
        $user->update(['role_id' => $primaryRoleId]);

        return response()->json($user->load('roles:id,name'));
    }
}
