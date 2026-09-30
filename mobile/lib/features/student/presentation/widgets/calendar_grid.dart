import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:eript_lms/features/student/data/models/student_models.dart';
import 'package:eript_lms/core/theme/theme_context.dart';

class CalendarGrid extends StatelessWidget {
  const CalendarGrid({
    super.key,
    required this.focusedMonth,
    required this.selectedDay,
    required this.exams,
    required this.onDayTap,
  });

  final DateTime focusedMonth;
  final DateTime? selectedDay;
  final List<CalendarExamModel> exams;
  final ValueChanged<DateTime> onDayTap;

  bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  Set<int> _daysWithExams() {
    final days = <int>{};
    for (final e in exams) {
      final dt = e.startDateTime;
      if (dt != null &&
          dt.year == focusedMonth.year &&
          dt.month == focusedMonth.month) {
        days.add(dt.day);
      }
    }
    return days;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final today = DateTime.now();
    final daysWithExams = _daysWithExams();

    // Build grid — week starts on Monday
    final firstOfMonth = focusedMonth;
    // weekday: 1=Mon … 7=Sun. Offset to 0-based
    final startOffset = (firstOfMonth.weekday - 1) % 7;
    final daysInMonth = DateUtils.getDaysInMonth(
      focusedMonth.year,
      focusedMonth.month,
    );
    final totalCells = startOffset + daysInMonth;
    final rows = (totalCells / 7).ceil();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        children: List.generate(rows, (row) {
          return Row(
            children: List.generate(7, (col) {
              final cellIndex = row * 7 + col;
              final dayNum = cellIndex - startOffset + 1;
              if (dayNum < 1 || dayNum > daysInMonth) {
                return const Expanded(child: SizedBox(height: 36));
              }
              final day = DateTime(
                focusedMonth.year,
                focusedMonth.month,
                dayNum,
              );
              final isToday = _isSameDay(day, today);
              final isSelected =
                  selectedDay != null && _isSameDay(day, selectedDay!);
              final hasExam = daysWithExams.contains(dayNum);

              return Expanded(
                child: GestureDetector(
                  onTap: () => onDayTap(day),
                  child: Container(
                    height: 36,
                    margin: const EdgeInsets.all(1),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? context.cs.primary
                          : isToday
                          ? context.cs.primary.withValues(alpha: 0.1)
                          : null,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        Text(
                          '$dayNum',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: isToday || isSelected
                                ? FontWeight.w700
                                : FontWeight.w400,
                            color: isSelected
                                ? Colors.white
                                : isToday
                                ? context.cs.primary
                                : theme.colorScheme.onSurface,
                          ),
                        ),
                        if (hasExam)
                          Positioned(
                            bottom: 3,
                            child: Container(
                              width: 4,
                              height: 4,
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? Colors.white
                                    : context.cs.error,
                                shape: BoxShape.circle,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              );
            }),
          );
        }),
      ),
    );
  }
}

class CalendarExamCard extends StatelessWidget {
  const CalendarExamCard({super.key, required this.exam});
  final CalendarExamModel exam;

  String _fmtTime(String? iso) {
    if (iso == null) return '--';
    try {
      return DateFormat('HH:mm dd/MM').format(DateTime.parse(iso).toLocal());
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isLive = exam.status == 'active';
    final color = isLive ? context.sem.success : context.cs.primary;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isLive
              ? context.sem.success.withValues(alpha: 0.4)
              : theme.colorScheme.outlineVariant,
        ),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: isLive
            ? () => GoRouter.of(context).push('/exam/${exam.id}')
            : null,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Container(
                width: 4,
                height: 48,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (isLive)
                      Text(
                        '● Đang mở',
                        style: TextStyle(
                          color: context.sem.successFg,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    Text(
                      exam.title,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(
                          Icons.schedule,
                          size: 12,
                          color: context.cs.onSurfaceVariant,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${_fmtTime(exam.startTime)} — ${_fmtTime(exam.endTime)}',
                          style: TextStyle(
                            fontSize: 12,
                            color: context.cs.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                    if (exam.duration != null)
                      Row(
                        children: [
                          Icon(
                            Icons.timer_outlined,
                            size: 12,
                            color: context.cs.onSurfaceVariant,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            '${exam.duration} phút',
                            style: TextStyle(
                              fontSize: 12,
                              color: context.cs.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                  ],
                ),
              ),
              if (isLive)
                FilledButton.tonal(
                  onPressed: () =>
                      GoRouter.of(context).push('/exam/${exam.id}'),
                  style: FilledButton.styleFrom(
                    backgroundColor: context.sem.success.withValues(
                      alpha: 0.12,
                    ),
                    foregroundColor: context.sem.success,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text(
                    'Vào thi',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
