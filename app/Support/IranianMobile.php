<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class IranianMobile
{
    public static function normalize(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 64) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل معتبر وارد کنید.']);
        }
        $value = strtr(trim($value), array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
        $value = preg_replace('/[\s()-]+/u', '', $value);
        if (!preg_match('/^(?:\+98|98|0)(9[0-9]{9})$/D', $value, $matches)) {
            throw ValidationException::withMessages(['mobile' => 'شماره موبایل ایران را به‌درستی وارد کنید.']);
        }
        return '0'.$matches[1];
    }
}
