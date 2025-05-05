<?php

namespace App\Http\Controllers;

use App\Models\Logs;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min($request->input('per_page', 10), 100);
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        $logs = Logs::query()
            ->with('completedBy') // Загружаем связанного пользователя
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->only(['per_page', 'sort_by', 'sort_direction']));

        return Inertia::render('Logs/Index', [
            'data' => $logs,
        ]);
    }

    public function complete(Request $request, Logs $log)
    {
        $log->update([
            'completed' => true,
            'completed_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Log marked as completed',
            'log' => $log->load('completedBy'),
        ]);
    }

    public function uncomplete(Request $request, Logs $log)
    {
        $log->update([
            'completed' => false,
            'completed_by' => null,
        ]);

        return response()->json([
            'message' => 'Log marked as uncompleted',
            'log' => $log->load('completedBy'),
        ]);
    }
}