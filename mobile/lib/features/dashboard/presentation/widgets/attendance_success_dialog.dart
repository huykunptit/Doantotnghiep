import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

import '../../data/models/attendance_model.dart';

/// Confirmation dialog shown after a successful check-in.
void showAttendanceSuccessDialog(
  BuildContext context, {
  required String message,
  required AttendanceHistoryItemModel attendance,
}) {
  final session = attendance.offlineSession;
  final present = attendance.status == 'present';

  showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) => AlertDialog(
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: ctx.cs.primaryContainer,
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.check_circle_rounded,
              color: ctx.cs.primary,
              size: 48,
            ),
          ),
          AppSpacing.h20,
          Text(
            message,
            style: ctx.tt.titleMedium?.copyWith(color: ctx.cs.primary),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h16,
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: ctx.cs.surfaceContainerLow,
              borderRadius: AppRadius.rXl,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Row('Khóa học:', session?.courseTitle ?? '—'),
                AppSpacing.h8,
                _Row(
                  'Phiên học:',
                  session?.lessonTitle ?? session?.title ?? '—',
                ),
                AppSpacing.h8,
                _Row('Địa điểm:', session?.location ?? '—'),
                AppSpacing.h8,
                _Row(
                  'Trạng thái:',
                  present ? 'Có mặt' : 'Đi muộn',
                  color: present ? ctx.sem.successFg : ctx.sem.warningFg,
                ),
                if (attendance.distanceMeters != null) ...[
                  AppSpacing.h8,
                  _Row('Khoảng cách:', '${attendance.distanceMeters}m'),
                ],
              ],
            ),
          ),
          AppSpacing.h24,
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => Navigator.of(ctx).pop(),
              child: const Text('Đóng'),
            ),
          ),
        ],
      ),
    ),
  );
}

class _Row extends StatelessWidget {
  const _Row(this.label, this.value, {this.color});

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 90,
          child: Text(
            label,
            style: context.tt.bodySmall?.copyWith(
              color: context.cs.onSurfaceVariant,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: context.tt.bodySmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: color ?? context.cs.onSurface,
            ),
          ),
        ),
      ],
    );
  }
}
