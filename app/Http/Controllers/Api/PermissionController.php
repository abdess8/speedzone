<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Permission::query()->orderBy('name')->paginate(20)
        );
    }

    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = Permission::create($request->validated());

        return response()->json($permission, JsonResponse::HTTP_CREATED);
    }

    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission->update($request->validated());

        return response()->json($permission->refresh());
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
