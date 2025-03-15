<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryMethodController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $items = DeliveryMethod::query()
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        return Inertia::render('DeliveryMethods/Index', [
            'data' => $items,
        ]);
    }

    public function getall(Request $request)
    {
 
        $delivery_methods = DeliveryMethod::all();
    
        return response()->json([
            'delivery_methods' => $delivery_methods,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        DeliveryMethod::create($validated);

        return back()->with('success', 'Delivery Method created successfully.');
    }

    public function update(Request $request, $id)
    {
        $item = DeliveryMethod::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return back()->with('success', 'Delivery Method updated successfully.');
    }

    public function destroy($id)
    {
        $item = DeliveryMethod::findOrFail($id);
        $item->delete();

        return back()->with('success', 'Delivery Method deleted successfully.');
    }
}
