<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

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

        return response()->json($category);
    }
}
