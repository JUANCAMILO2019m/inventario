<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    use TracksUser, Auditable;

    protected $fillable = [
        'name', 'notes', 'category_id', 'blind', 'status', 'applied_at', 'cancelled_at', 'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'blind'        => 'boolean',
            'applied_at'   => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'open'    => 'Abierto',
            'applied' => 'Aplicado',
            default   => 'Cancelado',
        };
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}