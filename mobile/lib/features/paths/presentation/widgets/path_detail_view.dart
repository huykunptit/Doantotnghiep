import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/utils/format_vnd.dart';
import 'package:eript_lms/core/utils/html_text.dart';
import 'package:eript_lms/core/widgets/detail_widgets.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../courses/providers/my_courses_provider.dart';
import '../../data/models/career_path_model.dart';
import '../../data/repositories/path_repository.dart';
import '../../providers/path_detail_provider.dart';

/// Career-path detail: hero, stats, description, ordered courses, buy bar.
class PathDetailView extends ConsumerStatefulWidget {
  const PathDetailView({super.key, required this.path, required this.slug});

  final CareerPathDetail path;
  final String slug;

  @override
  ConsumerState<PathDetailView> createState() => _PathDetailViewState();
}

class _PathDetailViewState extends ConsumerState<PathDetailView> {
  bool _isProcessing = false;

  CareerPathDetail get _path => widget.path;

  int get _coursesCount => _path.pathCoursesCount > 0
      ? _path.pathCoursesCount
      : _path.pathCourses.length;

  Set<int> get _enrolledSet => _path.enrolledCourseIds.toSet();

  void _refresh({bool enrollments = true}) {
    ref.invalidate(pathDetailProvider(widget.slug));
    if (enrollments) ref.invalidate(myEnrollmentsProvider);
  }

  Future<void> _handlePurchase() async {
    setState(() => _isProcessing = true);
    try {
      final res = await ref
          .read(pathRepositoryProvider)
          .createPathOrder(_path.id);
      if (!mounted) return;
      if (res['enrolled'] == true) {
        showAppSnack(
          context,
          _path.price > 0
              ? 'Mua lộ trình thành công!'
              : 'Ghi danh lộ trình miễn phí thành công!',
          tone: StatusTone.success,
        );
        _refresh();
      } else if (res['payment_url'] != null) {
        final success = await context.push<bool>(
          '/checkout-webview',
          extra: res['payment_url'],
        );
        if (!mounted) return;
        if (success == true) {
          showAppSnack(
            context,
            'Thanh toán thành công!',
            tone: StatusTone.success,
          );
          _refresh();
        } else if (success == false) {
          showAppSnack(
            context,
            'Thanh toán chưa hoàn tất hoặc đã bị hủy.',
            tone: StatusTone.danger,
          );
        }
      }
    } catch (e) {
      if (!mounted) return;
      showAppSnack(context, friendlyErrorMessage(e), tone: StatusTone.danger);
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  Future<void> _handleFollow() async {
    setState(() => _isProcessing = true);
    try {
      await ref.read(pathRepositoryProvider).followPath(_path.id);
      if (!mounted) return;
      showAppSnack(context, 'Đã theo dõi lộ trình!', tone: StatusTone.success);
      _refresh(enrollments: false);
    } catch (e) {
      if (!mounted) return;
      showAppSnack(context, friendlyErrorMessage(e), tone: StatusTone.danger);
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final path = _path;
    final description = htmlToPlainText(path.description);

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          DetailHeroAppBar(
            title: path.title,
            imageUrl: path.coverUrl,
            fallbackIcon: Icons.route_rounded,
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: AppSpacing.p20,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      InfoChip(
                        icon: Icons.menu_book_outlined,
                        label: '$_coursesCount khoá học',
                      ),
                      InfoChip(
                        icon: Icons.payments_outlined,
                        label: formatVnd(path.price),
                        iconColor: path.price > 0
                            ? context.cs.primary
                            : context.sem.success,
                      ),
                      if (path.isPurchased)
                        InfoChip(
                          icon: Icons.check_circle_outline,
                          label: 'Đã sở hữu',
                          iconColor: context.sem.success,
                        ),
                    ],
                  ),
                  if (description.isNotEmpty) ...[
                    AppSpacing.h20,
                    const SectionTitle(title: 'Mô tả lộ trình'),
                    AppSpacing.h8,
                    Text(
                      description,
                      style: context.tt.bodyMedium?.copyWith(
                        color: context.cs.onSurfaceVariant,
                        height: 1.6,
                      ),
                    ),
                  ],
                  AppSpacing.h24,
                  SectionTitle(
                    title: 'Lộ trình khoá học',
                    badge: '$_coursesCount khoá',
                  ),
                  AppSpacing.h12,
                ],
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 100),
            sliver: SliverList(
              delegate: SliverChildBuilderDelegate((context, i) {
                final item = path.pathCourses[i];
                final enrolled = _enrolledSet.contains(item.courseId);
                final lessons = item.course?.lessonsCount;
                return NumberedListTile(
                  leading: CircleBadge(
                    child: Text(
                      '${i + 1}',
                      style: context.tt.labelMedium?.copyWith(
                        color: context.cs.onPrimaryContainer,
                      ),
                    ),
                  ),
                  title: item.course?.title ?? 'Khoá #${item.courseId}',
                  subtitle:
                      '${item.isRequired ? 'Bắt buộc' : 'Tuỳ chọn'}'
                      '${lessons != null ? ' · $lessons bài' : ''}',
                  trailing: enrolled
                      ? const StatusBadge(
                          label: 'Đã học',
                          tone: StatusTone.primary,
                        )
                      : null,
                  onTap: () => context.push('/courses/${item.courseId}'),
                );
              }, childCount: path.pathCourses.length),
            ),
          ),
        ],
      ),
      bottomNavigationBar: BottomActionBar(child: _actions(context)),
    );
  }

  Widget _actions(BuildContext context) {
    if (_isProcessing) {
      return const Center(child: CircularProgressIndicator());
    }
    final path = _path;
    const size = Size.fromHeight(52);

    if (path.isPurchased) {
      return FilledButton.icon(
        onPressed: path.pathCourses.isNotEmpty
            ? () => context.go('/my-courses')
            : null,
        icon: const Icon(Icons.play_arrow_rounded),
        label: const Text('Tiếp tục học'),
        style: FilledButton.styleFrom(minimumSize: size),
      );
    }

    if (path.price > 0) {
      return FilledButton(
        onPressed: _handlePurchase,
        style: FilledButton.styleFrom(
          minimumSize: size,
          backgroundColor: context.cs.tertiary,
          foregroundColor: context.cs.onTertiary,
        ),
        child: Text('Mua lộ trình · ${formatVnd(path.price)}'),
      );
    }

    return Row(
      children: [
        Expanded(
          child: FilledButton(
            onPressed: _handlePurchase,
            style: FilledButton.styleFrom(minimumSize: size),
            child: const Text('Ghi danh miễn phí'),
          ),
        ),
        if (!path.isFollowing) ...[
          AppSpacing.w12,
          OutlinedButton(
            onPressed: _handleFollow,
            style: OutlinedButton.styleFrom(minimumSize: const Size(52, 52)),
            child: const Icon(Icons.bookmark_add_outlined),
          ),
        ],
      ],
    );
  }
}
