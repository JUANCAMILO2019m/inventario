<?php

namespace App\Http\Controllers;

use App\Models\SipsaImport;
use App\Models\SipsaPrice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PriceReferenceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'city' => 'nullable|string|max:80',
            'q'    => 'nullable|string|max:60',
            'sort' => 'nullable|in:name,up,down,price',
        ]);

        $cities = SipsaPrice::query()->distinct()->orderBy('city')->pluck('city');

        if ($cities->isEmpty()) {
            return view('prices.index', ['empty' => true]);
        }

        $city = $cities->contains($request->input('city'))
            ? $request->input('city')
            : ($this->preferred($cities) ?? $this->mostCovered());

        $maxDate = Carbon::parse(SipsaPrice::where('city', $city)->max('captured_on'));

        $rows = SipsaPrice::where('city', $city)
            ->where('captured_on', '>=', $maxDate->copy()->subDays(120)->toDateString())
            ->orderBy('product_code')
            ->orderByDesc('captured_on')
            ->orderByDesc('reg_id')
            ->get(['product_code', 'product', 'price', 'captured_on']);

        $items = $rows->groupBy('product_code')->map(function ($group) use ($maxDate) {
            // un solo precio por fecha (el de mayor reg_id)
            $perDate = $group->unique(fn ($r) => $r->captured_on->toDateString())->values();
            $latest = $perDate->first();
            $previous = $perDate->get(1);
            $monthAgo = $perDate->first(fn ($r) => $r->captured_on->lte($latest->captured_on->copy()->subDays(30)));

            $change = fn ($old) => ($old && $old->price > 0)
                ? ($latest->price - $old->price) / $old->price * 100
                : null;

            return [
                'code'         => $latest->product_code,
                'name'         => SipsaPrice::productLabel($latest->product),
                'price'        => $latest->price,
                'date'         => $latest->captured_on,
                'stale'        => $latest->captured_on->lt($maxDate->copy()->subDays(7)),
                'prev_change'  => $change($previous),
                'prev_date'    => $previous?->captured_on,
                'month_change' => $change($monthAgo),
            ];
        })->values();

        $total = $items->count();
        $rising = $items->filter(fn ($i) => ($i['prev_change'] ?? 0) > 0.05)->count();
        $falling = $items->filter(fn ($i) => ($i['prev_change'] ?? 0) < -0.05)->count();

        if ($request->filled('q')) {
            $term = Str::of($request->input('q'))->ascii()->lower()->toString();
            $items = $items->filter(fn ($i) => str_contains(Str::of($i['name'])->ascii()->lower()->toString(), $term));
        }

        $sort = $request->input('sort', 'name');

        $items = match ($sort) {
            'up'    => $items->sortByDesc(fn ($i) => $i['prev_change'] ?? -INF),
            'down'  => $items->sortBy(fn ($i) => $i['prev_change'] ?? INF),
            'price' => $items->sortByDesc('price'),
            default => $items->sortBy(fn ($i) => Str::ascii($i['name'])),
        };

        return view('prices.index', [
            'empty'   => false,
            'cities'  => $cities,
            'city'    => $city,
            'items'   => $items->values(),
            'maxDate' => $maxDate,
            'total'   => $total,
            'rising'  => $rising,
            'falling' => $falling,
            'sort'    => $sort,
            'import'  => SipsaImport::latest('id')->first(),
        ]);
    }

    public function show(Request $request, int $code)
    {
        $request->validate([
            'city' => 'nullable|string|max:80',
            'days' => 'nullable|in:30,90,180,365',
        ]);

        $product = SipsaPrice::where('product_code', $code)->latest('captured_on')->first(['product']);
        abort_unless($product, 404);

        $cities = SipsaPrice::where('product_code', $code)->distinct()->orderBy('city')->pluck('city');

        $city = $cities->contains($request->input('city'))
            ? $request->input('city')
            : ($this->preferred($cities) ?? $cities->first());

        $days = (int) $request->input('days', 90);

        $last = Carbon::parse(SipsaPrice::where('product_code', $code)->where('city', $city)->max('captured_on'));

        $series = SipsaPrice::where('product_code', $code)
            ->where('city', $city)
            ->where('captured_on', '>=', $last->copy()->subDays($days)->toDateString())
            ->orderBy('captured_on')
            ->orderBy('reg_id')
            ->get(['price', 'captured_on'])
            ->groupBy(fn ($r) => $r->captured_on->toDateString())
            ->map(fn ($g) => $g->last())
            ->values();

        $points = $series->map(fn ($r) => [
            'date'  => $r->captured_on->toDateString(),
            'price' => $r->price,
        ])->all();

        $prices = $series->pluck('price');

        $stats = [
            'current' => $series->last()?->price,
            'date'    => $series->last()?->captured_on,
            'min'     => $prices->min(),
            'max'     => $prices->max(),
            'avg'     => $prices->isNotEmpty() ? $prices->avg() : null,
            'count'   => $prices->count(),
        ];

        $maxAll = Carbon::parse(SipsaPrice::where('product_code', $code)->max('captured_on'));

        $byCity = SipsaPrice::where('product_code', $code)
            ->where('captured_on', '>=', $maxAll->copy()->subDays(14)->toDateString())
            ->orderBy('city')
            ->orderByDesc('captured_on')
            ->orderByDesc('reg_id')
            ->get(['city', 'price', 'captured_on'])
            ->groupBy('city')
            ->map(fn ($g) => $g->first())
            ->sortBy('price')
            ->values();

        return view('prices.show', [
            'code'   => $code,
            'name'   => SipsaPrice::productLabel($product->product),
            'cities' => $cities,
            'city'   => $city,
            'days'   => $days,
            'points' => $points,
            'stats'  => $stats,
            'byCity' => $byCity,
            'mine'   => $byCity->firstWhere('city', $city)?->price,
            'maxAll' => $maxAll,
        ]);
    }

    private function preferred(Collection $cities): ?string
    {
        return $cities->first(fn ($c) => str_contains(Str::upper(Str::ascii($c)), 'VILLAVICENCIO'));
    }

    private function mostCovered(): string
    {
        return (string) SipsaPrice::select('city', DB::raw('COUNT(DISTINCT product_code) as n'))
            ->groupBy('city')
            ->orderByDesc('n')
            ->orderBy('city')
            ->value('city');
    }
}