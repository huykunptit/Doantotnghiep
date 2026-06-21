import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/my_courses_provider.dart';
import '../data/models/enrollment_model.dart';

class MyCoursesPage extends ConsumerWidget {
  const MyCoursesPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final enrollmentsAsync = ref.watch(myEnrollmentsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Khoá học của tôi'),
        centerTitle: false,
      ),
      body: enrollmentsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorView(
          message: e.toString(),
          onRetry: () => ref.invalidate(myEnrollmentsProvider),
        ),
        data: (enrollments) {
          if (enrollments.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.school_outlined, size: 64, color: theme.colorScheme.outline),
                  const SizedBox(height: 16),
                  Text('Bạn chưa đăng ký khoá học nào', style: theme.textTheme.bodyLarge),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myEnrollmentsProvider),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: enrollments.length,
              itemBuilder: (context, index) =>
                  _EnrollmentCard(enrollment: enrollments[index]),
            ),
          );
        },
      ),
    );
  }
}

class _EnrollmentCard extends StatelessWidget {
  const _EnrollmentCard({required this.enrollment});

  final EnrollmentModel enrollment;

  @override
  Widget build(BuildContext context) {
    final course = enrollment.course;
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/courses/${course.id}'),
        child: Row(
          children: [
            if (course.thumbnail != null)
              CachedNetworkImage(
                imageUrl: course.thumbnail!,
                width: 100,
                height: 80,
                fit: BoxFit.cover,
                errorWidget: (context, url, error) => _ThumbnailPlaceholder(),
              )
            else
              _ThumbnailPlaceholder(),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      course.title,
                      style: theme.textTheme.titleSmall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(
                          course.courseMode == 'online'
                              ? Icons.laptop_outlined
                              : Icons.location_on_outlined,
                          size: 14,
                          color: theme.colorScheme.primary,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          course.courseMode == 'online' ? 'Online' : 'Offline',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.primary,
                          ),
                        ),
                        if (course.creditValue != null) ...[
                          const SizedBox(width: 12),
                          Text(
                            '${course.creditValue} tín chỉ',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(4),
                            child: LinearProgressIndicator(
                              value: enrollment.progress / 100,
                              backgroundColor: theme.colorScheme.surfaceContainerHighest,
                              valueColor: AlwaysStoppedAnimation<Color>(theme.colorScheme.primary),
                              minHeight: 6,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '${enrollment.progress.toStringAsFixed(0)}%',
                          style: theme.textTheme.bodySmall?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const Icon(Icons.chevron_right),
            const SizedBox(width: 8),
          ],
        ),
      ),
    );
  }
}

class _ThumbnailPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 100,
      height: 80,
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: Icon(Icons.play_circle_outline,
          color: Theme.of(context).colorScheme.outline),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: Colors.red),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Thử lại'),
            ),
          ],
        ),
      ),
    );
  }
}
