<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\Course;
use App\Models\Order;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): ?JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    public function uploadAsset(Request $request, MediaService $mediaService): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
            'folder' => ['nullable', 'string', 'in:users,settings,courses'],
            'old_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $folder = match ($validated['folder'] ?? 'users') {
            'settings' => 'admin/settings',
            'courses' => 'admin/courses',
            default => 'admin/users',
        };

        $uploaded = $mediaService->upload($request->file('file'), $folder);

        if (!empty($validated['old_path']) && $mediaService->exists($validated['old_path'])) {
            $mediaService->delete($validated['old_path']);
        }

        return response()->json([
            'message' => 'Upload successful',
            'path' => $uploaded['path'],
            'url' => $mediaService->getUrl($uploaded['path']),
            'meta' => $uploaded,
        ], 201);
    }


    public function stats(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $paidStatuses = ['paid', 'completed'];
        $totalRevenue = Order::whereIn('status', $paidStatuses)->sum('amount');

        $coursesByStatus = Course::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $monthKeys = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->format('Y-m'));

        $revenueRows = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        $userRows = User::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        return response()->json([
            'total_users'       => User::count(),
            'total_courses'     => Course::count(),
            'total_orders'      => Order::count(),
            'total_revenue'     => (int) $totalRevenue,
            'total_students'    => User::role('student')->count(),
            'total_instructors' => User::role('instructor')->count(),
            'courses_by_status' => $coursesByStatus,
            'revenue_by_month'  => $this->hydrateMonthlySeries($monthKeys, $revenueRows),
            'new_users_by_month' => $this->hydrateMonthlySeries($monthKeys, $userRows),
            'top_courses'       => $this->getTopCourses(),
            'engagement'        => $this->getEngagementStats(),
        ]);
    }

    private function getTopCourses(): Collection
    {
        return Course::query()
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get(['id', 'title', 'enrollments_count']);
    }

    private function getEngagementStats(): array
    {
        $lessonProgressQuery = \App\Models\LessonProgress::query();

        if (Schema::hasColumn('lesson_progress', 'is_completed')) {
            $lessonProgressQuery->where('is_completed', true);
        } elseif (Schema::hasColumn('lesson_progress', 'completed_at')) {
            $lessonProgressQuery->whereNotNull('completed_at');
        } else {
            $lessonProgressQuery->whereRaw('1 = 0');
        }

        $activeStudentsQuery = User::role('student');

        if (Schema::hasColumn('users', 'last_login_at')) {
            $activeStudentsQuery->where('last_login_at', '>=', now()->subWeek());
        } else {
            $activeStudentsQuery->whereNotNull('created_at');
        }

        return [
            'avg_quiz_score' => \App\Models\QuizAttempt::avg('score') ?? 0,
            'total_completions' => $lessonProgressQuery->count(),
            'active_students_this_week' => $activeStudentsQuery->count(),
        ];
    }

    public function users(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = User::with('roles');

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $users = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,instructor,student'],
        ]);

        if ($request->user()->id === $user->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Không thể tự gỡ quyền admin của chính bạn.',
            ], 422);
        }

        $user->syncRoles([$validated['role']]);
        $user->load('roles');

        return response()->json([
            'message' => 'Role updated',
            'user'    => $user,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'role' => ['required', 'string', 'in:admin,instructor,student'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'avatar' => $validated['avatar'] ?? null,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$validated['role']]);
        $user->load('roles');

        return response()->json([
            'message' => 'User created',
            'user' => $user,
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'role' => ['sometimes', 'required', 'string', 'in:admin,instructor,student'],
        ]);

        if (
            isset($validated['role'])
            && $request->user()->id === $user->id
            && $validated['role'] !== 'admin'
        ) {
            return response()->json([
                'message' => 'Không thể tự gỡ quyền admin của chính bạn.',
            ], 422);
        }

        $user->fill(collect($validated)->except(['password', 'role'])->toArray());
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->load('roles');

        return response()->json([
            'message' => 'User updated',
            'user' => $user,
        ]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Không thể tự xóa tài khoản của chính bạn.'], 422);
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return response()->json(['message' => 'Không thể xóa admin cuối cùng của hệ thống.'], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted',
        ]);
    }

    public function courses(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Course::with('instructor:id,name,avatar', 'category')
            ->withCount('lessons', 'enrollments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $courses = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($courses);
    }

    public function showCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $course->load([
            'instructor:id,name,email,avatar',
            'category:id,name,slug',
            'sections.lessons' => fn ($q) => $q->orderBy('order'),
            'lessons' => fn($q) => $q->select('id','course_id','title','description','video_url','duration','order','is_preview','created_at')->orderBy('order'),
        ]);
        $course->loadCount('lessons', 'enrollments');

        $previewLesson = $course->lessons->firstWhere('is_preview', true) ?? $course->lessons->first();

        return response()->json([
            ...$course->toArray(),
            'preview_urls' => [
                'course' => "/courses/{$course->id}",
                'learn' => $previewLesson ? "/learn/{$course->id}/{$previewLesson->id}" : null,
                'curriculum' => "/admin/courses/{$course->id}/curriculum",
            ],
        ]);
    }

    public function approveCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $course->update([
            'status'       => 'published',
            'published_at' => now(),
            'reject_reason' => null,
        ]);

        // Notify instructor
        \App\Models\Notification::send(
            $course->user_id,
            'course_approved',
            'Khóa học đã được duyệt',
            "Khóa học \"{$course->title}\" đã được phê duyệt và xuất bản thành công.",
            "/courses/{$course->id}"
        );

        return response()->json([
            'message' => 'Course approved',
            'course'  => $course,
        ]);
    }

    public function rejectCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:1000'],
        ]);

        $course->update([
            'status'        => 'rejected',
            'reject_reason' => $validated['reject_reason'],
        ]);

        // Notify instructor
        \App\Models\Notification::send(
            $course->user_id,
            'course_rejected',
            'Khóa học bị từ chối',
            "Khóa học \"{$course->title}\" bị từ chối: {$validated['reject_reason']}",
            "/instructor/courses/{$course->id}/curriculum"
        );

        return response()->json([
            'message' => 'Course rejected',
            'course'  => $course,
        ]);
    }

    private function hydrateMonthlySeries(Collection $monthKeys, Collection $rows): array
    {
        return $monthKeys->map(function (string $monthKey) use ($rows) {
            return [
                'month' => $monthKey,
                'label' => $this->formatMonthLabel($monthKey),
                'value' => (int) ($rows[$monthKey] ?? 0),
            ];
        })->values()->all();
    }

    private function formatMonthLabel(string $monthKey): string
    {
        [$year, $month] = explode('-', $monthKey);

        return sprintf('%s/%s', $month, substr($year, -2));
    }

    // ─── Categories ─────────────────────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $categories = Category::withCount('courses')
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:10'],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);

        $category = Category::create($validated);
        $category->load('parent:id,name');
        $category->loadCount('courses');

        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:10'],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        }

        $category->update($validated);
        $category->load('parent:id,name');
        $category->loadCount('courses');

        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if ($category->courses()->exists()) {
            return response()->json(['message' => 'Không thể xóa danh mục đang có khóa học.'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Order::with([
            'user:id,name,email,avatar',
            'course:id,title,thumbnail',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('course', fn ($c) => $c->where('title', 'like', '%' . $request->search . '%'));
            });
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($orders);
    }

    public function showOrder(Request $request, Order $order): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $order->load(['user:id,name,email,avatar', 'course:id,title,thumbnail,price']);

        return response()->json($order);
    }

    // ─── Reviews ────────────────────────────────────────────────────────────────

    public function reviews(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Review::with([
            'user:id,name,email,avatar',
            'course:id,title',
        ]);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('comment', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('course', fn ($c) => $c->where('title', 'like', '%' . $request->search . '%'));
            });
        }

        $reviews = $query->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($reviews);
    }

    public function destroyReview(Request $request, Review $review): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted']);
    }

    // ─── Site Settings ─────────────────────────────────────────────────────────

    public function siteSettings(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        return response()->json(SiteSetting::getAll());
    }

    public function updateSiteSettings(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'site_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'site_logo'        => ['sometimes', 'nullable', 'string', 'max:2048'],
            'site_favicon'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'smtp_host'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_port'        => ['sometimes', 'nullable', 'string', 'max:10'],
            'smtp_username'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_password'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_encryption'  => ['sometimes', 'nullable', 'string', 'in:tls,ssl,none'],
            'smtp_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'smtp_from_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        SiteSetting::setMany($validated);

        return response()->json([
            'message' => 'Cập nhật cài đặt thành công',
            'settings' => SiteSetting::getAll(),
        ]);
    }

    public function publicSiteSettings(): JsonResponse
    {
        $keys = ['site_name', 'site_description', 'site_logo', 'site_favicon'];
        $settings = SiteSetting::whereIn('key', $keys)->pluck('value', 'key');

        return response()->json($settings);
    }
}
