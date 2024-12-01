<?php

namespace App\Http\Controllers;

use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderStatusController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $statuses = OrderStatus::query()
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        return Inertia::render('OrderStatuses/Index', [
            'data' => $statuses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        OrderStatus::create($validated);

        return back()->with('success', 'Order status created successfully.');
    }

    public function update(Request $request, $id)
    {
        $status = OrderStatus::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:6'
        ]);

        $status->update($validated);

        return back()->with('success', 'Order status updated successfully.');
    }

    public function destroy($id)
    {
        $status = OrderStatus::findOrFail($id);
        $status->delete();

        return back()->with('success', 'Order status deleted successfully.');
    }
}
