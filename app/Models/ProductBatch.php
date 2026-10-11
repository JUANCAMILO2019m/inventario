<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    use TracksUser;

    protected $fillable = ['product_id', 'purchase_item_id', 'lot_code', 'quantity', 'expires_on'];

    protected function casts(): array
    {
        return [
            'quantity'   => 'float',
            'expires_on' => 'date',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function item()
    {
        return $this->belongsTo(PurchaseItem::class, 'purchase_item_id');
    }
}