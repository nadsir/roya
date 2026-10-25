<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageNextChapter extends Model
{
    protected $table = 'homepage_next_chapter';

    protected $fillable = [
        'enabled',
        'eyebrow',
        'heading',
        'intro',
        'delivery_enabled',
        'delivery_title',
        'delivery_description',
        'delivery_image',
        'delivery_cta_label',
        'delivery_cta_url',
        'wholesale_enabled',
        'wholesale_title',
        'wholesale_description',
        'wholesale_image',
        'wholesale_cta_label',
        'whatsapp_number',
        'whatsapp_message',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'delivery_enabled' => 'boolean',
        'wholesale_enabled' => 'boolean',
    ];

    public static function singleton(): self
    {
        $record = self::query()->first();
        if ($record) {
            return $record;
        }
        return self::create([
            'whatsapp_message' => 'سلام، برای دریافت اطلاعات خرید عمده محصولات رویا گالری و شرایط همکاری پیام می‌دهم.',
        ]);
    }
}