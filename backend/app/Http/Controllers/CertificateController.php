<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\UserCertificate;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CertificateController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin') && !$request->user()->hasRole('instructor')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(CertificateTemplate::all());
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'background_image_url' => 'nullable|string|max:2048',
            'fields_config' => 'nullable|array',
        ]);

        $backgroundUrl = $validated['background_image_url'] ?? null;

        if ($request->hasFile('background_image')) {
            $result = $this->media->upload($request->file('background_image'), 'certificates/templates');
            $backgroundUrl = $this->media->getUrl($result['path']);
        }

        $template = CertificateTemplate::create([
            'name' => $validated['name'],
            'background_image_url' => $backgroundUrl,
            'fields_config' => $validated['fields_config'] ?? $this->defaultFieldsConfig(),
        ]);

        return response()->json([
            'message' => 'Certificate template created',
            'template' => $template,
        ], 201);
    }

    public function update(Request $request, CertificateTemplate $template): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'background_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'background_image_url' => 'nullable|string|max:2048',
            'fields_config' => 'nullable|array',
        ]);

        if ($request->hasFile('background_image')) {
            $result = $this->media->upload($request->file('background_image'), 'certificates/templates');
            $validated['background_image_url'] = $this->media->getUrl($result['path']);
        }

        unset($validated['background_image']);
        $template->update($validated);

        return response()->json([
            'message' => 'Certificate template updated',
            'template' => $template->fresh(),
        ]);
    }

    public function updateFields(Request $request, CertificateTemplate $template): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'fields_config' => 'required|array',
            'fields_config.*.key' => 'required|string',
            'fields_config.*.label' => 'required|string',
            'fields_config.*.x' => 'required|numeric|min:0|max:100',
            'fields_config.*.y' => 'required|numeric|min:0|max:100',
            'fields_config.*.font_size' => 'required|numeric|min:6|max:120',
            'fields_config.*.font_family' => 'required|string|max:100',
            'fields_config.*.color' => 'required|string|max:30',
            'fields_config.*.font_weight' => 'required|string|in:normal,bold',
            'fields_config.*.text_align' => 'required|string|in:left,center,right',
            'fields_config.*.visible' => 'required|boolean',
        ]);

        $template->update(['fields_config' => $validated['fields_config']]);

        return response()->json([
            'message' => 'Fields config saved',
            'template' => $template->fresh(),
        ]);
    }

    public function destroy(Request $request, CertificateTemplate $template): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $template->delete();

        return response()->json(['message' => 'Certificate template deleted']);
    }

    public function myCertificates(Request $request): JsonResponse
    {
        $certificates = UserCertificate::with([
            'course:id,title',
            'careerPath:id,title,slug',
            'certificateTemplate',
        ])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get();

        return response()->json($certificates);
    }

    public function showByCredential(string $credentialId): JsonResponse
    {
        $certificate = UserCertificate::with([
            'user:id,name',
            'course:id,title',
            'careerPath:id,title,slug',
            'certificateTemplate',
        ])
            ->where('credential_id', $credentialId)
            ->firstOrFail();

        return response()->json($certificate);
    }

    private function defaultFieldsConfig(): array
    {
        return [
            [
                'key' => 'student_name',
                'label' => 'Tên học viên',
                'x' => 50,
                'y' => 42,
                'font_size' => 36,
                'font_family' => 'Georgia, serif',
                'color' => '#1a1a1a',
                'font_weight' => 'bold',
                'text_align' => 'center',
                'visible' => true,
            ],
            [
                'key' => 'course_title',
                'label' => 'Tên khoá học',
                'x' => 50,
                'y' => 55,
                'font_size' => 18,
                'font_family' => 'Arial, sans-serif',
                'color' => '#444444',
                'font_weight' => 'normal',
                'text_align' => 'center',
                'visible' => true,
            ],
            [
                'key' => 'issued_date',
                'label' => 'Ngày cấp',
                'x' => 50,
                'y' => 68,
                'font_size' => 13,
                'font_family' => 'Arial, sans-serif',
                'color' => '#666666',
                'font_weight' => 'normal',
                'text_align' => 'center',
                'visible' => true,
            ],
            [
                'key' => 'credential_id',
                'label' => 'Mã xác nhận',
                'x' => 50,
                'y' => 78,
                'font_size' => 11,
                'font_family' => 'Courier New, monospace',
                'color' => '#888888',
                'font_weight' => 'normal',
                'text_align' => 'center',
                'visible' => true,
            ],
        ];
    }
}
