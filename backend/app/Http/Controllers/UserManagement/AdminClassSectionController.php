<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeClass;
use App\Models\ClassSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminClassSectionController extends Controller
{
    public function index(Request $request, AdministrativeClass $adminClass): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $sections = $adminClass->classSections()
            ->with(['course:id,title,code', 'term:id,name,code', 'lecturer:id,name,email'])
            ->orderByPivot('term_number')
            ->orderBy('id')
            ->get();

        return response()->json($sections);
    }

    public function attach(Request $request, AdministrativeClass $adminClass): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'class_section_ids' => ['required', 'array', 'min:1'],
            'class_section_ids.*' => ['integer', 'exists:class_sections,id'],
            'term_number' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        $sectionIds = ClassSection::query()
            ->whereIn('id', $validated['class_section_ids'])
            ->pluck('id')
            ->all();

        $payload = [];
        foreach ($sectionIds as $sectionId) {
            $payload[$sectionId] = [
                'term_number' => $validated['term_number'],
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ];
        }

        $adminClass->classSections()->syncWithoutDetaching($payload);

        return response()->json([
            'message' => 'Đã gán lớp tín chỉ cho lớp hành chính.',
        ]);
    }

    public function detach(Request $request, AdministrativeClass $adminClass, ClassSection $section): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::table('administrative_class_class_section')
            ->where('administrative_class_id', $adminClass->id)
            ->where('class_section_id', $section->id)
            ->delete();

        return response()->json(['message' => 'Đã hủy gán lớp tín chỉ.']);
    }
}

