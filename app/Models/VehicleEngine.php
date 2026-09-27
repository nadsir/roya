<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VehicleEngine extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_trim_id',
        'name',
        'slug',
        'displacement',
        'fuel_type',
        'horsepower',
        'is_active',
    ];

    protected $casts = [
        'displacement' => 'decimal:1',
        'horsepower' => 'integer',
        'is_active' => 'boolean',
    ];

    public function trim(): BelongsTo
    {
        return $this->belongsTo(VehicleTrim::class, 'vehicle_trim_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_vehicle_compat'
        )->withTimestamps();
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_vehicle')->withTimestamps();
    }
}
