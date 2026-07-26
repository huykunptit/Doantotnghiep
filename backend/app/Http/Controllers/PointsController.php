<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PointsController extends Controller
{
    public function __construct(private readonly PointService $pointService) {}

    // GET /points/summary
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        $recentTransactions = $user->pointTransactions()
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'balance'             => (int) $user->points_balance,
            'streak_days'         => (int) $user->streak_days,
            'last_login_date'     => $user->last_login_date,
            'recent_transactions' => $recentTransactions,
        ]);
    }

    // GET /points/transactions
    public function transactions(Request $request): JsonResponse
    {
        $txs = $request->user()
            ->pointTransactions()
            ->latest()
            ->paginate(20);

        return response()->json($txs);
    }

    // POST /points/daily-login
    public function dailyLogin(Request $request): JsonResponse
    {
        $tx = $this->pointService->handleDailyLogin($request->user());

        if (!$tx) {
            return response()->json([
                'message'  => 'Bạn đã nhận điểm đăng nhập hôm nay rồi.',
                'balance'  => (int) $request->user()->fresh()->points_balance,
                'streak'   => (int) $request->user()->fresh()->streak_days,
                'rewarded' => false,
            ]);
        }

        $user = $request->user()->fresh();

        return response()->json([
            'message'  => "Nhận thành công! +{$tx->amount} điểm.",
            'earned'   => $tx->amount,
            'balance'  => (int) $user->points_balance,
            'streak'   => (int) $user->streak_days,
            'rewarded' => true,
        ]);
    }

    // GET /vouchers — public shop listing
    public function shopIndex(Request $request): JsonResponse
    {
        $vouchers = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('total_quantity')->orWhereColumn('redeemed_count', '<', 'total_quantity');
            })
            ->orderBy('points_cost')
            ->with('course:id,title,thumbnail')
            ->get();

        return response()->json($vouchers);
    }

    // POST /vouchers/{voucher}/redeem
    public function redeem(Request $request, Voucher $voucher): JsonResponse
    {
        $user = $request->user();

        if (!$voucher->isAvailable()) {
            return response()->json(['message' => 'Voucher không còn khả dụng.'], 422);
        }

        if ($user->points_balance < $voucher->points_cost) {
            return response()->json([
                'message' => 'Không đủ điểm. Bạn cần thêm ' .
                    ($voucher->points_cost - $user->points_balance) . ' điểm.',
            ], 422);
        }

        $userVoucher = DB::transaction(function () use ($user, $voucher) {
            $this->pointService->redeem(
                $user,
                (int) $voucher->points_cost,
                "Đổi voucher: {$voucher->name}"
            );

            $voucher->increment('redeemed_count');

            return UserVoucher::create([
                'user_id'      => $user->id,
                'voucher_id'   => $voucher->id,
                'code'         => strtoupper(Str::random(12)),
                'status'       => 'unused',
                'points_spent' => $voucher->points_cost,
                'expires_at'   => now()->addDays(90),
            ]);
        });

        return response()->json([
            'message'      => 'Đổi voucher thành công!',
            'user_voucher' => $userVoucher->load('voucher'),
            'balance'      => (int) $user->fresh()->points_balance,
        ], 201);
    }

    // GET /me/vouchers — user's redeemed vouchers
    public function myVouchers(Request $request): JsonResponse
    {
        $vouchers = $request->user()
            ->userVouchers()
            ->with('voucher.course:id,title,thumbnail')
            ->latest()
            ->get();

        return response()->json($vouchers);
    }

    // GET /points/quests — list of available quests with user completion state
    public function quests(Request $request): JsonResponse
    {
        $user = $request->user();

        $quests = [
            [
                'key'         => 'login_daily',
                'title'       => 'Đăng nhập mỗi ngày',
                'description' => 'Nhận điểm thưởng khi đăng nhập hàng ngày.',
                'points'      => PointService::POINTS['login_daily'],
                'icon'        => 'calendar-check',
                'category'    => 'daily',
                'done_today'  => $user->last_login_date === now()->toDateString(),
            ],
            [
                'key'         => 'streak_7',
                'title'       => 'Chuỗi 7 ngày liên tiếp',
                'description' => 'Đăng nhập 7 ngày liên tiếp để nhận thưởng.',
                'points'      => PointService::POINTS['streak_7'],
                'icon'        => 'flame',
                'category'    => 'milestone',
                'progress'    => min($user->streak_days, 7),
                'target'      => 7,
            ],
            [
                'key'         => 'streak_30',
                'title'       => 'Chuỗi 30 ngày liên tiếp',
                'description' => 'Đăng nhập 30 ngày liên tiếp — thưởng lớn!',
                'points'      => PointService::POINTS['streak_30'],
                'icon'        => 'trophy',
                'category'    => 'milestone',
                'progress'    => min($user->streak_days, 30),
                'target'      => 30,
            ],
            [
                'key'         => 'lesson_complete',
                'title'       => 'Hoàn thành bài học',
                'description' => 'Nhận điểm mỗi khi hoàn thành một bài học.',
                'points'      => PointService::POINTS['lesson_complete'],
                'icon'        => 'book-open-check',
                'category'    => 'learning',
                'recurring'   => true,
            ],
            [
                'key'         => 'course_complete',
                'title'       => 'Hoàn thành khóa học',
                'description' => 'Hoàn thành toàn bộ bài học trong một khóa học.',
                'points'      => PointService::POINTS['course_complete'],
                'icon'        => 'graduation-cap',
                'category'    => 'learning',
                'recurring'   => true,
            ],
            [
                'key'         => 'exam_high_score',
                'title'       => 'Đạt điểm cao trong kỳ thi',
                'description' => 'Đạt ≥ 90% trong bài thi để nhận thưởng.',
                'points'      => PointService::POINTS['exam_high_score'],
                'icon'        => 'medal',
                'category'    => 'learning',
                'recurring'   => true,
            ],
            [
                'key'         => 'purchase',
                'title'       => 'Mua khóa học',
                'description' => 'Nhận điểm thưởng mỗi khi mua khóa học mới.',
                'points'      => PointService::POINTS['purchase'],
                'icon'        => 'shopping-bag',
                'category'    => 'engagement',
                'recurring'   => true,
            ],
            [
                'key'         => 'survey',
                'title'       => 'Hoàn thành khảo sát',
                'description' => 'Tham gia khảo sát và chia sẻ ý kiến của bạn.',
                'points'      => PointService::POINTS['survey'],
                'icon'        => 'clipboard-list',
                'category'    => 'engagement',
                'recurring'   => true,
            ],
            [
                'key'         => 'review_course',
                'title'       => 'Đánh giá khóa học',
                'description' => 'Viết nhận xét sau khi hoàn thành khóa học.',
                'points'      => PointService::POINTS['review_course'],
                'icon'        => 'star',
                'category'    => 'engagement',
                'recurring'   => true,
            ],
        ];

        $totalEarned = $user->pointTransactions()->where('type', 'earn')->sum('amount');

        return response()->json([
            'quests'       => $quests,
            'balance'      => (int) $user->points_balance,
            'streak_days'  => (int) $user->streak_days,
            'total_earned' => (int) $totalEarned,
        ]);
    }

    // GET /points/leaderboard
    public function leaderboard(Request $request): JsonResponse
    {
        $top = User::where('user_type', 'student')
            ->orderByDesc('points_balance')
            ->take(20)
            ->get(['id', 'name', 'student_code', 'avatar', 'points_balance', 'streak_days']);

        $me = $request->user();
        $myRank = User::where('user_type', 'student')
            ->where('points_balance', '>', $me->points_balance)
            ->count() + 1;

        return response()->json([
            'top'     => $top,
            'my_rank' => $myRank,
            'my_balance' => (int) $me->points_balance,
        ]);
    }

    // ── Admin / Instructor endpoints ────────────────────────────────────────

    // GET /admin/vouchers
    public function adminVoucherIndex(Request $request): JsonResponse
    {
        $this->requireAdminOrInstructor($request);

        $vouchers = Voucher::with('course:id,title')
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($vouchers);
    }

    // POST /admin/vouchers
    public function adminVoucherStore(Request $request): JsonResponse
    {
        $this->requireAdminOrInstructor($request);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', 'in:discount_percent,discount_fixed,free_course,physical_gift,ai_quota'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'points_cost'    => ['required', 'integer', 'min:1'],
            'total_quantity' => ['nullable', 'integer', 'min:1'],
            'is_active'      => ['boolean'],
            'expires_at'     => ['nullable', 'date'],
            'course_id'      => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $voucher = Voucher::create($data);

        return response()->json($voucher, 201);
    }

    // PUT /admin/vouchers/{voucher}
    public function adminVoucherUpdate(Request $request, Voucher $voucher): JsonResponse
    {
        $this->requireAdminOrInstructor($request);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', 'in:discount_percent,discount_fixed,free_course,physical_gift,ai_quota'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'points_cost'    => ['required', 'integer', 'min:1'],
            'total_quantity' => ['nullable', 'integer', 'min:1'],
            'is_active'      => ['boolean'],
            'expires_at'     => ['nullable', 'date'],
            'course_id'      => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $voucher->update($data);

        return response()->json($voucher->fresh());
    }

    // DELETE /admin/vouchers/{voucher}
    public function adminVoucherDestroy(Request $request, Voucher $voucher): JsonResponse
    {
        $this->requireAdminOrInstructor($request);

        if ($voucher->redeemed_count > 0) {
            return response()->json(['message' => 'Không thể xóa voucher đã có người đổi.'], 422);
        }

        $voucher->delete();

        return response()->json(['message' => 'Đã xóa voucher.']);
    }

    // GET /admin/points/stats
    public function adminStats(Request $request): JsonResponse
    {
        $this->requireAdminOrInstructor($request);

        $totalIssued  = PointTransaction::where('type', 'earn')->sum('amount');
        $totalRedeemed = PointTransaction::where('type', 'redeem')->selectRaw('SUM(ABS(amount))')->value('result') ?? 0;
        $totalRedeemed = PointTransaction::where('type', 'redeem')->sum(DB::raw('ABS(amount)'));
        $activeVouchers = Voucher::where('is_active', true)->count();
        $redemptions    = UserVoucher::count();

        // Daily earned points last 14 days
        $trend = DB::table('point_transactions')
            ->selectRaw('DATE(created_at) as day, SUM(CASE WHEN type="earn" THEN amount ELSE 0 END) as earned, COUNT(CASE WHEN type="redeem" THEN 1 END) as redeemed_count')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trendData = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $trendData[] = [
                'date'            => $d,
                'earned'          => (int) ($trend->get($d)?->earned ?? 0),
                'redeemed_count'  => (int) ($trend->get($d)?->redeemed_count ?? 0),
            ];
        }

        // Top students by balance
        $topStudents = User::where('user_type', 'student')
            ->orderByDesc('points_balance')
            ->take(10)
            ->get(['id', 'name', 'student_code', 'avatar', 'points_balance', 'streak_days']);

        // Recent redemptions
        $recentRedemptions = UserVoucher::with(['user:id,name,student_code', 'voucher:id,name,points_cost'])
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'totals' => [
                'total_issued'    => (int) $totalIssued,
                'total_redeemed'  => (int) $totalRedeemed,
                'active_vouchers' => $activeVouchers,
                'redemptions'     => $redemptions,
            ],
            'trend'              => $trendData,
            'top_students'       => $topStudents,
            'recent_redemptions' => $recentRedemptions,
        ]);
    }

    private function requireAdminOrInstructor(Request $request): void
    {
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            abort(403, 'Forbidden');
        }
    }
}

