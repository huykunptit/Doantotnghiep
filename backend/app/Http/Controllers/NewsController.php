<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 12), 50);
        $posts = NewsPost::published()
            ->with('author:id,name')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        $posts->getCollection()->transform(fn (NewsPost $p) => $this->transform($p));

        return response()->json($posts);
    }

    public function publicShow(string $slug): JsonResponse
    {
        $post = NewsPost::published()
            ->with('author:id,name')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($this->transform($post, true));
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $q = NewsPost::query()->with('author:id,name')->orderByDesc('updated_at');

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        $posts = $q->paginate($perPage);
        $posts->getCollection()->transform(fn (NewsPost $p) => $this->transform($p));

        return response()->json($posts);
    }

    public function adminShow(NewsPost $newsPost): JsonResponse
    {
        return response()->json($this->transform($newsPost->load('author:id,name'), true));
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $status = $validated['status'] ?? 'draft';

        $post = NewsPost::create([
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'slug' => NewsPost::uniqueSlug($validated['title']),
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'] ?? null,
            'cover_image' => $validated['cover_image'] ?? null,
            'status' => $status,
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'published_at' => $status === 'published'
                ? ($validated['published_at'] ?? now())
                : null,
        ]);

        if ($status === 'published' && ($validated['notify_students'] ?? false)) {
            $this->notifyStudents($post);
        }

        return response()->json($this->transform($post->load('author:id,name'), true), 201);
    }

    public function adminUpdate(Request $request, NewsPost $newsPost): JsonResponse
    {
        $validated = $this->validatePayload($request, true);
        $wasPublished = $newsPost->status === 'published';

        if (isset($validated['title']) && $validated['title'] !== $newsPost->title) {
            $validated['slug'] = NewsPost::uniqueSlug($validated['title'], $newsPost->id);
        }

        if (isset($validated['status']) && $validated['status'] === 'published' && !$newsPost->published_at) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }
        if (isset($validated['status']) && $validated['status'] === 'draft') {
            // keep published_at history optional — leave as-is
        }

        $newsPost->update($validated);
        $newsPost->refresh();

        if (
            !$wasPublished
            && $newsPost->status === 'published'
            && ($request->boolean('notify_students'))
        ) {
            $this->notifyStudents($newsPost);
        }

        return response()->json($this->transform($newsPost->load('author:id,name'), true));
    }

    public function adminDestroy(NewsPost $newsPost): JsonResponse
    {
        $newsPost->delete();

        return response()->json(['message' => 'Đã xóa tin tức.']);
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:draft,published'],
            'is_featured' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'notify_students' => ['sometimes', 'boolean'],
        ];

        return $request->validate($rules);
    }

    private function notifyStudents(NewsPost $post): void
    {
        $link = '/news/' . $post->slug;
        User::role('student')->pluck('id')->each(function ($id) use ($post, $link) {
            Notification::send(
                (int) $id,
                'news',
                'Tin tức mới: ' . $post->title,
                $post->excerpt ?: 'Có tin tức mới trên hệ thống.',
                $link,
                false,
            );
        });
    }

    private function transform(NewsPost $post, bool $withContent = false): array
    {
        $cover = $post->cover_image;
        if ($cover && !str_starts_with($cover, 'http')) {
            $cover = Storage::disk('public')->url($cover);
        }

        $data = [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'cover_image' => $post->cover_image,
            'cover_image_url' => $cover,
            'status' => $post->status,
            'is_featured' => $post->is_featured,
            'published_at' => $post->published_at?->toIso8601String(),
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'author' => $post->author ? [
                'id' => $post->author->id,
                'name' => $post->author->name,
            ] : null,
        ];

        if ($withContent) {
            $data['content'] = $post->content;
        }

        return $data;
    }
}
