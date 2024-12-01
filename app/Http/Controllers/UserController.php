<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 500);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $users = User::with('roles:id,name')
        ->orderBy($sortBy, $sortDirection)
        ->paginate($perPage)
        ->appends([
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);

        $roles = Role::all(); // Загружаем все роли

        
        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        return back()->with('success', 'Користувач створений успішно.');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($data);

        return back()->with('success', 'Користувач оновлений успішно.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return back()->with('success', 'Користувач видалений успішно.');
    }

    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'roles' => 'array|exists:roles,id', // Проверяем существование ID ролей
        ]);

        $user->syncRoles($data['roles']);

        return back()->with('success', 'Ролі оновлено успішно.');
    }
}
