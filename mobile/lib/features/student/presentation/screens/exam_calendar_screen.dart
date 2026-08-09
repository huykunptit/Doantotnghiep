import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/models/student_models.dart';
import '../../providers/student_providers.dart';
import '../../../../core/error/friendly_error.dart';

class ExamCalendarScreen extends ConsumerStatefulWidget {
  const ExamCalendarScreen({super.key});

  @override
  ConsumerState<ExamCalendarScreen> createState() =>
      _ExamCalendarScreenState();
}

class _ExamCalendarScreenState extends ConsumerState<ExamCalendarScreen> {
  DateTime _focusedMonth = DateTime(
      DateTime.now().year, DateTime.now().month, 1);
  DateTime? _selectedDay;

  void _prevMonth() => setState(() {
        _focusedMonth =
            DateTime(_focusedMonth.year, _focusedMonth.month - 1, 1);
        _selectedDay = null;
      });

  void _nextMonth() => setState(() {
        _focusedMonth =
            DateTime(_focusedMonth.year, _focusedMonth.month + 1, 1);
        _selectedDay = null;
      });

  bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  List<CalendarExamModel> _examsForDay(
      List<CalendarExamModel> exams, DateTime day) {
    return exams.where((e) {
      final dt = e.startDateTime;
      if (dt == null) return false;
      return _isSameDay(dt, day);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final examsAsync = ref.watch(examScheduleProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lịch thi',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: examsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 8),
              Text(friendlyErrorMessage(e), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(
                  onPressed: () => ref.invalidate(examScheduleProvider),
                  child: const Text('Thử lại')),
            ],
          ),
        ),
        data: (exams) {
          final selectedExams = _selectedDay != null
              ? _examsForDay(exams, _selectedDay!)
              : exams
                  .where((e) => e.startDateTime != null)
                  .toList()
                ..sort((a, b) =>
                    a.startDateTime!.compareTo(b.startDateTime!));

          return Column(
            children: [
              // Calendar
              Container(
                margin: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  borderRadius: BorderRadius.circular(16),
                  border:
                      Border.all(color: theme.colorScheme.outlineVariant),
                ),
                child: Column(
                  children: [
                    // Month navigation
                    Padding(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 12),
                      child: Row(
                        children: [
                          IconButton(
                            onPressed: _prevMonth,
                            icon: const Icon(Icons.chevron_left),
                            visualDensity: VisualDensity.compact,
                          ),
                          Expanded(
                            child: Text(
                              DateFormat('MMMM yyyy', 'vi')
                                  .format(_focusedMonth),
                              textAlign: TextAlign.center,
                              style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700),
                            ),
                          ),
                          IconButton(
                            onPressed: _nextMonth,
                            icon: const Icon(Icons.chevron_right),
                            visualDensity: VisualDensity.compact,
                          ),
                        ],
                      ),
                    ),
                    // Day-of-week headers
                    Padding(
                      padding:
                          const EdgeInsets.symmetric(horizontal: 8),
                      child: Row(
                        children: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']
                            .map((d) => Expanded(
                                  child: Center(
                                    child: Text(d,
                                        style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w600,
                                            color: theme.colorScheme
                                                .onSurfaceVariant)),
                                  ),
                                ))
                            .toList(),
                      ),
                    ),
                    const SizedBox(height: 4),
                    // Grid
                    _CalendarGrid(
                      focusedMonth: _focusedMonth,
                      selectedDay: _selectedDay,
                      exams: exams,
                      onDayTap: (day) => setState(() {
                        _selectedDay =
                            _isSameDay(day, _selectedDay ?? DateTime(0))
                                ? null
                                : day;
                      }),
                    ),
                    const SizedBox(height: 8),
                  ],
                ),
              ),
              // List
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    Text(
                      _selectedDay != null
                          ? DateFormat('dd/MM/yyyy').format(_selectedDay!)
                          : 'Tất cả kỳ thi',
                      style: theme.textTheme.titleSmall
                          ?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppColors.primary400.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text('${selectedExams.length}',
                          style: const TextStyle(
                              color: AppColors.primary400,
                              fontWeight: FontWeight.w700,
                              fontSize: 12)),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: selectedExams.isEmpty
                    ? const Center(
                        child: Text('Không có kỳ thi',
                            style: TextStyle(color: Colors.grey)),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: selectedExams.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: 8),
                        itemBuilder: (context, i) =>
                            _CalendarExamCard(exam: selectedExams[i]),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _CalendarGrid extends StatelessWidget {
  const _CalendarGrid({
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
        focusedMonth.year, focusedMonth.month);
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
                  focusedMonth.year, focusedMonth.month, dayNum);
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
                          ? AppColors.primary400
                          : isToday
                              ? AppColors.primary400.withOpacity(0.1)
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
                                    ? AppColors.primary400
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
                                    : AppColors.error,
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

class _CalendarExamCard extends StatelessWidget {
  const _CalendarExamCard({required this.exam});
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
    final color = isLive ? AppColors.success : AppColors.primary400;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(
          color: isLive
              ? AppColors.success.withOpacity(0.4)
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
                      const Text('● Đang mở',
                          style: TextStyle(
                              color: AppColors.success,
                              fontSize: 11,
                              fontWeight: FontWeight.w700)),
                    Text(exam.title,
                        style: theme.textTheme.bodyMedium
                            ?.copyWith(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(Icons.schedule, size: 12, color: Colors.grey),
                        const SizedBox(width: 4),
                        Text(
                          '${_fmtTime(exam.startTime)} — ${_fmtTime(exam.endTime)}',
                          style: const TextStyle(
                              fontSize: 11, color: Colors.grey),
                        ),
                      ],
                    ),
                    if (exam.duration != null)
                      Row(
                        children: [
                          const Icon(Icons.timer_outlined,
                              size: 12, color: Colors.grey),
                          const SizedBox(width: 4),
                          Text('${exam.duration} phút',
                              style: const TextStyle(
                                  fontSize: 11, color: Colors.grey)),
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
                    backgroundColor: AppColors.success.withOpacity(0.12),
                    foregroundColor: AppColors.success,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 6),
                    minimumSize: Size.zero,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  child: const Text('Vào thi',
                      style: TextStyle(
                          fontWeight: FontWeight.w700, fontSize: 12)),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
