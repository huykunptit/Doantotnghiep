<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with([
                'children' => fn ($query) => $query
                    ->withCount([
                        'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
                    ])
                    ->with([
                        'children' => fn ($childQuery) => $childQuery
                            ->withCount([
                                'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
                            ])
                            ->orderBy('sort_order')
                            ->orderBy('name'),
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->withCount([
                'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
            ])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Match CourseController category filter: parent count includes descendant courses.
        $categories->each(fn (Category $category) => $this->applySubtreeCourseCounts($category));

        return response()->json($categories);
    }

    public function show(Category $category): JsonResponse
    {
        $category->load([
            'parent:id,name,slug,parent_id',
            'children' => fn ($query) => $query
                ->withCount([
                    'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
                ])
                ->with([
                    'children' => fn ($childQuery) => $childQuery
                        ->withCount([
                            'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
                        ])
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                ])
                ->orderBy('sort_order')
                ->orderBy('name'),
        ]);
        $category->loadCount([
            'courses as courses_count' => fn ($courseQuery) => $courseQuery->where('status', 'published'),
        ]);

        $this->applySubtreeCourseCounts($category);

        return response()->json($category);
    }

    /**
     * Replace each node's direct courses_count with subtree total (self + descendants).
     * Mirrors CourseController::categoryAndDescendantIds() used by catalog filters.
     */
    protected function applySubtreeCourseCounts(Category $category): int
    {
        $total = (int) ($category->courses_count ?? 0);
        /** @var Collection<int, Category> $children */
        $children = $category->relationLoaded('children')
            ? $category->children
            : collect();

        foreach ($children as $child) {
            $total += $this->applySubtreeCourseCounts($child);
        }

        $category->setAttribute('courses_count', $total);

        return $total;
    }
}
