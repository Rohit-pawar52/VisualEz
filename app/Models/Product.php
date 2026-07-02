<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_code',
        'product_name',
        'category',
        'price',
        'stock',
        'status',
        'last_updated_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'last_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
