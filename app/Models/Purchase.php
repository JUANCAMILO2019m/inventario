<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use TracksUser, Auditable;

    protected $fillable = ['supplier_id', 'purchased_at', 'invoice_number', 'total', 'notes', 'voided_at'];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'total'        => 'float',
            'voided_at'    => 'datetime',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function auditLabel(): string
    {
        return 'Compra #' . $this->id . ($this->supplier ? ' · ' . $this->supplier->name : '');
    }
}