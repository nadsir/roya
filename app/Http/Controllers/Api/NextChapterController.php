<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageNextChapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class NextChapterController extends Controller
{
    public function show(): JsonResponse
    {
        $record = HomepageNextChapter::singleton();

        return response()->json($this->transform($record));
    }

    public function update(Request $request): JsonResponse
    {
        $record = HomepageNextChapter::singleton();

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'eyebrow' => ['sometimes', 'nullable', 'string', 'max:120'],
            'heading' => ['sometimes', 'nullable', 'string', 'max:255'],
            'intro' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'delivery_enabled' => ['sometimes', 'boolean'],
            'delivery_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'delivery_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'delivery_cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'delivery_cta_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'wholesale_enabled' => ['sometimes', 'boolean'],
            'wholesale_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'wholesale_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'wholesale_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'wholesale_cta_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:40'],
            'whatsapp_message' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('whatsapp_number', $validated)) {
            $validated['whatsapp_number'] = $this->normalizePhone($validated['whatsapp_number']);
        }

        $record->fill($validated);
        $record->save();

        return response()->json($this->transform($record));
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'panel' => ['required', 'in:delivery,wholesale'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $record = HomepageNextChapter::singleton();
        $panel = $request->input('panel');

        try {
            $path = app(\App\Services\ImageOptimizationService::class)
                ->optimizeAndStore($request->file('image'), 'next-chapter');
        } catch (Throwable $e) {
            return response()->json(['message' => 'پردازش تصویر ناموفق بود.'], 422);
        }

        if ($panel === 'delivery') {
            if ($record->delivery_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($record->delivery_image);
            }
            $record->delivery_image = $path;
        } else {
            if ($record->wholesale_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($record->wholesale_image);
            }
            $record->wholesale_image = $path;
        }

        $record->save();

        return response()->json($this->transform($record));
    }

    private function transform(HomepageNextChapter $record): array
    {
        $number = $this->normalizePhone($record->whatsapp_number ?: '09126612898');
        $intl = preg_replace('/\D+/', '', $number);
        if (str_starts_with($intl, '0')) {
            $intl = '98' . substr($intl, 1);
        }
        if (! str_starts_with($intl, '98')) {
            $intl = '98' . ltrim($intl, '0');
        }
        $message = $record->whatsapp_message ?: 'سلام، برای دریافت اطلاعات خرید عمده محصولات رویا گالری و شرایط همکاری پیام می‌دهم.';
        $waUrl = 'https://wa.me/' . $intl . '?text=' . rawurlencode($message);

        return [
            'enabled' => (bool) $record->enabled,
            'eyebrow' => $record->eyebrow ?: 'YOUR NEXT CHAPTER',
            'heading' => $record->heading ?: 'انتخاب بعدی، به سلیقه شما.',
            'intro' => $record->intro,
            'delivery_enabled' => (bool) $record->delivery_enabled,
            'delivery_title' => $record->delivery_title ?: 'استایل شما، در سراسر ایران',
            'delivery_description' => $record->delivery_description ?: 'انتخابتان را با خیال راحت سفارش دهید؛ رویا گالری در کنار شماست تا محصولاتتان را در سراسر ایران دریافت کنید.',
            'delivery_image' => $record->delivery_image ? '/storage/' . ltrim($record->delivery_image, '/') : null,
            'delivery_cta_label' => $record->delivery_cta_label ?: 'مشاهده محصولات',
            'delivery_cta_url' => $record->delivery_cta_url ?: '/store',
            'wholesale_enabled' => (bool) $record->wholesale_enabled,
            'wholesale_title' => $record->wholesale_title ?: 'همکاری عمده با رویا گالری',
            'wholesale_description' => $record->wholesale_description ?: 'برای خرید عمده و آغاز یک همکاری تجاری، با ما در ارتباط باشید.',
            'wholesale_image' => $record->wholesale_image ? '/storage/' . ltrim($record->wholesale_image, '/') : null,
            'wholesale_cta_label' => $record->wholesale_cta_label ?: 'درخواست همکاری عمده',
            'whatsapp_number' => $number,
            'whatsapp_message' => $message,
            'whatsapp_url' => $waUrl,
        ];
    }

    private function normalizePhone(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '09126612898';
        }
        return $value;
    }
}