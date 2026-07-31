<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SectionController extends Controller
{
    /**
     * Sections of a course.
     * - Owner / admin / manage_courses: full payload for builder
     * - Public: outline of published courses only
     */
    public function index(Course $course)
    {
        $user = auth('sanctum')->user();
        $canManage = $user && (
            \App\Support\Authorize::isAdmin($user)
            || (int) $user->id === (int) $course->user_id
            || $user->can('manage_courses')
        );

        if (!$canManage && $course->status !== 'published') {
            return response()->json(['message' => 'Course not found'], 404);
        }

        if ($canManage) {
            $sections = $course->sections()
                ->with(['lessons' => function ($query) {
                    $query->with(['attachments', 'virtualClass', 'scormPackage', 'assignment'])
                        ->orderBy('order');
                }])
                ->ordered()
                ->get();
        } else {
            $sections = $course->sections()
                ->with(['lessons' => function ($query) {
                    $query->orderBy('order')
                        ->select('id', 'section_id', 'course_id', 'title', 'type', 'duration', 'is_preview', 'order');
                }])
                ->ordered()
                ->get();
        }

        return response()->json([
            'data' => $sections,
            'message' => 'Sections retrieved successfully',
        ]);
    }

    /**
     * Create a new section
     */
    public function store(Request $request, Course $course)
    {
        // Check authorization (instructor or admin)
        if (auth()->id() !== $course->user_id && !\App\Support\Authorize::isAdmin(auth()->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'position' => 'nullable|integer|min:0',
        ]);

        // Auto-set position if not provided
        if (!isset($validated['position'])) {
            $validated['position'] = $course->sections()->max('position') + 1;
        }

        $section = $course->sections()->create($validated);

        return response()->json([
            'data' => $section,
            'message' => 'Section created successfully',
        ], 201);
    }

    /**
     * Show a specific section
     */
    public function show(Section $section)
    {
        $section->load(['lessons' => function ($query) {
            $query->orderBy('order');
        }]);

        return response()->json([
            'data' => $section,
            'message' => 'Section retrieved successfully',
        ]);
    }

    /**
     * Update a section
     */
    public function update(Request $request, Section $section)
    {
        // Check authorization
        if (auth()->id() !== $section->course->user_id && !\App\Support\Authorize::isAdmin(auth()->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'position' => 'sometimes|integer|min:0',
        ]);

        $section->update($validated);

        return response()->json([
            'data' => $section,
            'message' => 'Section updated successfully',
        ]);
    }

    /**
     * Delete a section
     */
    public function destroy(Section $section)
    {
        // Check authorization
        if (auth()->id() !== $section->course->user_id && !\App\Support\Authorize::isAdmin(auth()->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if section has lessons
        if ($section->lessons()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete section with lessons. Please delete lessons first.',
            ], 422);
        }

        $section->delete();

        return response()->json([
            'message' => 'Section deleted successfully',
        ]);
    }

    /**
     * Reorder sections
     */
    public function reorder(Request $request, Course $course)
    {
        // Check authorization
        if (auth()->id() !== $course->user_id && !\App\Support\Authorize::isAdmin(auth()->user())) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:sections,id',
            'sections.*.position' => 'required|integer|min:0',
        ]);

        foreach ($validated['sections'] as $sectionData) {
            Section::where('id', $sectionData['id'])
                ->where('course_id', $course->id)
                ->update(['position' => $sectionData['position']]);
        }

        return response()->json([
            'message' => 'Sections reordered successfully',
        ]);
    }
}
