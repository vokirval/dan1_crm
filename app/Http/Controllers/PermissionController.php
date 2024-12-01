<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::paginate(10);
        return Inertia::render('Permissions/Index', ['permissions' => $permissions]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|unique:permissions']);
        Permission::create($data);

        return redirect()->route('permissions.index')->with('success', 'Permission created successfully.');
    }

    public function destroy(Permission $permission)
    {
        $permission->delete();
        return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully.');
    }
}
