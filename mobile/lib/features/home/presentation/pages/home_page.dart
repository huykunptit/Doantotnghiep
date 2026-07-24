import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../auth/providers/auth_provider.dart';
import '../../../dashboard/providers/dashboard_provider.dart';
import '../../../courses/providers/course_catalog_provider.dart';
import '../../../courses/data/models/course_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../notifications/providers/notification_providers.dart';
import '../../../ai/presentation/widgets/recommendations_section.dart';
import '../../../ai/providers/ai_providers.dart';

class HomePage extends ConsumerWidget {
  const HomePage({super.key});

  static const routeName = '/home';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authNotifierProvider).valueOrNull;
    final dashboardAsync = ref.watch(studentDashboardProvider);
    final catalogAsync = ref.watch(courseCatalogProvider());
    final unreadCountAsync = ref.watch(unreadNotificationsCountProvider);
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(studentDashboardProvider);
          ref.invalidate(courseCatalogProvider());
          ref.invalidate(recommendationsProvider);
        },
        child: CustomScrollView(
          slivers: [
            // ── App Bar ──
            SliverAppBar(
              pinned: true,
              floating: false,
              expandedHeight: 0,
              surfaceTintColor: Colors.transparent,
              title: Row(
                children: [
                  CircleAvatar(
                    radius: 17,
                    backgroundColor: AppColors.primary400,
                    backgroundImage: user?.avatar != null
                        ? CachedNetworkImageProvider(user!.avatar!)
                        : null,
                    child: user?.avatar == null
                        ? Text(
                            user?.name.isNotEmpty == true
                                ? user!.name[0].toUpperCase()
                                : 'U',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                          )
                        : null,
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Xin chào, ${user?.name.split(' ').last ?? "Học viên"} 👋',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          'Hôm nay học gì nào?',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              actions: [
                unreadCountAsync.when(
                  data: (count) => Stack(
                    clipBehavior: Clip.none,
                    children: [
                      IconButton(
                        icon: const Icon(Icons.notifications_none_rounded),
                        onPressed: () => context.push('/notifications'),
                      ),
                      if (count > 0)
                        Positioned(
                          top: 8,
                          right: 8,
                          child: Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: AppColors.error,
                              shape: BoxShape.circle,
                            ),
                          ),
                        ),
                    ],
                  ),
                  loading: () => IconButton(
                    icon: const Icon(Icons.notifications_none_rounded),
                    onPressed: () => context.push('/notifications'),
                  ),
                  error: (_, _) => IconButton(
                    icon: const Icon(Icons.notifications_none_rounded),
                    onPressed: () => context.push('/notifications'),
                  ),
                ),
                AppSpacing.w4,
              ],
            ),

            // ── Body ──
            SliverPadding(
              padding: const EdgeInsets.only(bottom: 32),
              sliver: SliverList(
                delegate: SliverChildListDelegate([
                  // Stats hero card
                  dashboardAsync.when(
                    loading: () => const _StatsShimmer(),
                    error: (_, _) => const SizedBox.shrink(),
                    data: (data) => _StatsHeroCard(data: data),
                  ),

                  AppSpacing.h24,

                  // Quick Actions Grid
                  const _QuickActionsSection(),

                  AppSpacing.h24,

                  // Continue learning
                  dashboardAsync.when(
                    loading: () => const SizedBox.shrink(),
                    error: (_, _) => const SizedBox.shrink(),
                    data: (data) {
                      if (data.currentEnrollments.isEmpty) return const SizedBox.shrink();
                      return _ContinueLearningSection(enrollments: data.currentEnrollments);
                    },
                  ),

                  // Personalized recommendations
                  const RecommendationsSection(),

                  // Latest courses header
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 0, 12, 0),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Khóa học mới nhất',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            letterSpacing: -0.2,
                          ),
                        ),
                        TextButton(
                          onPressed: () => context.go('/catalog'),
                          style: TextButton.styleFrom(
                            foregroundColor: AppColors.primary600,
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                          ),
                          child: const Text(
                            'Xem tất cả',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                        ),
                      ],
                    ),
                  ),
                  AppSpacing.h8,

                  // Course cards
                  catalogAsync.when(
                    loading: () => const _CoursesShimmer(),
                    error: (e, _) => Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 20),
                      child: Text('Lỗi tải danh sách: $e'),
                    ),
                    data: (list) {
                      if (list.isEmpty) {
                        return const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 20, vertical: 24),
                          child: Center(child: Text('Không có khóa học nào.')),
                        );
                      }
                      return SizedBox(
                        height: 228,
                        child: ListView.builder(
                          scrollDirection: Axis.horizontal,
                          padding: const EdgeInsets.symmetric(horizontal: 20),
                          itemCount: list.length > 6 ? 6 : list.length,
                          itemBuilder: (context, index) =>
                              _CourseCard(course: list[index]),
                        ),
                      );
                    },
                  ),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Stats Hero Card ──────────────────────────────────────────
