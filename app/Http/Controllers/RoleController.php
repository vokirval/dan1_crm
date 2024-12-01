<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {

        $perPage = min($request->input('per_page', 10), 500);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        
        $permissions = Permission::all();
        $roles = Role::with(['permissions:id'])
        ->orderBy($sortBy, $sortDirection)
        ->paginate($perPage)
        ->through(fn($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('id')->toArray(), // Только ID
        ])
        ->appends([
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);
        return Inertia::render('Roles/Index', ['roles' => $roles, 'permissions' => $permissions]);
    }

    public function create()
    {
        $permissions = Permission::all();
        return Inertia::render('Roles/Create', ['permissions' => $permissions]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|unique:roles']);
        $role = Role::create($data);
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all();
        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate(['name' => 'required|string|unique:roles,name,' . $role->id]);
        $role->update($data);
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }
}
