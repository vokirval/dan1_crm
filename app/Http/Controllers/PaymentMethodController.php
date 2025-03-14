<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $items = PaymentMethod::query()
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        return Inertia::render('PaymentMethods/Index', [
            'data' => $items,
        ]);
    }

    public function getall(Request $request)
    {
 
        $payment_methods = PaymentMethod::all();
    
        return response()->json([
            'payment_methods' => $payment_methods,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        PaymentMethod::create($validated);

        return back()->with('success', 'Payment Method created successfully.');
    }

    public function update(Request $request, $id)
    {
        $item = PaymentMethod::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $item->update($validated);

        return back()->with('success', 'Payment Method updated successfully.');
    }

    public function destroy($id)
    {
        $item = PaymentMethod::findOrFail($id);
        $item->delete();

        return back()->with('success', 'Payment Method deleted successfully.');
    }
}
