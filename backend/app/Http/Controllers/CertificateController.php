<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\UserCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CertificateController extends Controller
{
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
            'background_image_url' => 'nullable|string|max:2048',
        ]);

        $template = CertificateTemplate::create($validated);

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
            'background_image_url' => 'nullable|string|max:2048',
        ]);

        $template->update($validated);

        return response()->json([
            'message' => 'Certificate template updated',
            'template' => $template,
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
        $certificates = UserCertificate::with(['course:id,title', 'certificateTemplate'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('issued_at')
            ->get();

        return response()->json($certificates);
    }

    public function showByCredential(string $credentialId): JsonResponse
    {
        $certificate = UserCertificate::with(['user:id,name', 'course:id,title', 'certificateTemplate'])
            ->where('credential_id', $credentialId)
            ->firstOrFail();

        return response()->json($certificate);
    }
}
