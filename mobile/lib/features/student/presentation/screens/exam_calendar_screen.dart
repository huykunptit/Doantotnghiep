import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../data/models/student_models.dart';
import '../../providers/student_providers.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/student/presentation/widgets/calendar_grid.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class ExamCalendarScreen extends ConsumerStatefulWidget {
  const ExamCalendarScreen({super.key});

  @override
  ConsumerState<ExamCalendarScreen> createState() => _ExamCalendarScreenState();
}

class _ExamCalendarScreenState extends ConsumerState<ExamCalendarScreen> {
  DateTime _focusedMonth = DateTime(
    DateTime.now().year,
    DateTime.now().month,
    1,
  );
  DateTime? _selectedDay;

  void _prevMonth() => setState(() {
    _focusedMonth = DateTime(_focusedMonth.year, _focusedMonth.month - 1, 1);
    _selectedDay = null;
  });

  void _nextMonth() => setState(() {
    _focusedMonth = DateTime(_focusedMonth.year, _focusedMonth.month + 1, 1);
    _selectedDay = null;
  });

  bool _isSameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  List<CalendarExamModel> _examsForDay(
    List<CalendarExamModel> exams,
    DateTime day,
  ) {
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
        title: const Text(
          'Lịch thi',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: examsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(examScheduleProvider),
        ),
        data: (exams) {
          final selectedExams =
              _selectedDay != null
                    ? _examsForDay(exams, _selectedDay!)
                    : exams.where((e) => e.startDateTime != null).toList()
                ..sort((a, b) => a.startDateTime!.compareTo(b.startDateTime!));

          return Column(
            children: [
              // Calendar
              Container(
                margin: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: theme.colorScheme.surface,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: theme.colorScheme.outlineVariant),
                ),
                child: Column(
                  children: [
                    // Month navigation
                    Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 12,
                      ),
                      child: Row(
                        children: [
                          IconButton(
                            tooltip: 'Trước',
                            onPressed: _prevMonth,
                            icon: const Icon(Icons.chevron_left),
                            visualDensity: VisualDensity.compact,
                          ),
                          Expanded(
                            child: Text(
                              DateFormat(
                                'MMMM yyyy',
                                'vi',
                              ).format(_focusedMonth),
                              textAlign: TextAlign.center,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                          IconButton(
                            tooltip: 'Sau',
                            onPressed: _nextMonth,
                            icon: const Icon(Icons.chevron_right),
                            visualDensity: VisualDensity.compact,
                          ),
                        ],
                      ),
                    ),
                    // Day-of-week headers
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      child: Row(
                        children: ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']
                            .map(
                              (d) => Expanded(
                                child: Center(
                                  child: Text(
                                    d,
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      color: theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ),
                              ),
                            )
                            .toList(),
                      ),
                    ),
                    const SizedBox(height: 4),
                    // Grid
                    CalendarGrid(
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
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: context.cs.primary.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        '${selectedExams.length}',
                        style: TextStyle(
                          color: context.cs.primary,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: selectedExams.isEmpty
                    ? Center(
                        child: Text(
                          'Không có kỳ thi',
                          style: TextStyle(color: context.cs.onSurfaceVariant),
                        ),
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: selectedExams.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (context, i) =>
                            CalendarExamCard(exam: selectedExams[i]),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}
