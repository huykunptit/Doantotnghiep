import 'package:cached_network_image/cached_network_image.dart';
import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/utils/format_vnd.dart';
import 'package:eript_lms/core/utils/html_text.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/detail_widgets.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../data/models/course_model.dart';
import '../../data/repositories/course_repository.dart';
import '../../providers/course_detail_provider.dart';
import '../../providers/my_courses_provider.dart';

/// Course detail: hero, stats, instructor, description, lessons, buy/enroll bar.
class CourseDetailView extends ConsumerStatefulWidget {
  const CourseDetailView({super.key, required this.course});

  final CourseDetailModel course;

  @override
  ConsumerState<CourseDetailView> createState() => _CourseDetailViewState();
}

class _CourseDetailViewState extends ConsumerState<CourseDetailView> {
  bool _isProcessing = false;

  CourseDetailModel get _course => widget.course;

  int get _lessonCount {
    final listed = _course.lessons.length;
    final counted = _course.lessonsCount;
    return listed > counted ? listed : counted;
  }

  void _refresh() {
    ref.invalidate(courseDetailProvider(_course.id));
    ref.invalidate(myEnrollmentsProvider);
  }

  Future<void> _handleEnrollment() async {
    setState(() => _isProcessing = true);
    try {
      final res = await ref
          .read(courseRepositoryProvider)
          .createOrder(_course.id);
      if (!mounted) return;
      if (res['enrolled'] == true) {
        showAppSnack(
          context,
          'Ghi danh khóa học thành công!',
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

  @override
  Widget build(BuildContext context) {
    final course = _course;
    final description = htmlToPlainText(course.description);

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          DetailHeroAppBar(
            title: course.title,
            imageUrl: course.thumbnail,
            fallbackIcon: Icons.school_rounded,
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
                        icon: Icons.people_outline_rounded,
                        label: '${course.enrollmentsCount} học viên',
                      ),
                      InfoChip(
                        icon: Icons.star_rounded,
                        label: course.avgRating.toStringAsFixed(1),
                        iconColor: context.sem.warning,
                      ),
                      InfoChip(
                        icon: Icons.play_circle_outline_rounded,
                        label: '$_lessonCount bài học',
                      ),
                      if (course.creditValue != null)
                        InfoChip(
                          icon: Icons.school_outlined,
                          label: '${course.creditValue} tín chỉ',
                          iconColor: context.cs.primary,
                        ),
                    ],
                  ),
                  if (course.instructor != null) ...[
                    AppSpacing.h16,
                    _InstructorCard(instructor: course.instructor!),
                  ],
                  if (description.isNotEmpty) ...[
                    AppSpacing.h20,
                    const SectionTitle(title: 'Mô tả khoá học'),
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
                    title: 'Nội dung khoá học',
                    badge: '$_lessonCount bài',
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
                (context, i) =>
                    _LessonTile(course: course, lesson: course.lessons[i]),
                childCount: course.lessons.length,
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: BottomActionBar(child: _actionButton(context)),
    );
  }

  Widget _actionButton(BuildContext context) {
    final course = _course;
    if (_isProcessing) {
      return const Center(child: CircularProgressIndicator());
    }
    const size = Size.fromHeight(52);
    if (course.isEnrolled) {
      return FilledButton.icon(
        onPressed: course.lessons.isNotEmpty
            ? () =>
                  context.push('/learn/${course.id}/${course.lessons.first.id}')
            : null,
        icon: const Icon(Icons.play_arrow_rounded),
        label: const Text('Vào học ngay'),
        style: FilledButton.styleFrom(minimumSize: size),
      );
    }
    final paid = course.price > 0;
    return FilledButton(
      onPressed: _handleEnrollment,
      style: FilledButton.styleFrom(
        minimumSize: size,
        backgroundColor: paid ? context.cs.tertiary : null,
        foregroundColor: paid ? context.cs.onTertiary : null,
      ),
      child: Text(
        paid
            ? 'Mua khoá học · ${formatVnd(course.price)}'
            : 'Ghi danh miễn phí',
      ),
    );
  }
}

class _InstructorCard extends StatelessWidget {
  const _InstructorCard({required this.instructor});

  final InstructorModel instructor;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final avatar = instructor.avatar;
    final name = instructor.name;
    return AppCard(
      padding: AppSpacing.p12,
      child: Row(
        children: [
          CircleAvatar(
            radius: 20,
            backgroundColor: cs.primaryContainer,
            backgroundImage: avatar != null
                ? CachedNetworkImageProvider(avatar)
                : null,
            child: avatar == null
                ? Text(
                    name.isNotEmpty ? name[0].toUpperCase() : '?',
                    style: context.tt.titleSmall?.copyWith(
                      color: cs.onPrimaryContainer,
                    ),
                  )
                : null,
          ),
          AppSpacing.w12,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Giảng viên',
                style: context.tt.bodySmall?.copyWith(
                  color: cs.onSurfaceVariant,
                ),
              ),
              Text(name, style: context.tt.titleSmall),
            ],
          ),
        ],
      ),
    );
  }
}

class _LessonTile extends StatelessWidget {
  const _LessonTile({required this.course, required this.lesson});

  final CourseDetailModel course;
  final LessonSummaryModel lesson;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final enrolled = course.isEnrolled;
    final duration = formatLessonDuration(lesson.duration);

    return NumberedListTile(
      muted: !enrolled,
      leading: CircleBadge(
        active: enrolled,
        child: Icon(
          enrolled ? Icons.play_arrow_rounded : Icons.lock_outline_rounded,
          size: enrolled ? 18 : 14,
          color: enrolled ? cs.primary : cs.outline,
        ),
      ),
      title: displayLessonTitle(lesson.title, course.title),
      trailing: duration.isNotEmpty
          ? Text(
              duration,
              style: context.tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
            )
          : null,
      onTap: enrolled
          ? () => context.push('/learn/${course.id}/${lesson.id}')
          : () => showAppSnack(context, 'Mua khoá học để mở khoá bài học này.'),
    );
  }
}
