import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/path_detail_provider.dart';
import '../data/models/career_path_model.dart';
import '../data/repositories/path_repository.dart';
import '../../courses/providers/my_courses_provider.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class PathDetailPage extends ConsumerWidget {
  const PathDetailPage({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pathAsync = ref.watch(pathDetailProvider(slug));

    return pathAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.error_outline, size: 48, color: AppColors.error),
                AppSpacing.h12,
                Text(e.toString(), textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(pathDetailProvider(slug)),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
      ),
      data: (path) => _PathDetailView(path: path, slug: slug),
    );
  }
}

class _PathDetailView extends ConsumerStatefulWidget {
  const _PathDetailView({required this.path, required this.slug});
  final CareerPathDetail path;
  final String slug;

  @override
  ConsumerState<_PathDetailView> createState() => _PathDetailViewState();
}

class _PathDetailViewState extends ConsumerState<_PathDetailView> {
  bool _isProcessing = false;

  int get _coursesCount =>
      widget.path.pathCoursesCount > 0
          ? widget.path.pathCoursesCount
          : widget.path.pathCourses.length;

  Set<int> get _enrolledSet => widget.path.enrolledCourseIds.toSet();

  Future<void> _handlePurchase() async {
    setState(() => _isProcessing = true);
    try {
      final res = await ref
          .read(pathRepositoryProvider)
          .createPathOrder(widget.path.id);
      if (!mounted) return;
      if (res['enrolled'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              widget.path.price > 0
                  ? 'Mua lộ trình thành công!'
                  : 'Ghi danh lộ trình miễn phí thành công!',
            ),
            backgroundColor: AppColors.success,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
        ref.invalidate(pathDetailProvider(widget.slug));
        ref.invalidate(myEnrollmentsProvider);
      } else if (res['payment_url'] != null) {
        final success = await context.push<bool>(
          '/checkout-webview',
          extra: res['payment_url'],
        );
        if (success == true && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Thanh toán thành công!'),
              backgroundColor: AppColors.success,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          );
          ref.invalidate(pathDetailProvider(widget.slug));
          ref.invalidate(myEnrollmentsProvider);
        } else if (success == false && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Thanh toán chưa hoàn tất hoặc đã bị hủy.'),
              backgroundColor: AppColors.error,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  Future<void> _handleFollow() async {
    setState(() => _isProcessing = true);
    try {
      await ref.read(pathRepositoryProvider).followPath(widget.path.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: const Text('Đã theo dõi lộ trình!'),
          backgroundColor: AppColors.success,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
      ref.invalidate(pathDetailProvider(widget.slug));
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final path = widget.path;

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 220,
            pinned: true,
            flexibleSpace: FlexibleSpaceBar(
              titlePadding: const EdgeInsets.fromLTRB(56, 0, 16, 14),
              title: Text(
                path.title,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  shadows: [Shadow(color: Colors.black54, blurRadius: 8)],
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              background: path.coverUrl != null
                  ? Stack(
                      fit: StackFit.expand,
                      children: [
                        CachedNetworkImage(
                          imageUrl: path.coverUrl!,
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => _headerPlaceholder(isDark),
                        ),
                        Container(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [
                                Colors.transparent,
                                Colors.black.withValues(alpha: 0.6),
                              ],
                              stops: const [0.4, 1.0],
                            ),
                          ),
                        ),
                      ],
                    )
                  : _headerPlaceholder(isDark),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _StatBadge(
                        icon: Icons.menu_book_outlined,
                        label: '$_coursesCount khoá học',
                      ),
                      _StatBadge(
                        icon: Icons.payments_outlined,
                        label: path.price > 0 ? '${path.price}đ' : 'Miễn phí',
                        iconColor: path.price > 0 ? AppColors.primary400 : AppColors.success,
                      ),
                      if (path.isPurchased)
                        _StatBadge(
                          icon: Icons.check_circle_outline,
                          label: 'Đã sở hữu',
                          iconColor: AppColors.success,
                        ),
                    ],
                  ),
                  if (path.description != null) ...[
                    AppSpacing.h20,
                    Text(
                      'Mô tả lộ trình',
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.2,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      path.description!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        height: 1.6,
                      ),
                    ),
                  ],
                  AppSpacing.h24,
                  Row(
                    children: [
                      Text(
                        'Lộ trình khoá học',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                        ),
                      ),
                      AppSpacing.w8,
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primary50,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          '$_coursesCount khoá',
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppColors.primary600,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                  AppSpacing.h12,
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 100),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final item = path.pathCourses[index];
                  final courseTitle = item.course?.title ?? 'Khoá #${item.courseId}';
                  final isEnrolled = _enrolledSet.contains(item.courseId);
                  final canOpen = path.isPurchased && isEnrolled;

                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkSurface : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isDark ? AppColors.darkBorder : AppColors.neutral200,
                      ),
                    ),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: canOpen
                          ? () => context.push('/courses/${item.courseId}')
                          : () => context.push('/courses/${item.courseId}'),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 32,
                              height: 32,
                              decoration: BoxDecoration(
                                color: AppColors.primary50,
                                shape: BoxShape.circle,
                              ),
                              child: Center(
                                child: Text(
                                  '${index + 1}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primary600,
                                  ),
                                ),
                              ),
                            ),
                            AppSpacing.w12,
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    courseTitle,
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  AppSpacing.h4,
                                  Text(
                                    '${item.isRequired ? 'Bắt buộc' : 'Tuỳ chọn'}'
                                    '${item.course?.lessonsCount != null ? ' · ${item.course!.lessonsCount} bài' : ''}',
                                    style: theme.textTheme.bodySmall?.copyWith(
                                      fontSize: 11,
                                      color: theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (isEnrolled)
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.primary50,
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: const Text(
                                  'Đã học',
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.primary600,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
                childCount: path.pathCourses.length,
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          decoration: BoxDecoration(
            color: theme.colorScheme.surface,
            border: Border(
              top: BorderSide(color: AppColors.neutral200.withValues(alpha: 0.5)),
            ),
          ),
          child: _buildBottomActions(theme),
        ),
      ),
    );
  }

  Widget _buildBottomActions(ThemeData theme) {
    if (_isProcessing) {
      return const Center(child: CircularProgressIndicator());
    }

    final path = widget.path;

    if (path.isPurchased) {
      return FilledButton.icon(
        onPressed: path.pathCourses.isNotEmpty
            ? () => context.go('/my-courses')
            : null,
        icon: const Icon(Icons.play_arrow_rounded),
        label: const Text('Tiếp tục học'),
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary400,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      );
    }

    if (path.price > 0) {
      return FilledButton(
        onPressed: _handlePurchase,
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.accent400,
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
        child: Text(
          'Mua lộ trình · ${path.price}đ',
          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      );
    }

    return Row(
      children: [
        Expanded(
          child: FilledButton(
            onPressed: _handlePurchase,
            style: FilledButton.styleFrom(
              backgroundColor: AppColors.primary400,
              minimumSize: const Size.fromHeight(52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: const Text(
              'Ghi danh miễn phí',
              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            ),
          ),
        ),
        if (!path.isFollowing) ...[
          AppSpacing.w12,
          OutlinedButton(
            onPressed: _handleFollow,
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(52, 52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              side: const BorderSide(color: AppColors.primary400),
            ),
            child: const Icon(Icons.bookmark_add_outlined, color: AppColors.primary600),
          ),
        ],
      ],
    );
  }

  Widget _headerPlaceholder(bool isDark) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: isDark
              ? [AppColors.primary900, AppColors.primary800]
              : [AppColors.primary600, AppColors.primary400],
        ),
      ),
      child: const Icon(Icons.route_rounded, size: 64, color: Colors.white30),
    );
  }
}

class _StatBadge extends StatelessWidget {
  const _StatBadge({required this.icon, required this.label, this.iconColor});
  final IconData icon;
  final String label;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.6),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.neutral200.withValues(alpha: 0.5)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: iconColor ?? theme.colorScheme.onSurfaceVariant),
          AppSpacing.w4,
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: theme.colorScheme.onSurface,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
