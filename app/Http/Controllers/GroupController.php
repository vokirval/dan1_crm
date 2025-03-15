<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $items = Group::query()
            ->with('users') // Подгружаем пользователей группы
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        $allUsers = User::all(); // Для использования в UI

        return Inertia::render('Groups/Index', [
            'data' => $items,
            'users' => $allUsers, // Отправляем всех пользователей
        ]);
    }

    public function getall(Request $request)
    {
 
        $groups = Group::all();
    
        return response()->json([
            'groups' => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Group::create($validated);

        return back()->with('success', 'Group created successfully.');
    }

    public function update(Request $request, $id)
    {
        $item = Group::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return back()->with('success', 'Group updated successfully.');
    }

    public function destroy($id)
    {
        $group = Group::findOrFail($id);

        if ($group->users()->exists()) {
            return back()->withErrors('Неможливо видалити групу, оскільки в ній є користувачі.');
        }

        $group->delete();

        return back()->with('success', 'Group deleted successfully.');
    }

    /**
     * Массовое добавление пользователей в группу.
     */
    public function addUsers(Request $request, $id)
    {
        $group = Group::findOrFail($id);

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Исключаем пользователей, которые уже есть в группе
        $newUserIds = array_diff($validated['user_ids'], $group->users->pluck('id')->toArray());

        if (empty($newUserIds)) {
            return back()->withErrors('Усі вибрані користувачі вже є в групі.');
        }

        $group->users()->attach($newUserIds);

        return back()->with('success', 'Користувачі успішно додані до групи.');
    }


    /**
     * Удаление пользователя из группы.
     */
    public function removeUser($groupId, $userId)
    {
        $group = Group::findOrFail($groupId);

        // Используем явное указание таблицы в запросе
        $exists = $group->users()->where('users.id', $userId)->exists();

        if (!$exists) {
            return back()->withErrors('Користувач не знайдений у групі.');
        }

        $group->users()->detach($userId);

        return back()->with('success', 'Користувач успішно видалений з групи.');
    }

}
