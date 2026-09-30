import 'package:cached_network_image/cached_network_image.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Greeting + avatar + notification bell with unread badge.
class HomeAppBar extends StatelessWidget {
  const HomeAppBar({
    super.key,
    required this.name,
    required this.avatarUrl,
    required this.unreadCount,
  });

  final String? name;
  final String? avatarUrl;
  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final firstName = (name ?? '').trim().split(' ').last;
    final display = firstName.isEmpty ? 'Học viên' : firstName;

    return SliverAppBar(
      pinned: true,
      title: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: cs.primaryContainer,
            backgroundImage: avatarUrl != null
                ? CachedNetworkImageProvider(avatarUrl!)
                : null,
            child: avatarUrl == null
                ? Text(
                    display[0].toUpperCase(),
                    style: context.tt.titleSmall?.copyWith(
                      color: cs.onPrimaryContainer,
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
                  'Xin chào, $display',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.tt.titleSmall,
                ),
                Text(
                  'Hôm nay học gì nào?',
                  style: context.tt.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      actions: [
        IconButton(
          tooltip: unreadCount > 0
              ? 'Thông báo, $unreadCount chưa đọc'
              : 'Thông báo',
          onPressed: () => context.push('/notifications'),
          icon: Badge(
            isLabelVisible: unreadCount > 0,
            label: Text(unreadCount > 99 ? '99+' : '$unreadCount'),
            child: const Icon(Icons.notifications_none_rounded),
          ),
        ),
        AppSpacing.w4,
      ],
    );
  }
}
