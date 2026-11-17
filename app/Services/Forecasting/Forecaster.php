<?php

namespace App\Services\Forecasting;

/**
 * Modelos de pronóstico sencillos y transparentes:
 *  - Regresión lineal con intervalo de predicción (para pesos de animales).
 *  - Suavizado exponencial de Holt con tendencia amortiguada (para series en el tiempo).
 *  - Promedio de los últimos valores (modelo de referencia).
 * Para cada serie se prueban los dos modelos contra datos que ya ocurrieron (backtest)
 * y se usa el que menos se equivoca.
 */
class Forecaster
{
    /** Valores críticos de la t de Student (intervalo bilateral de 80%) por grados de libertad. */
    private const T80 = [
        1 => 3.078, 2 => 1.886, 3 => 1.638, 4 => 1.533, 5 => 1.476, 6 => 1.440, 7 => 1.415,
        8 => 1.397, 9 => 1.383, 10 => 1.372, 11 => 1.363, 12 => 1.356, 13 => 1.350, 14 => 1.345,
        15 => 1.341, 16 => 1.337, 17 => 1.333, 18 => 1.330, 19 => 1.328, 20 => 1.325,
        25 => 1.316, 30 => 1.310,
    ];

    private const Z80 = 1.2816;

    public static function t80(int $df): float
    {
        if ($df < 1) {
            return self::T80[1];
        }

        if ($df > 30) {
            return self::Z80;
        }

        while (!isset(self::T80[$df])) {
            $df++;
        }

        return self::T80[$df];
    }

    // ------------------------------------------------------------------ regresión lineal

    /** Ajusta y = a + b·x. Devuelve null si no hay datos suficientes. */
    public static function linear(array $x, array $y): ?array
    {
        $x = array_values($x);
        $y = array_values($y);
        $n = count($x);

        if ($n < 3 || $n !== count($y)) {
            return null;
        }

        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxx = $sxy = $syy = 0.0;

        foreach ($x as $i => $xi) {
            $dx = $xi - $mx;
            $dy = $y[$i] - $my;
            $sxx += $dx * $dx;
            $sxy += $dx * $dy;
            $syy += $dy * $dy;
        }

        if ($sxx <= 0) {
            return null;
        }

        $b = $sxy / $sxx;
        $a = $my - $b * $mx;
        $sse = 0.0;

        foreach ($x as $i => $xi) {
            $e = $y[$i] - ($a + $b * $xi);
            $sse += $e * $e;
        }

        $df = $n - 2;

        return [
            'a'   => $a,
            'b'   => $b,
            'n'   => $n,
            'mx'  => $mx,
            'sxx' => $sxx,
            'df'  => $df,
            's'   => sqrt($sse / $df),
            'r2'  => $syy > 0 ? max(0.0, 1 - $sse / $syy) : 1.0,
        ];
    }

    /** Predicción de un valor nuevo con su intervalo de 80%. */
    public static function linearPredict(array $fit, float $x): array
    {
        $value = $fit['a'] + $fit['b'] * $x;
        $se = $fit['s'] * sqrt(1 + 1 / $fit['n'] + (($x - $fit['mx']) ** 2) / $fit['sxx']);
        $margin = self::t80($fit['df']) * $se;

        return ['value' => $value, 'low' => $value - $margin, 'high' => $value + $margin];
    }

