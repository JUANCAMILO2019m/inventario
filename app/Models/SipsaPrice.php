<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SipsaPrice extends Model
{
    protected $fillable = ['reg_id', 'city', 'product_code', 'product', 'price', 'captured_on'];

    protected function casts(): array
    {
        return [
            'price'       => 'float',
            'captured_on' => 'date',
        ];
    }

    /** «BOGOTÁ, D.C.» -> «Bogotá, D.C.» */
    public static function cityLabel(string $city): string
    {
        $label = mb_convert_case(mb_strtolower($city, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        return str_replace([' De ', ' Del ', ' La ', ' Y ', 'D.c.'], [' de ', ' del ', ' la ', ' y ', 'D.C.'], $label);
    }

    /** DANE marca algunos productos con asterisco al final del nombre. */
    public static function productLabel(string $name): string
    {
        return rtrim(trim($name), '* ');
    }
}