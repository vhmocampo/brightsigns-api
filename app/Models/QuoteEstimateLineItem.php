<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteEstimateLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_estimate_id',
        'name',
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'product_id',
        'similarity_score',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'similarity_score' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function quoteEstimate(): BelongsTo
    {
        return $this->belongsTo(QuoteEstimate::class);
    }
}