class _StatsHeroCard extends StatelessWidget {
  const _StatsHeroCard({required this.data});
  final dynamic data;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: isDark
            ? LinearGradient(
                colors: [AppColors.primary800, AppColors.primary600],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              )
            : LinearGradient(
                colors: [AppColors.primary600, AppColors.primary400],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary400.withValues(alpha: 0.3),
            blurRadius: 20,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            data.currentTerm?.name ?? 'Học kỳ hiện tại',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Colors.white70,
              letterSpacing: 0.3,
            ),
          ),
          AppSpacing.h4,
          const Text(
            'Tiến độ học tập',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: Colors.white,
              letterSpacing: -0.3,
            ),
          ),
          AppSpacing.h16,
          Row(
            children: [
              _StatChip(
                icon: Icons.menu_book_rounded,
                label: 'Đã đăng ký',
                value: '${data.totals.enrollments}',
              ),
              AppSpacing.w12,
              _StatChip(
                icon: Icons.pending_actions_rounded,
                label: 'Đang học',
                value: '${data.totals.inProgress}',
              ),
              AppSpacing.w12,
              _StatChip(
                icon: Icons.check_circle_rounded,
                label: 'Hoàn thành',
                value: '${data.totals.completed}',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          children: [
            Icon(icon, color: Colors.white, size: 18),
            AppSpacing.h8,
            Text(
              value,
              style: const TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
            ),
            Text(
              label,
              style: const TextStyle(
                fontSize: 10,
                color: Colors.white70,
                fontWeight: FontWeight.w500,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

// ── Continue Learning ────────────────────────────────────────
class _ContinueLearningSection extends StatelessWidget {
  const _ContinueLearningSection({required this.enrollments});
  final List<dynamic> enrollments;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Text(
            'Tiếp tục học tập',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: -0.2,
            ),
          ),
        ),
        AppSpacing.h8,
        SizedBox(
          height: 108,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            itemCount: enrollments.length,
            itemBuilder: (context, index) {
              final enroll = enrollments[index];
              return _ContinueCard(enroll: enroll);
            },
          ),
        ),
        AppSpacing.h24,
      ],
    );
  }
}

