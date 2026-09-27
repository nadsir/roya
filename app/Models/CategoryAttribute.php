<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryAttribute extends Pivot
{
    protected $table = 'category_attributes';

    protected $casts = [
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_variant_axis' => 'boolean',
        'sort_order' => 'integer',
    ];
}
