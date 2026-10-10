<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $types = AuditLog::typeOptions();

        $request->validate([
            'event' => 'nullable|in:created,updated,deleted',
            'type'  => 'nullable|in:' . implode(',', array_keys($types)),
            'from'  => 'nullable|date',
            'to'    => 'nullable|date|after_or_equal:from',
        ]);

        $logs = AuditLog::query()
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->input('user')))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('type'), fn ($q) => $q->where('auditable_type', $request->input('type')))
            ->when($request->filled('q'), fn ($q) => $q->where('label', 'like', '%' . $request->input('q') . '%'))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('audit.index', compact('logs', 'users', 'types'));
    }
}