<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSyncLog extends Model
{
    protected $table = 'product_sync_logs';
    public $timestamps = false;

    protected $fillable = [
        'product_code',
        'action',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
