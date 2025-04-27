<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use Inertia\Inertia;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $statuses = Logs::query()
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        return Inertia::render('Logs/Index', [
            'data' => $statuses,
        ]);
    }
}
