import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/notification_providers.dart';
import '../../../../core/theme/app_spacing.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  static const routeName = '/notifications';

  String _formatTime(String rawDate) {
    try {
      final parsed = DateTime.parse(rawDate).toLocal();
      final now = DateTime.now();
      final diff = now.difference(parsed);
      
      if (diff.inMinutes < 60) {
        return '${diff.inMinutes} phút trước';
      } else if (diff.inHours < 24) {
        return '${diff.inHours} giờ trước';
      } else {
        return '${parsed.day}/${parsed.month}/${parsed.year}';
      }
    } catch (_) {
      return rawDate;
    }
  }

  IconData _getIconForType(String? type) {
    switch (type) {
      case 'exam_violation':
      case 'warning':
        return Icons.warning_amber_rounded;
      case 'course_enrollment':
      case 'order_success':
        return Icons.check_circle_outline_rounded;
      case 'new_lesson':
      case 'curriculum_update':
        return Icons.menu_book_rounded;
      case 'grade_published':
        return Icons.workspace_premium_rounded;
      default:
        return Icons.notifications_none_outlined;
    }
  }

  Color _getColorForType(BuildContext context, String? type) {
    final theme = Theme.of(context);
    switch (type) {
      case 'exam_violation':
      case 'warning':
        return Colors.red.shade600;
      case 'course_enrollment':
      case 'order_success':
        return Colors.green.shade600;
      case 'grade_published':
        return Colors.amber.shade700;
      default:
        return theme.colorScheme.primary;
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notificationsAsync = ref.watch(studentNotificationsProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Thông báo'),
        actions: [
          TextButton(
            onPressed: () {
              ref.read(studentNotificationsProvider.notifier).markAllAsRead().then((_) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Đã đánh dấu đọc tất cả thông báo!'),
                    backgroundColor: Colors.green,
                  ),
                );
              }).catchError((e) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Lỗi: $e'), backgroundColor: Colors.red),
                );
              });
            },
            child: const Text('Đọc tất cả'),
          ),
          AppSpacing.w8,
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(studentNotificationsProvider);
          ref.invalidate(unreadNotificationsCountProvider);
        },
        child: notificationsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: Colors.red),
                  AppSpacing.h12,
                  Text('Lỗi: $e', textAlign: TextAlign.center),
                  AppSpacing.h16,
                  FilledButton.icon(
                    onPressed: () {
                      ref.invalidate(studentNotificationsProvider);
                      ref.invalidate(unreadNotificationsCountProvider);
                    },
                    icon: const Icon(Icons.refresh),
                    label: const Text('Thử lại'),
                  ),
                ],
              ),
            ),
          ),
          data: (notifications) {
            if (notifications.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  SizedBox(
                    height: MediaQuery.of(context).size.height * 0.7,
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.notifications_off_outlined,
                            size: 64,
                            color: theme.colorScheme.outline,
                          ),
                          AppSpacing.h16,
                          const Text('Bạn chưa có thông báo nào.'),
                        ],
                      ),
                    ),
                  ),
                ],
              );
            }

            return ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: notifications.length,
              separatorBuilder: (_, _) => Divider(
                height: 1,
                color: theme.colorScheme.outlineVariant.withValues(alpha: 0.4),
              ),
              itemBuilder: (context, index) {
                final item = notifications[index];
                final isUnread = !item.isRead;
                final typeColor = _getColorForType(context, item.type);

                return InkWell(
                  onTap: () {
                    if (isUnread) {
                      ref.read(studentNotificationsProvider.notifier).markAsRead(item.id);
                    }
                    // Handle deep link if notification has link
                    if (item.link != null && item.link!.isNotEmpty) {
                      // Navigate inside app or launch externally if needed
                    }
                  },
                  child: Container(
                    color: isUnread
                        ? theme.colorScheme.primaryContainer.withValues(alpha: 0.2)
                        : Colors.transparent,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Left styled notification type icon
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: typeColor.withValues(alpha: 0.1),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            _getIconForType(item.type),
                            size: 20,
                            color: typeColor,
                          ),
                        ),
                        AppSpacing.w12,

                        // Message details
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Text(
                                      item.title,
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        fontWeight: isUnread ? FontWeight.bold : FontWeight.w500,
                                        color: isUnread ? theme.colorScheme.onSurface : theme.colorScheme.onSurfaceVariant,
                                      ),
                                    ),
                                  ),
                                  if (isUnread) ...[
                                    AppSpacing.w8,
                                    Container(
                                      width: 8,
                                      height: 8,
                                      margin: const EdgeInsets.only(top: 4),
                                      decoration: BoxDecoration(
                                        color: theme.colorScheme.primary,
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                              AppSpacing.h4,
                              Text(
                                item.message,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant.withValues(alpha: 0.8),
                                  height: 1.4,
                                ),
                              ),
                              AppSpacing.h8,
                              Text(
                                _formatTime(item.createdAt),
                                style: TextStyle(
                                  fontSize: 10,
                                  color: theme.colorScheme.outlineVariant,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
