<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SipsaImport extends Model
{
    protected $fillable = ['source', 'records_read', 'records_new', 'first_date', 'last_date'];
}