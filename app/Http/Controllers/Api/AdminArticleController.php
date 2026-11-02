<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\VehicleBrand;
use App\Models\VehicleEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = Article::query()->with(['author', 'categories', 'products', 'vehicles', 'brands']);

        if ($search = $request->input('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $paginator = $query->latest()->paginate(20);

        return response()->json($paginator);
    }

    public function show(Article $article)
    {
        $article->load(['author', 'categories', 'products', 'vehicles', 'brands']);

        return response()->json($article);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        return DB::transaction(function () use ($data) {
            $categoryIds = $data['categories'] ?? [];
            $productIds = $data['products'] ?? [];
            $vehicleIds = $data['vehicles'] ?? [];
            $brandIds = $data['brands'] ?? [];

            unset(
                $data['categories'],
                $data['products'],
                $data['vehicles'],
                $data['brands']
            );

            $data = $this->applyPublishBehavior($data, $article ?? null);

            $article = Article::create($data);

            $this->syncRelations($article, $categoryIds, $productIds, $vehicleIds, $brandIds);

            return response()->json(
                $article->load(['author', 'categories', 'products', 'vehicles', 'brands']),
                201
            );
        });
    }

    public function update(Request $request, Article $article)
    {
        $data = $this->validatedData($request, $article);

        return DB::transaction(function () use ($data, $article) {
            $categoryIds = $data['categories'] ?? [];
            $productIds = $data['products'] ?? [];
            $vehicleIds = $data['vehicles'] ?? [];
            $brandIds = $data['brands'] ?? [];

            unset(
                $data['categories'],
                $data['products'],
                $data['vehicles'],
                $data['brands']
            );

            $data = $this->applyPublishBehavior($data, $article);

            $article->update($data);

            $this->syncRelations($article, $categoryIds, $productIds, $vehicleIds, $brandIds);

            return response()->json(
                $article->fresh()->load(['author', 'categories', 'products', 'vehicles', 'brands'])
            );
        });
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return response()->noContent();
    }

    private function applyPublishBehavior(array $data, ?Article $article = null): array
    {
        $status = array_key_exists('status', $data) ? ($data['status'] ?? null) : ($article?->status);

        if (array_key_exists('published_at', $data)) {
            $publishedAt = $data['published_at'];

            if ($publishedAt === null || $publishedAt === '') {
                $data['published_at'] = null;
            } elseif (is_string($publishedAt)) {
                $trimmed = trim($publishedAt);

                if ($trimmed === '') {
                    $data['published_at'] = null;
                } else {
                    try {
                        $data['published_at'] = $this->parsePublishedAt($trimmed);
                    } catch (\Throwable $e) {
                        throw ValidationException::withMessages([
                            'published_at' => ['تاریخ انتشار نامعتبر است.'],
                        ]);
                    }
                }
            } else {
                throw ValidationException::withMessages([
                    'published_at' => ['تاریخ انتشار نامعتبر است.'],
                ]);
            }
        }

        if ($status === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = now();
            }
        } elseif ($status === 'draft') {
            $data['published_at'] = null;
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = $status;
        }

        return $data;
    }

    private function parsePublishedAt(string $value): Carbon
    {
        // Explicit timezone (Z, +HH:MM, -HH:MM) must be respected.
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value) === 1) {
            $carbon = Carbon::parse($value);

            if ($this->hasCarbonDateErrors() || ! $carbon instanceof Carbon) {
                throw new \InvalidArgumentException('Invalid date.');
            }

            return $carbon->utc();
        }

        // No timezone: interpret the value as Asia/Tehran wall-clock time.
        $normalized = str_replace('T', ' ', $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
            $format = 'Y-m-d H:i:s';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $format = 'Y-m-d H:i';
        } else {
            throw new \InvalidArgumentException('Invalid date.');
        }

        $carbon = Carbon::createFromFormat($format, $normalized, 'Asia/Tehran');

        if (
            ! $carbon instanceof Carbon
            || $this->hasCarbonDateErrors()
            || $carbon->format($format) !== $normalized
        ) {
            throw new \InvalidArgumentException('Invalid date.');
        }

        return $carbon->utc();
    }

    private function hasCarbonDateErrors(): bool
    {
        $errors = Carbon::getLastErrors();

        if (! is_array($errors)) {
            return false;
        }

        return ($errors['warning_count'] ?? 0) > 0
            || ($errors['error_count'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Article $article = null): array
    {
        $articleId = $article?->id;
        $isUpdate = $article !== null;

        return $request->validate([
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('articles', 'slug')->ignore($articleId),
            ],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'canonical_url' => ['nullable', 'string', 'max:2048'],
            'is_featured' => ['boolean'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer', 'exists:products,id'],
            'vehicles' => ['nullable', 'array'],
            'vehicles.*' => ['integer', 'exists:vehicle_engines,id'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['integer', 'exists:vehicle_brands,id'],
        ]);
    }

    private function syncRelations(
        Article $article,
        array $categoryIds,
        array $productIds,
        array $vehicleIds,
        array $brandIds
    ): void {
        $article->categories()->sync(
            collect($categoryIds)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $article->products()->sync(
            collect($productIds)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $article->vehicles()->sync(
            collect($vehicleIds)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $article->brands()->sync(
            collect($brandIds)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );
    }
}