class _ContinueCard extends StatelessWidget {
  const _ContinueCard({required this.enroll});
  final dynamic enroll;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return GestureDetector(
      onTap: () => context.push('/courses/${enroll.courseId}'),
      child: Container(
        width: 240,
        margin: const EdgeInsets.only(right: 12),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkSurface : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isDark ? AppColors.darkBorder : AppColors.neutral200,
          ),
          boxShadow: isDark
              ? []
              : [
                  BoxShadow(
                    color: AppColors.neutral800.withValues(alpha: 0.05),
                    blurRadius: 12,
                    offset: const Offset(0, 2),
                  ),
                ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: AppColors.primary50,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.play_circle_outline_rounded,
                    color: AppColors.primary600,
                    size: 20,
                  ),
                ),
                AppSpacing.w12,
                Expanded(
                  child: Text(
                    enroll.course.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
            Row(
              children: [
                Icon(
                  enroll.course.courseMode == 'online'
                      ? Icons.wifi_rounded
                      : Icons.location_on_outlined,
                  size: 13,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                AppSpacing.w4,
                Text(
                  enroll.course.courseMode == 'online' ? 'Online' : 'Offline',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontSize: 11,
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
                if (enroll.course.creditValue != null) ...[
                  AppSpacing.w8,
                  Container(
                    width: 3,
                    height: 3,
                    decoration: BoxDecoration(
                      color: theme.colorScheme.onSurfaceVariant,
                      shape: BoxShape.circle,
                    ),
                  ),
                  AppSpacing.w8,
                  Text(
                    '${enroll.course.creditValue} TC',
                    style: theme.textTheme.bodySmall?.copyWith(
                      fontSize: 11,
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ── Course Card ───────────────────────────────────────────────
class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});
  final CourseListItemModel course;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return GestureDetector(
      onTap: () => context.push('/courses/${course.id}'),
      child: Container(
        width: 188,
        margin: const EdgeInsets.only(right: 14),
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkSurface : Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isDark ? AppColors.darkBorder : AppColors.neutral200,
          ),
          boxShadow: isDark
              ? []
              : [
                  BoxShadow(
                    color: AppColors.neutral800.withValues(alpha: 0.06),
                    blurRadius: 12,
                    offset: const Offset(0, 3),
                  ),
                ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Thumbnail
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: SizedBox(
                height: 108,
                width: double.infinity,
                child: course.thumbnail != null
                    ? CachedNetworkImage(
                        imageUrl: course.thumbnail!,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => _thumbnailPlaceholder(),
                      )
                    : _thumbnailPlaceholder(),
              ),
            ),

            // Info
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      course.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        height: 1.35,
                        color: theme.colorScheme.onSurface,
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Icon(Icons.star_rounded, color: Colors.amber.shade600, size: 13),
                        AppSpacing.w4,
                        Text(
                          course.avgRating.toStringAsFixed(1),
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontWeight: FontWeight.w600,
                            fontSize: 11,
                          ),
                        ),
                        AppSpacing.w8,
                        Text(
                          '(${course.enrollmentsCount})',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h4,
                    Text(
                      course.price > 0 ? '${course.price}đ' : 'Miễn phí',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: course.price > 0 ? AppColors.primary600 : AppColors.success,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _thumbnailPlaceholder() {
    return Container(
      color: AppColors.primary50,
      alignment: Alignment.center,
      child: const Icon(Icons.school_rounded, size: 36, color: AppColors.primary200),
    );
  }
}

// ── Shimmer placeholders ─────────────────────────────────────
class _StatsShimmer extends StatelessWidget {
  const _StatsShimmer();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      height: 116,
      decoration: BoxDecoration(
        color: AppColors.neutral100,
        borderRadius: BorderRadius.circular(20),
      ),
    );
  }
}

class _CoursesShimmer extends StatelessWidget {
  const _CoursesShimmer();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 228,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: 3,
        itemBuilder: (_, idx) => Container(
          width: 188,
          margin: const EdgeInsets.only(right: 14),
          decoration: BoxDecoration(
            color: AppColors.neutral100,
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
    );
  }
}

// ── Quick Actions Grid ─────────────────────────────────────────
class _QuickActionsSection extends StatelessWidget {
  const _QuickActionsSection();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final row1 = [
      _QuickActionItem(
        icon: Icons.alt_route_rounded,
        label: 'Lộ trình học',
        color: AppColors.primary400,
        onTap: () => context.push('/learning-path'),
      ),
      _QuickActionItem(
        icon: Icons.analytics_rounded,
        label: 'Bảng điểm',
        color: AppColors.secondary400,
        onTap: () => context.push('/transcript'),
      ),
      _QuickActionItem(
        icon: Icons.qr_code_scanner_rounded,
        label: 'Điểm danh QR',
        color: AppColors.success,
        onTap: () => context.push('/attendance'),
      ),
    ];

    final row2 = [
      _QuickActionItem(
        icon: Icons.verified_rounded,
        label: 'Chứng chỉ',
        color: AppColors.accent400,
        onTap: () => context.push('/certificates'),
      ),
      _QuickActionItem(
        icon: Icons.chat_bubble_outline_rounded,
        label: 'Trợ lý AI',
        color: Colors.purple,
        onTap: () => context.push('/ai-chat'),
      ),
      _QuickActionItem(
        icon: Icons.auto_awesome_rounded,
        label: 'AI Career',
        color: AppColors.secondary400,
        onTap: () => context.push('/career'),
      ),
    ];

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Chức năng chính',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: -0.2,
            ),
          ),
          AppSpacing.h12,
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: row1.map((item) => _QuickActionCard(item: item, isDark: isDark)).toList(),
          ),
          AppSpacing.h12,
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: row2.map((item) => _QuickActionCard(item: item, isDark: isDark)).toList(),
          ),
        ],
      ),
    );
  }
}

class _QuickActionItem {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  _QuickActionItem({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });
}

class _QuickActionCard extends StatelessWidget {
  const _QuickActionCard({required this.item, required this.isDark});
  final _QuickActionItem item;
  final bool isDark;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: item.onTap,
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 4),
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkSurface : Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isDark ? AppColors.darkBorder : AppColors.neutral200,
            ),
            boxShadow: isDark
                ? []
                : [
                    BoxShadow(
                      color: AppColors.neutral800.withValues(alpha: 0.04),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
          ),
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: item.color.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(item.icon, color: item.color, size: 22),
              ),
              AppSpacing.h8,
              Text(
                item.label,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
