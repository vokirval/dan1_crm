<?php

namespace App\Http\Controllers;

use Log;
use Inertia\Inertia;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductsCategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 500);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $sortableFields = ['id', 'name', 'created_at'];
        if (!in_array($sortBy, $sortableFields)) {
            $sortBy = 'created_at';
        }

        $categories = Category::query()
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends([
                'per_page' => $perPage,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ]);

        return Inertia::render('Categories/Index', [
            'data' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Category::create($validated);

        return back()->with('success', 'Категорія створена успішно.');
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update($validated);

        return back()->with('success', 'Категорія оновлена успішно.');
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        if ($category->children()->exists()) {
            return back()->withErrors('Помилка, у даній категорії є підкатегорії');
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }

}
