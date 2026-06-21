import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../auth/providers/auth_provider.dart';
import '../../../dashboard/providers/dashboard_provider.dart';
import '../../../courses/providers/course_catalog_provider.dart';
import '../../../courses/data/models/course_model.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../notifications/providers/notification_providers.dart';

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
      appBar: AppBar(
        title: Row(
          children: [
            CircleAvatar(
              radius: 18,
              backgroundImage: user?.avatar != null
                  ? CachedNetworkImageProvider(user!.avatar!)
                  : null,
              child: user?.avatar == null
                  ? Text(user?.name.isNotEmpty == true ? user!.name[0].toUpperCase() : '?')
                  : null,
            ),
            AppSpacing.w12,
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Chào, ${user?.name ?? "Học viên"} 🌿',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  Text(
                    'Hôm nay học gì nào?',
                    style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          unreadCountAsync.when(
            data: (count) => count > 0
                ? IconButton(
                    icon: Badge(
                      label: Text('$count'),
                      child: const Icon(Icons.notifications_none_outlined),
                    ),
                    onPressed: () => context.push('/notifications'),
                  )
                : IconButton(
                    icon: const Icon(Icons.notifications_none_outlined),
                    onPressed: () => context.push('/notifications'),
                  ),
            loading: () => IconButton(
              icon: const Icon(Icons.notifications_none_outlined),
              onPressed: () => context.push('/notifications'),
            ),
            error: (_, _) => IconButton(
              icon: const Icon(Icons.notifications_none_outlined),
              onPressed: () => context.push('/notifications'),
            ),
          ),
          AppSpacing.w8,
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(studentDashboardProvider);
          ref.invalidate(courseCatalogProvider());
        },
        child: ListView(
          padding: const EdgeInsets.symmetric(vertical: 16),
          children: [
            // Dashboard Totals Section
            dashboardAsync.when(
              loading: () => const Center(child: Padding(
                padding: EdgeInsets.all(16.0),
                child: CircularProgressIndicator(),
              )),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Text('Lỗi tải thông tin học tập: $e'),
                ),
              ),
              data: (data) => Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Card(
                  color: theme.colorScheme.primaryContainer,
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Kỳ học hiện tại: ${data.currentTerm?.name ?? "N/A"}',
                          style: theme.textTheme.titleSmall?.copyWith(
                            color: theme.colorScheme.onPrimaryContainer,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        AppSpacing.h12,
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            _buildStatItem(
                              context,
                              'Đã đăng ký',
                              '${data.totals.enrollments}',
                              Icons.menu_book_outlined,
                            ),
                            _buildStatItem(
                              context,
                              'Đang học',
                              '${data.totals.inProgress}',
                              Icons.pending_actions_outlined,
                            ),
                            _buildStatItem(
                              context,
                              'Hoàn thành',
                              '${data.totals.completed}',
                              Icons.check_circle_outline,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
            AppSpacing.h24,

            // Continue Learning Section
            dashboardAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, _) => const SizedBox.shrink(),
              data: (data) {
                if (data.currentEnrollments.isEmpty) return const SizedBox.shrink();
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text(
                        'Tiếp tục học tập ⏱',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                      ),
                    ),
                    AppSpacing.h12,
                    SizedBox(
                      height: 140,
                      child: ListView.builder(
                        scrollDirection: Axis.horizontal,
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: data.currentEnrollments.length,
                        itemBuilder: (context, index) {
                          final enroll = data.currentEnrollments[index];
                          return Container(
                            width: 260,
                            margin: const EdgeInsets.only(right: 12),
                            child: Card(
                              clipBehavior: Clip.antiAlias,
                              child: InkWell(
                                onTap: () => context.push('/courses/${enroll.courseId}'),
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        enroll.course.title,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                                      ),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Chip(
                                            label: Text(enroll.course.courseMode == 'online' ? 'Online' : 'Offline'),
                                            padding: EdgeInsets.zero,
                                            visualDensity: VisualDensity.compact,
                                          ),
                                          if (enroll.course.creditValue != null)
                                            Text(
                                              '${enroll.course.creditValue} tín chỉ',
                                              style: theme.textTheme.bodySmall,
                                            ),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    AppSpacing.h24,
                  ],
                );
              },
            ),

            // Featured Courses Section
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Khóa học mới nhất 🎓',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  TextButton(
                    onPressed: () => context.go('/catalog'),
                    child: const Text('Xem tất cả'),
                  ),
                ],
              ),
            ),
            AppSpacing.h8,
            catalogAsync.when(
              loading: () => const Center(child: Padding(
                padding: EdgeInsets.all(24.0),
                child: CircularProgressIndicator(),
              )),
              error: (e, _) => Center(child: Text('Lỗi tải danh sách: $e')),
              data: (list) {
                if (list.isEmpty) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(24.0),
                      child: Text('Không có khóa học nào mới.'),
                    ),
                  );
                }
                return SizedBox(
                  height: 240,
                  child: ListView.builder(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: list.length > 5 ? 5 : list.length,
                    itemBuilder: (context, index) {
                      final item = list[index];
                      return _HorizontalCourseCard(course: item);
                    },
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatItem(BuildContext context, String title, String val, IconData icon) {
    final theme = Theme.of(context);
    return Column(
      children: [
        Icon(icon, color: theme.colorScheme.onPrimaryContainer, size: 24),
        AppSpacing.h4,
        Text(
          val,
          style: theme.textTheme.titleLarge?.copyWith(
            color: theme.colorScheme.onPrimaryContainer,
            fontWeight: FontWeight.bold,
          ),
        ),
        Text(
          title,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onPrimaryContainer.withValues(alpha: 0.8),
          ),
        ),
      ],
    );
  }
}

class _HorizontalCourseCard extends StatelessWidget {
  const _HorizontalCourseCard({required this.course});

  final CourseListItemModel course;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: 200,
      margin: const EdgeInsets.only(right: 16),
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => context.push('/courses/${course.id}'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Course Thumbnail
              Expanded(
                child: course.thumbnail != null
                    ? CachedNetworkImage(
                        imageUrl: course.thumbnail!,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => _placeholder(theme),
                      )
                    : _placeholder(theme),
              ),
              Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      course.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    AppSpacing.h4,
                    Row(
                      children: [
                        Icon(Icons.star, color: Colors.amber.shade700, size: 14),
                        AppSpacing.w4,
                        Text(
                          course.avgRating.toStringAsFixed(1),
                          style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        AppSpacing.w8,
                        Text(
                          '(${course.enrollmentsCount})',
                          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                    AppSpacing.h8,
                    Text(
                      course.price > 0 ? '${course.price}đ' : 'Miễn phí',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placeholder(ThemeData theme) {
    return Container(
      color: theme.colorScheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: Icon(Icons.school_outlined, size: 40, color: theme.colorScheme.outline),
    );
  }
}
