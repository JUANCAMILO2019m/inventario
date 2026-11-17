<?php

namespace App\Http\Controllers;

use App\Models\SipsaPrice;
use App\Services\Forecasting\ForecastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['city' => 'nullable|string|max:80']);

        $cities = SipsaPrice::query()->distinct()->orderBy('city')->pluck('city');
        $city = null;
        $prices = null;

        if ($cities->isNotEmpty()) {
            $city = $cities->contains($request->input('city'))
                ? $request->input('city')
                : ($cities->first(fn ($c) => str_contains(Str::upper(Str::ascii($c)), 'VILLAVICENCIO'))
                    ?? $this->mostCovered());

            $prices = ForecastService::prices($city);
        }

        return view('forecasts.index', [
            'finance'   => ForecastService::finance(),
            'growth'    => ForecastService::growth(),
            'inventory' => ForecastService::inventory(),
            'prices'    => $prices,
            'cities'    => $cities,
            'city'      => $city,
        ]);
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