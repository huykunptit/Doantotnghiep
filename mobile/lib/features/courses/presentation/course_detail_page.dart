import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/course_detail_provider.dart';
import '../data/models/course_model.dart';
import '../data/repositories/course_repository.dart';

class CourseDetailPage extends ConsumerWidget {
  const CourseDetailPage({super.key, required this.courseId});

  final int courseId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final courseAsync = ref.watch(courseDetailProvider(courseId));

    return Scaffold(
      body: courseAsync.when(
        loading: () => const Scaffold(
          body: Center(child: CircularProgressIndicator()),
        ),
        error: (e, _) => Scaffold(
          appBar: AppBar(),
          body: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                const SizedBox(height: 12),
                Text(e.toString(), textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton.icon(
                  onPressed: () => ref.invalidate(courseDetailProvider(courseId)),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
        ),
        data: (course) => _CourseDetailView(course: course),
      ),
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
          const SnackBar(content: Text('Ghi danh khóa học thành công!'), backgroundColor: Colors.green),
        );
        ref.invalidate(courseDetailProvider(widget.course.id));
      } else if (res['payment_url'] != null) {
        final success = await context.push<bool>(
          '/checkout-webview',
          extra: res['payment_url'],
        );
        if (success == true && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Thanh toán thành công!'), backgroundColor: Colors.green),
          );
          ref.invalidate(courseDetailProvider(widget.course.id));
        }
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _isProcessing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            flexibleSpace: FlexibleSpaceBar(
              title: Text(
                widget.course.title,
                style: const TextStyle(fontSize: 14),
                maxLines: 2,
              ),
              background: widget.course.thumbnail != null
                  ? CachedNetworkImage(
                      imageUrl: widget.course.thumbnail!,
                      fit: BoxFit.cover,
                      errorWidget: (context, url, error) => Container(
                        color: theme.colorScheme.primaryContainer,
                      ),
                    )
                  : Container(color: theme.colorScheme.primaryContainer),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      _StatChip(
                        icon: Icons.people_outline,
                        label: '${widget.course.enrollmentsCount} học viên',
                      ),
                      const SizedBox(width: 8),
                      _StatChip(
                        icon: Icons.star_outline,
                        label: widget.course.avgRating.toStringAsFixed(1),
                      ),
                      const SizedBox(width: 8),
                      _StatChip(
                        icon: Icons.video_library_outlined,
                        label: '${widget.course.lessonsCount} bài học',
                      ),
                    ],
                  ),
                  if (widget.course.instructor != null) ...[
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 18,
                          backgroundImage: widget.course.instructor!.avatar != null
                              ? CachedNetworkImageProvider(widget.course.instructor!.avatar!)
                              : null,
                          child: widget.course.instructor!.avatar == null
                              ? Text(widget.course.instructor!.name[0].toUpperCase())
                              : null,
                        ),
                        const SizedBox(width: 10),
                        Text(
                          widget.course.instructor!.name,
                          style: theme.textTheme.bodyMedium
                              ?.copyWith(fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ],
                  if (widget.course.description != null) ...[
                    const SizedBox(height: 16),
                    Text(
                      'Mô tả',
                      style: theme.textTheme.titleMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    Text(widget.course.description!, style: theme.textTheme.bodyMedium),
                  ],
                  const SizedBox(height: 24),
                  Text(
                    'Nội dung khoá học',
                    style: theme.textTheme.titleMedium
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                ],
              ),
            ),
          ),
          SliverList(
            delegate: SliverChildBuilderDelegate(
              (context, index) {
                final lesson = widget.course.lessons[index];
                return ListTile(
                  leading: CircleAvatar(
                    radius: 16,
                    backgroundColor: theme.colorScheme.primaryContainer,
                    child: Text(
                      '${lesson.order}',
                      style: TextStyle(
                        color: theme.colorScheme.onPrimaryContainer,
                        fontSize: 12,
                      ),
                    ),
                  ),
                  title: Text(lesson.title, style: theme.textTheme.bodyMedium),
                  trailing: lesson.duration != null
                      ? Text(
                          _formatDuration(lesson.duration),
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        )
                      : null,
                  dense: true,
                );
              },
              childCount: widget.course.lessons.length,
            ),
          ),
          const SliverPadding(padding: EdgeInsets.only(bottom: 32)),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: _isProcessing
              ? const Center(
                  child: CircularProgressIndicator(),
                )
              : widget.course.isEnrolled
                  ? FilledButton(
                      onPressed: () {
                        if (widget.course.lessons.isNotEmpty) {
                          context.push('/learn/${widget.course.id}/${widget.course.lessons.first.id}');
                        } else {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Khóa học này chưa có bài học.')),
                          );
                        }
                      },
                      child: const Text('Vào học ngay'),
                    )
                  : FilledButton(
                      onPressed: _handleEnrollment,
                      child: Text(
                        widget.course.price > 0
                            ? 'Mua khóa học (${widget.course.price}đ)'
                            : 'Đăng ký học miễn phí',
                      ),
                    ),
        ),
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14),
          const SizedBox(width: 4),
          Text(label, style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }
}
