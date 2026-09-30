import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/section_header.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class _Utility {
  const _Utility(this.icon, this.label, this.route);
  final IconData icon;
  final String label;
  final String route;
}

const _utilities = <_Utility>[
  _Utility(Icons.alt_route_rounded, 'Lộ trình học', '/learning-path'),
  _Utility(Icons.analytics_rounded, 'Bảng điểm', '/transcript'),
  _Utility(Icons.calendar_month_rounded, 'Thời khóa biểu', '/timetable'),
  _Utility(Icons.qr_code_scanner_rounded, 'Điểm danh QR', '/attendance'),
  _Utility(Icons.event_note_rounded, 'Lịch thi', '/exam-calendar'),
  _Utility(Icons.task_alt_rounded, 'Nhiệm vụ', '/tasks'),
  _Utility(Icons.payments_rounded, 'Học phí', '/tuition'),
  _Utility(Icons.verified_rounded, 'Chứng chỉ', '/certificates'),
];

/// "Tiện ích sinh viên": 4-column grid of shortcuts to student-portal screens.
class StudentUtilities extends StatelessWidget {
  const StudentUtilities({super.key});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Tiện ích sinh viên'),
        // Rows of 4 (not a fixed-height grid) so labels can wrap to 2-3 lines
        // on small phones / large text without overflowing.
        for (var i = 0; i < _utilities.length; i += 4) ...[
          if (i > 0) AppSpacing.h8,
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              for (var j = i; j < i + 4; j++)
                Expanded(
                  child: j < _utilities.length
                      ? _UtilityTile(_utilities[j])
                      : const SizedBox.shrink(),
                ),
            ],
          ),
        ],
      ],
    );
  }
}

class _UtilityTile extends StatelessWidget {
  const _UtilityTile(this.u);

  final _Utility u;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return InkWell(
      borderRadius: AppRadius.rLg,
      onTap: () => context.push(u.route),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: cs.primaryContainer,
                borderRadius: AppRadius.rXl,
              ),
              child: Icon(u.icon, color: cs.onPrimaryContainer, size: 24),
            ),
            AppSpacing.h4,
            Text(
              u.label,
              maxLines: 2,
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              style: context.tt.bodySmall?.copyWith(color: cs.onSurface),
            ),
          ],
        ),
      ),
    );
  }
}