    /**
     * Primer día (contado desde $xNow) en que la predicción alcanza el objetivo.
     * $bound: 'value' (lo más probable), 'high' (lo más pronto) o 'low' (lo más tarde).
     */
    public static function daysToReach(array $fit, float $xNow, float $target, string $bound = 'value', int $maxDays = 730): ?int
    {
        for ($d = 0; $d <= $maxDays; $d++) {
            if (self::linearPredict($fit, $xNow + $d)[$bound] >= $target) {
                return $d;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------ series en el tiempo

    /** Promedio de los últimos $k valores, repetido hacia adelante. */
    public static function movingAverage(array $y, int $h, int $k = 4): ?array
    {
        $y = array_values($y);
        $n = count($y);

        if ($n < 3) {
            return null;
        }

        $tail = array_slice($y, -min($k, $n));
        $mean = array_sum($tail) / count($tail);

        $recent = array_slice($y, -min(8, $n));
        $m = array_sum($recent) / count($recent);
        $var = 0.0;

        foreach ($recent as $v) {
            $var += ($v - $m) ** 2;
        }

        $sigma = count($recent) > 1 ? sqrt($var / (count($recent) - 1)) : 0.0;
        $margin = self::Z80 * $sigma * sqrt(1 + 1 / count($tail));

        return [
            'values' => array_fill(0, $h, $mean),
            'low'    => array_fill(0, $h, $mean - $margin),
            'high'   => array_fill(0, $h, $mean + $margin),
        ];
    }

    /** Suavizado exponencial con tendencia amortiguada (Holt), con parámetros elegidos por mínimo error. */
    public static function holt(array $y, int $h): ?array
    {
        $y = array_values($y);
        $n = count($y);

        if ($n < 6) {
            return null;
        }

        $best = null;

        foreach ([0.1, 0.3, 0.5, 0.7, 0.9] as $alpha) {
            foreach ([0.05, 0.1, 0.2, 0.4] as $beta) {
                foreach ([0.8, 0.9, 0.98] as $phi) {
                    $run = self::holtRun($y, $alpha, $beta, $phi);

                    if ($best === null || $run['sse'] < $best['sse']) {
                        $best = $run + ['phi' => $phi];
                    }
                }
            }
        }

        $sigma = sqrt($best['sse'] / ($n - 1));
        $values = $low = $high = [];

        for ($k = 1; $k <= $h; $k++) {
            $damp = $best['phi'] * (1 - $best['phi'] ** $k) / (1 - $best['phi']);
            $point = $best['l'] + $damp * $best['b'];
            $margin = self::Z80 * $sigma * sqrt($k);

            $values[] = $point;
            $low[] = $point - $margin;
            $high[] = $point + $margin;
        }

        return ['values' => $values, 'low' => $low, 'high' => $high];
    }

    private static function holtRun(array $y, float $alpha, float $beta, float $phi): array
    {
        $n = count($y);
        $k = min(3, $n - 1);
        $level = $y[0];
        $trend = ($y[$k] - $y[0]) / $k;
        $sse = 0.0;

        for ($t = 1; $t < $n; $t++) {
            $forecast = $level + $phi * $trend;
            $error = $y[$t] - $forecast;
            $sse += $error * $error;

            $newLevel = $alpha * $y[$t] + (1 - $alpha) * $forecast;
            $trend = $beta * ($newLevel - $level) + (1 - $beta) * $phi * $trend;
            $level = $newLevel;
        }

        return ['l' => $level, 'b' => $trend, 'sse' => $sse];
    }

    /**
     * Error porcentual ponderado (WAPE) de un modelo, probándolo varias veces contra datos ya ocurridos.
     * Devuelve null si la serie es muy corta o los valores reales suman cero.
     */
    public static function backtest(array $y, int $h, callable $model): ?float
    {
        $y = array_values($y);
        $n = count($y);
        $step = max(1, intdiv($h, 2));
        $num = $den = 0.0;
        $origins = 0;

        for ($i = 0; $i < 3; $i++) {
            $m = $n - $h - $i * $step;

            if ($m < 8) {
                break;
            }

            $fc = $model(array_slice($y, 0, $m), $h);

            if (!$fc) {
                continue;
            }

            foreach (array_slice($y, $m, $h) as $j => $actual) {
                $num += abs($actual - $fc['values'][$j]);
                $den += abs($actual);
            }

            $origins++;
        }

        return ($origins > 0 && $den > 0) ? $num / $den * 100 : null;
    }

    /** Elige entre tendencia y promedio según su desempeño pasado, y devuelve el pronóstico. */
    public static function best(array $y, int $h, bool $nonNegative = true): ?array
    {
        $y = array_values($y);

        if (count($y) < 8) {
            return null;
        }

        $models = [
            'trend' => fn ($s, $hh) => self::holt($s, $hh),
            'avg'   => fn ($s, $hh) => self::movingAverage($s, $hh),
        ];

        $errors = [];

        foreach ($models as $name => $model) {
            $errors[$name] = self::backtest($y, $h, $model);
        }

        $chosen = 'avg';

        if ($errors['trend'] !== null && ($errors['avg'] === null || $errors['trend'] < $errors['avg'] * 0.95)) {
            $chosen = 'trend';
        }

        $fc = $models[$chosen]($y, $h);

        if (!$fc) {
            return null;
        }

        if ($nonNegative) {
            foreach (['values', 'low', 'high'] as $key) {
                $fc[$key] = array_map(fn ($v) => max(0.0, $v), $fc[$key]);
            }
        }

        $wape = $errors[$chosen];

        return $fc + [
            'method'      => $chosen,
            'wape'        => $wape,
            'wape_trend'  => $errors['trend'],
            'wape_avg'    => $errors['avg'],
            'reliability' => self::reliability($wape),
        ];
    }

    /** Etiqueta de confiabilidad según el error del backtest (umbrales de criterio propio, no de un estándar). */
    public static function reliability(?float $wape): string
    {
        if ($wape === null) {
            return 'unknown';
        }

        return $wape <= 10 ? 'high' : ($wape <= 25 ? 'medium' : 'low');
    }

    /** Confiabilidad de una regresión según cantidad de datos y ajuste. */
    public static function growthReliability(int $n, float $r2, int $spanDays): string
    {
        if ($n >= 5 && $r2 >= 0.9 && $spanDays >= 30) {
            return 'high';
        }

        if ($n >= 4 && $r2 >= 0.7) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Días hasta agotar el stock, dado el consumo semanal proyectado.
     * Después del horizonte se asume que se mantiene el último ritmo. Null si no hay consumo previsto.
     */
    public static function daysToDeplete(float $stock, array $weekly): ?float
    {
        if ($stock <= 0) {
            return 0.0;
        }

        $remaining = $stock;
        $weeks = count($weekly);

        foreach ($weekly as $k => $rate) {
            $rate = max(0.0, (float) $rate);

            if ($rate <= 0) {
                continue;
            }

            if ($remaining <= $rate) {
                return $k * 7 + $remaining / ($rate / 7);
            }

            $remaining -= $rate;
        }

        $last = $weeks > 0 ? max(0.0, (float) end($weekly)) : 0.0;

        return $last > 0 ? $weeks * 7 + $remaining / ($last / 7) : null;
    }
}
