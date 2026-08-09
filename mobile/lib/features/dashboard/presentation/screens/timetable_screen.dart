import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/models/portal_models.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';

class TimetableScreen extends ConsumerWidget {
  const TimetableScreen({super.key});
  static const routeName = '/timetable';

  static const _weekdays = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'CN'];

  String _fmtDateTime(String? raw) {
    if (raw == null || raw.isEmpty) return '—';
    final d = DateTime.tryParse(raw);
    if (d == null) return raw;
    return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')} ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(studentTimetableProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Thời khóa biểu'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(studentTimetableProvider),
          ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Lỗi: ${friendlyErrorMessage(e)}')),
        data: (data) {
          final byDay = <int, List<TimetableScheduleItem>>{};
          for (var d = 1; d <= 7; d++) {
            byDay[d] = [];
          }
          for (final s in data.schedules) {
            byDay[s.weekday]?.add(s);
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              if (data.termName != null)
                Text(
                  'Kỳ hiện tại: ${data.termName}',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              AppSpacing.h12,
              if (data.schedules.isEmpty)
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.neutral200),
                  ),
                  child: const Text('Chưa có lịch học cho lớp hành chính.'),
                )
              else
                ...List.generate(7, (i) {
                  final day = i + 1;
                  final slots = byDay[day] ?? [];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.neutral200),
                      color: theme.brightness == Brightness.dark ? AppColors.darkSurface : Colors.white,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(_weekdays[i], style: const TextStyle(fontWeight: FontWeight.w800)),
                        AppSpacing.h8,
                        if (slots.isEmpty)
                          Text('—', style: TextStyle(color: theme.colorScheme.onSurfaceVariant))
                        else
                          ...slots.map(
                            (s) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${s.startTime}–${s.endTime}',
                                    style: const TextStyle(
                                      color: AppColors.primary400,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  Text(s.courseTitle ?? '—', style: const TextStyle(fontWeight: FontWeight.w600)),
                                  if (s.room != null)
                                    Text('Phòng ${s.room}', style: theme.textTheme.bodySmall),
                                  if (s.lecturerName != null)
                                    Text(s.lecturerName!, style: theme.textTheme.bodySmall),
                                ],
                              ),
                            ),
                          ),
                      ],
                    ),
                  );
                }),
              if (data.exams.isNotEmpty) ...[
                AppSpacing.h12,
                Text('Lịch thi sắp tới', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                AppSpacing.h8,
                ...data.exams.map(
                  (ex) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.edit_document, color: AppColors.primary400),
                    title: Text(ex.title, style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text(_fmtDateTime(ex.startsAt)),
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
