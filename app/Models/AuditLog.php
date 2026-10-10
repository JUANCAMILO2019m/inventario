<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'user_name', 'event', 'auditable_type', 'auditable_id',
        'label', 'changes', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function typeOptions(): array
    {
        return [
            Product::class      => 'Producto',
            Category::class     => 'Categoría',
            Animal::class       => 'Animal',
            AnimalRecord::class => 'Registro de animal',
            User::class         => 'Usuario',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeOptions()[$this->auditable_type] ?? class_basename($this->auditable_type);
    }
}