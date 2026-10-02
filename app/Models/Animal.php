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

    public function getPhotoUrlAttribute(): ?string
    {
        if (blank($this->photo)) {
            return null;
        }

        // Si es una URL de Cloudinary, devolverla directamente.
        if (str_starts_with($this->photo, 'http://') ||
            str_starts_with($this->photo, 'https://')) {
            return $this->photo;
        }

        // Si es una imagen antigua almacenada localmente.
        return Storage::disk('public')->url($this->photo);
    }
}