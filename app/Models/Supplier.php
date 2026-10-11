<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Auditable;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'tax_id', 'notes'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}