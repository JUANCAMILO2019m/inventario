<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ReorderService;
use Illuminate\Http\Request;

class ReorderController extends Controller
{
    private const URGENT = ['out', 'critical', 'soon'];

    public function index(Request $request)
    {
        $request->validate([
            'window'   => 'nullable|in:14,30,60,90',
            'cover'    => 'nullable|in:15,30,45,60,90',
            'category' => 'nullable|exists:categories,id',
            'all'      => 'nullable|boolean',
        ]);

        $window = (int) $request->input('window', 30);
        $cover = (int) $request->input('cover', 30);
        $categoryId = $request->filled('category') ? (int) $request->input('category') : null;
        $includeIdle = $request->boolean('all');

        $rows = ReorderService::analyze($window, $cover, $categoryId, $includeIdle);

        $urgent = $rows->filter(fn ($r) => $r['suggest'] !== null && in_array($r['status'], self::URGENT, true));

        return view('reorder.index', [
            'rows'              => $rows,
            'counts'            => $rows->countBy('status'),
            'urgentCost'        => (float) $urgent->sum('est_cost'),
            'urgentMissingCost' => $urgent->whereNull('est_cost')->count(),
            'window'            => $window,
            'cover'             => $cover,
            'categoryId'        => $categoryId,
            'includeIdle'       => $includeIdle,
            'categories'        => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}