<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Animal extends Model
{
    protected $fillable = [
        'type', 'name', 'code', 'species', 'breed', 'sex',
        'birth_date', 'quantity', 'status', 'photo', 'description',
    ];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    public function records()
    {
        return $this->hasMany(AnimalRecord::class);
    }

    public function isLot(): bool
    {
        return $this->type === 'lot';
    }

    public function lastWeight(): ?AnimalRecord
    {
        return $this->records()
            ->where('type', 'weight')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }
}