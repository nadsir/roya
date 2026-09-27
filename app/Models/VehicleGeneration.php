<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_model_id',
        'name',
        'slug',
        'year_start',
        'year_end',
        'is_active',
    ];

    protected $casts = [
        'year_start' => 'integer',
        'year_end' => 'integer',
        'is_active' => 'boolean',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function trims(): HasMany
    {
        return $this->hasMany(VehicleTrim::class);
    }
}
