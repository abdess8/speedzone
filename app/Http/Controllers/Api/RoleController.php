<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->system()
            ->with('permissions:id,name')
            ->orderBy('name')
            ->paginate(20);

        return response()->json($roles);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = Role::create(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return response()->json(
            $role->load('permissions:id,name'),
            JsonResponse::HTTP_CREATED
        );
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json($role->load('permissions:id,name'));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();
        $role->update(['name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);

        return response()->json($role->load('permissions:id,name'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->permissions()->detach();
        $role->users()->detach();
        $role->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
