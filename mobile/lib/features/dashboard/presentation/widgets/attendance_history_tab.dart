import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/models/attendance_model.dart';

/// "Lịch sử" tab: list of past check-ins with pull-to-refresh.
class AttendanceHistoryTab extends StatelessWidget {
  const AttendanceHistoryTab({
    super.key,
    required this.history,
    required this.onRefresh,
  });

  final AsyncValue<List<AttendanceHistoryItemModel>> history;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return history.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => EmptyState(
        icon: Icons.error_outline,
        title: 'Không tải được lịch sử',
        message: friendlyErrorMessage(e),
      ),
      data: (list) {
        if (list.isEmpty) {
          return const EmptyState(
            icon: Icons.assignment_turned_in_outlined,
            title: 'Chưa có lịch sử điểm danh.',
          );
        }
        return RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            itemCount: list.length,
            separatorBuilder: (_, _) => AppSpacing.h12,
            itemBuilder: (_, i) => AttendanceHistoryCard(item: list[i]),
          ),
        );
      },
    );
  }
}

class AttendanceHistoryCard extends StatelessWidget {
  const AttendanceHistoryCard({super.key, required this.item});

  final AttendanceHistoryItemModel item;

  @override
  Widget build(BuildContext context) {
    final session = item.offlineSession;
    final present = item.status == 'present';
    final tone = present ? StatusTone.success : StatusTone.warning;
    final c = toneColors(context, tone);
    final muted = context.tt.bodySmall?.copyWith(
      color: context.cs.onSurfaceVariant,
    );
    final at = item.checkedInAt != null
        ? DateTime.tryParse(item.checkedInAt!)
        : null;

    return AppCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: c.bg, shape: BoxShape.circle),
            child: Icon(
              present
                  ? Icons.check_circle_outline_rounded
                  : Icons.pending_actions_rounded,
              color: c.fg,
            ),
          ),
          AppSpacing.w16,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session?.courseTitle ?? session?.title ?? '—',
                  style: context.tt.titleSmall,
                ),
                AppSpacing.h4,
                Text(session?.lessonTitle ?? '—', style: muted),
                AppSpacing.h8,
                Row(
                  children: [
                    Icon(
                      Icons.location_on_outlined,
                      size: 14,
                      color: context.cs.onSurfaceVariant,
                    ),
                    AppSpacing.w4,
                    Expanded(
                      child: Text(session?.location ?? '—', style: muted),
                    ),
                  ],
                ),
                if (at != null) ...[
                  AppSpacing.h4,
                  Text(
                    'Điểm danh lúc: ${at.hour}:${at.minute.toString().padLeft(2, '0')} ngày ${at.day}/${at.month}/${at.year}',
                    style: muted,
                  ),
                ],
              ],
            ),
          ),
          StatusBadge(label: present ? 'Có mặt' : 'Đi muộn', tone: tone),
        ],
      ),
    );
  }
}
