<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Expense extends Model
{
    use TracksUser, Auditable;

    public const CATEGORIES = [
        'labor'       => 'Mano de obra',
        'vet'         => 'Veterinario',
        'transport'   => 'Transporte',
        'rent'        => 'Arriendo',
        'utilities'   => 'Servicios (agua, luz, gas)',
        'maintenance' => 'Mantenimiento',
        'other'       => 'Otro',
    ];

    protected $fillable = ['spent_at', 'category', 'description', 'amount', 'animal_id', 'supplier_id'];

    protected function casts(): array
    {
        return [
            'spent_at' => 'date',
            'amount'   => 'float',
        ];
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public static function categoryLabel(?string $key): string
    {
        return self::CATEGORIES[$key] ?? ($key ?: '—');
    }

    public function auditLabel(): string
    {
        return self::categoryLabel($this->category)
            . ' · $' . number_format((float) $this->amount, 2)
            . ($this->description ? ' · ' . Str::limit($this->description, 60, '') : '');
    }
}