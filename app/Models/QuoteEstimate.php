<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QuoteEstimate extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_request_id',
        'uuid',
        'status',
        'total_amount',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteEstimateLineItem::class);
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->lineItems()->sum('total_price');
        $this->save();
    }
}
