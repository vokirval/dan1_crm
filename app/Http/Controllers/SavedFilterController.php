<?php

namespace App\Http\Controllers;

use App\Models\SavedFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SavedFilterController extends Controller
{
    public function index()
    {
        return response()->json(
            Auth::user()->savedFilters()->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'main_filter' => 'required|array',
            'date_filter' => 'required|array',
        ]);

        $filter = Auth::user()->savedFilters()->create([
            'name' => $request->name,
            'main_filter' => $request->main_filter,
            'date_filter' => $request->date_filter,
        ]);

        return response()->json($filter, 201);
    }

    public function destroy(SavedFilter $savedFilter)
    {
     
        
        $savedFilter->delete();
        
        return response()->noContent();

    }
}