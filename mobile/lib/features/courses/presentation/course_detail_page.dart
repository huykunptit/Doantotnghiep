import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/course_detail_provider.dart';
import '../data/models/course_model.dart';
import '../data/repositories/course_repository.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class CourseDetailPage extends ConsumerWidget {
  const CourseDetailPage({super.key, required this.courseId});
  final int courseId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final courseAsync = ref.watch(courseDetailProvider(courseId));

    return courseAsync.when(
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
                  onPressed: () => ref.invalidate(courseDetailProvider(courseId)),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
      ),
      data: (course) => _CourseDetailView(course: course),
    );
  }
}

class _CourseDetailView extends ConsumerStatefulWidget {
  const _CourseDetailView({required this.course});
  final CourseDetailModel course;

  @override
  ConsumerState<_CourseDetailView> createState() => _CourseDetailViewState();
}

class _CourseDetailViewState extends ConsumerState<_CourseDetailView> {
  bool _isProcessing = false;

  String _formatDuration(int? seconds) {
    if (seconds == null) return '';
    final m = seconds ~/ 60;
    if (m < 60) return '$m phút';
    final h = m ~/ 60;
    final rem = m % 60;
    return rem > 0 ? '${h}g ${rem}p' : '${h}g';
  }

  Future<void> _handleEnrollment() async {
    setState(() => _isProcessing = true);
    try {
      final res = await ref.read(courseRepositoryProvider).createOrder(widget.course.id);
      if (!mounted) return;
      if (res['enrolled'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('Ghi danh khóa học thành công!'),
            backgroundColor: AppColors.success,
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        );
        ref.invalidate(courseDetailProvider(widget.course.id));
      } else if (res['payment_url'] != null) {
        final success = await context.push<bool>('/checkout-webview', extra: res['payment_url']);
        if (success == true && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: const Text('Thanh toán thành công!'),
              backgroundColor: AppColors.success,
              behavior: SnackBarBehavior.floating,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
          );
          ref.invalidate(courseDetailProvider(widget.course.id));
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

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          // Hero image app bar
          SliverAppBar(
            expandedHeight: 220,
            pinned: true,
            flexibleSpace: FlexibleSpaceBar(
              titlePadding: const EdgeInsets.fromLTRB(56, 0, 16, 14),
              title: Text(
                widget.course.title,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, shadows: [
                  Shadow(color: Colors.black54, blurRadius: 8),
                ]),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              background: widget.course.thumbnail != null
                  ? Stack(
                      fit: StackFit.expand,
                      children: [
                        CachedNetworkImage(
                          imageUrl: widget.course.thumbnail!,
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => _headerPlaceholder(isDark),
                        ),
                        Container(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)],
                              stops: const [0.4, 1.0],
                            ),
                          ),
                        ),
                      ],
                    )
                  : _headerPlaceholder(isDark),
            ),
          ),

          // Content
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Stats row
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _StatBadge(
                        icon: Icons.people_outline_rounded,
                        label: '${widget.course.enrollmentsCount} học viên',
                      ),
                      _StatBadge(
                        icon: Icons.star_rounded,
                        label: widget.course.avgRating.toStringAsFixed(1),
                        iconColor: Colors.amber.shade600,
                      ),
                      _StatBadge(
                        icon: Icons.play_circle_outline_rounded,
                        label: '${widget.course.lessonsCount} bài học',
                      ),
                      if (widget.course.creditValue != null)
                        _StatBadge(
                          icon: Icons.school_outlined,
                          label: '${widget.course.creditValue} tín chỉ',
                          iconColor: AppColors.primary400,
                        ),
                    ],
                  ),

                  // Instructor
                  if (widget.course.instructor != null) ...[
                    AppSpacing.h16,
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkSurface : AppColors.neutral50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
                      ),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 20,
                            backgroundColor: AppColors.primary100,
                            backgroundImage: widget.course.instructor!.avatar != null
                                ? CachedNetworkImageProvider(widget.course.instructor!.avatar!)
                                : null,
                            child: widget.course.instructor!.avatar == null
                                ? Text(widget.course.instructor!.name[0].toUpperCase(),
                                    style: const TextStyle(color: AppColors.primary600, fontWeight: FontWeight.w700))
                                : null,
                          ),
                          AppSpacing.w12,
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Giảng viên',
                                  style: theme.textTheme.bodySmall?.copyWith(
                                      color: theme.colorScheme.onSurfaceVariant, fontSize: 11)),
                              Text(widget.course.instructor!.name,
                                  style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],

                  // Description
                  if (widget.course.description != null) ...[
                    AppSpacing.h20,
                    Text('Mô tả khoá học',
                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.2)),
                    AppSpacing.h8,
                    Text(widget.course.description!,
                        style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant, height: 1.6)),
                  ],

                  // Curriculum header
                  AppSpacing.h24,
                  Row(
                    children: [
                      Text('Nội dung khoá học',
                          style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.2)),
                      AppSpacing.w8,
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.primary50,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text('${widget.course.lessons.length} bài',
                            style: const TextStyle(fontSize: 11, color: AppColors.primary600, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ),
                  AppSpacing.h12,
                ],
              ),
            ),
          ),

          // Lesson list
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 100),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate(
                (context, index) {
                  final lesson = widget.course.lessons[index];
                  final isDark = Theme.of(context).brightness == Brightness.dark;
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkSurface : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
                    ),
                    child: InkWell(
                      borderRadius: BorderRadius.circular(12),
                      onTap: widget.course.isEnrolled
                          ? () => context.push('/learn/${widget.course.id}/${lesson.id}')
                          : null,
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Row(
                          children: [
                            Container(
                              width: 32, height: 32,
                              decoration: BoxDecoration(
                                color: widget.course.isEnrolled ? AppColors.primary50 : AppColors.neutral100,
                                shape: BoxShape.circle,
                              ),
                              child: Center(
                                child: widget.course.isEnrolled
                                    ? Icon(Icons.play_arrow_rounded, size: 18, color: AppColors.primary600)
                                    : Icon(Icons.lock_outline_rounded, size: 14, color: AppColors.neutral400),
                              ),
                            ),
                            AppSpacing.w12,
                            Expanded(
                              child: Text(lesson.title,
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      fontWeight: FontWeight.w600,
                                      color: widget.course.isEnrolled
                                          ? Theme.of(context).colorScheme.onSurface
                                          : Theme.of(context).colorScheme.onSurfaceVariant)),
                            ),
                            if (lesson.duration != null) ...[
                              AppSpacing.w8,
                              Text(_formatDuration(lesson.duration),
                                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      fontSize: 11, color: Theme.of(context).colorScheme.onSurfaceVariant)),
                            ],
                          ],
                        ),
                      ),
                    ),
                  );
                },
                childCount: widget.course.lessons.length,
              ),
            ),
          ),
        ],
      ),

      // Bottom CTA
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            border: Border(top: BorderSide(color: AppColors.neutral200.withValues(alpha: 0.5))),
          ),
          child: _isProcessing
              ? const Center(child: CircularProgressIndicator())
              : widget.course.isEnrolled
                  ? FilledButton.icon(
                      onPressed: widget.course.lessons.isNotEmpty
                          ? () => context.push('/learn/${widget.course.id}/${widget.course.lessons.first.id}')
                          : null,
                      icon: const Icon(Icons.play_arrow_rounded),
                      label: const Text('Vào học ngay'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary400,
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                    )
                  : FilledButton(
                      onPressed: _handleEnrollment,
                      style: FilledButton.styleFrom(
                        backgroundColor: widget.course.price > 0 ? AppColors.accent400 : AppColors.primary400,
                        minimumSize: const Size.fromHeight(52),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      ),
                      child: Text(
                        widget.course.price > 0 ? 'Mua khoá học · ${widget.course.price}đ' : 'Ghi danh miễn phí',
                        style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                      ),
                    ),
        ),
      ),
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
      child: const Icon(Icons.school_rounded, size: 64, color: Colors.white30),
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
          Text(label, style: TextStyle(fontSize: 12, color: theme.colorScheme.onSurface, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
