<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagController extends Controller
{
    protected string $aiServiceUrl;

    public function __construct()
    {
        $this->aiServiceUrl = config('services.ai_service.url') . '/rag';
    }

    /**
     * Ingest document from URL (Admin only)
     */
    public function ingestFromUrl(Request $request): JsonResponse
    {
        $request->validate([
            'file_url' => 'required|url',
            'subject_name' => 'nullable|string',
            'course_id' => 'nullable|integer',
            'collection_name' => 'nullable|string',
        ]);

        try {
            $response = Http::timeout(120)->post($this->aiServiceUrl . '/ingest/url', [
                'file_url' => $request->file_url,
                'subject_name' => $request->subject_name,
                'course_id' => $request->course_id,
                'collection_name' => $request->collection_name,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => $response->json('detail') ?? 'Không thể ingest tài liệu từ URL.'
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('RAG ingest URL failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload and ingest document file (Admin only)
     */
    public function ingestUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,docx|max:20480', // Max 20MB
            'subject_name' => 'nullable|string',
            'course_id' => 'nullable|integer',
            'collection_name' => 'nullable|string',
        ]);

        try {
            $file = $request->file('file');
            
            // Forward multipart request to FastAPI
            $response = Http::timeout(120)
                ->attach('file', file_get_contents($file->getPathname()), $file->getClientOriginalName())
                ->post($this->aiServiceUrl . '/ingest/upload', [
                    'subject_name' => $request->input('subject_name', ''),
                    'course_id' => $request->input('course_id', 0),
                    'collection_name' => $request->input('collection_name', ''),
                ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => $response->json('detail') ?? 'Không thể ingest file tải lên.'
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('RAG ingest upload failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Query semantic chunks (Admin or testing)
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string',
            'course_id' => 'nullable|integer',
            'subject_name' => 'nullable|string',
            'top_k' => 'nullable|integer|min:1|max:10',
        ]);

        try {
            $response = Http::timeout(30)->post($this->aiServiceUrl . '/query', [
                'question' => $request->question,
                'course_id' => $request->course_id,
                'subject_name' => $request->subject_name,
                'top_k' => $request->input('top_k', 5),
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Không thể truy vấn RAG.'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List collections (Admin only)
     */
    public function collections(): JsonResponse
    {
        try {
            $response = Http::timeout(30)->get($this->aiServiceUrl . '/collections');

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => 'Không thể tải danh sách collections.'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete collection (Admin only)
     */
    public function deleteCollection(string $name): JsonResponse
    {
        try {
            $response = Http::timeout(30)->delete($this->aiServiceUrl . "/collections/{$name}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => $response->json('detail') ?? 'Không thể xóa collection.'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }
}
