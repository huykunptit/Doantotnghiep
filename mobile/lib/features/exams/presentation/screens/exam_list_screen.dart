import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/error/friendly_error.dart';
import '../../data/models/exam_list_model.dart';
import '../../providers/exam_providers.dart';

class ExamListScreen extends ConsumerStatefulWidget {
  const ExamListScreen({super.key});

  @override
  ConsumerState<ExamListScreen> createState() => _ExamListScreenState();
}

class _ExamListScreenState extends ConsumerState<ExamListScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  int _tabIndex = 0;

  static const _tabs = [
    ('', 'Tất cả'),
    ('upcoming', 'Sắp tới'),
    ('active', 'Đang mở'),
    ('done', 'Đã làm'),
  ];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _tabs.length, vsync: this);
    _tabController.addListener(() {
      if (_tabController.indexIsChanging) return;
      setState(() => _tabIndex = _tabController.index);
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tab = _tabs[_tabIndex].$1;
    final examsAsync = ref.watch(myExamsProvider(tab: tab));

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      appBar: AppBar(
        title: const Text('Kỳ thi của tôi',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelStyle:
              const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
          unselectedLabelStyle:
              const TextStyle(fontWeight: FontWeight.w400, fontSize: 13),
          indicatorSize: TabBarIndicatorSize.label,
          tabs: _tabs.map((t) => Tab(text: t.$2)).toList(),
        ),
      ),
      body: examsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorState(
          message: friendlyErrorMessage(e),
          onRetry: () => ref.invalidate(myExamsProvider(tab: tab)),
        ),
        data: (exams) {
          if (exams.isEmpty) {
            return _EmptyState(tab: _tabs[_tabIndex].$2);
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myExamsProvider(tab: tab)),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: exams.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) =>
                  _ExamCard(exam: exams[index]),
            ),
          );
        },
      ),
    );
  }
}

class _ExamCard extends StatelessWidget {
  const _ExamCard({required this.exam});
  final ExamListItemModel exam;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: theme.colorScheme.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: exam.isLive
            ? () => context.push('/exam/${exam.id}')
            : exam.isDone && exam.myAttempt != null
                ? () => context.push('/exam-result/${exam.myAttempt!.id}')
                : null,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _StatusChip(exam: exam),
                        const SizedBox(height: 8),
                        Text(
                          exam.title,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (exam.course != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            exam.course!.title,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (exam.isDone && exam.myAttempt?.score != null)
                    _ScoreBadge(
                        score: exam.myAttempt!.score!,
                        passed: exam.myAttempt!.passed ?? false),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _InfoChip(
                    icon: Icons.timer_outlined,
                    label: exam.duration != null
                        ? '${exam.duration} phút'
                        : 'Không giới hạn',
                  ),
                  const SizedBox(width: 8),
                  _InfoChip(
                    icon: Icons.check_circle_outline,
                    label: 'Đạt: ${exam.passScore}%',
                  ),
                  if (exam.proctoringEnabled) ...[
                    const SizedBox(width: 8),
                    _InfoChip(
                      icon: Icons.videocam_outlined,
                      label: 'Giám sát',
                    ),
                  ],
                ],
              ),
              if (exam.startTime != null || exam.endTime != null) ...[
                const SizedBox(height: 8),
                _TimeRow(start: exam.startTime, end: exam.endTime),
              ],
              if (exam.isLive || (exam.isDone && exam.myAttempt != null)) ...[
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: exam.isLive
                        ? () => context.push('/exam/${exam.id}')
                        : () => context.push(
                            '/exam-result/${exam.myAttempt!.id}'),
                    style: FilledButton.styleFrom(
                      backgroundColor: exam.isLive
                          ? AppColors.primary400
                          : theme.colorScheme.secondaryContainer,
                      foregroundColor: exam.isLive
                          ? Colors.white
                          : theme.colorScheme.onSecondaryContainer,
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10)),
                    ),
                    child: Text(
                      exam.isLive ? 'Vào thi ngay' : 'Xem kết quả chi tiết',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.exam});
  final ExamListItemModel exam;

  @override
  Widget build(BuildContext context) {
    late Color bg;
    late Color fg;
    late String label;

    if (exam.isLive) {
      bg = AppColors.success.withOpacity(0.12);
      fg = AppColors.success;
      label = '● Đang mở';
    } else if (exam.isDone) {
      final passed = exam.myAttempt?.passed ?? false;
      bg = (passed ? AppColors.success : AppColors.error).withOpacity(0.1);
      fg = passed ? AppColors.success : AppColors.error;
      label = passed ? '✓ Đã đạt' : '✗ Chưa đạt';
    } else if (exam.isUpcoming) {
      bg = AppColors.warning.withOpacity(0.12);
      fg = AppColors.warning;
      label = '○ Sắp diễn ra';
    } else {
      bg = Colors.grey.shade100;
      fg = Colors.grey.shade600;
      label = exam.status;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration:
          BoxDecoration(color: bg, borderRadius: BorderRadius.circular(6)),
      child: Text(label,
          style: TextStyle(
              color: fg, fontSize: 11, fontWeight: FontWeight.w700)),
    );
  }
}

class _ScoreBadge extends StatelessWidget {
  const _ScoreBadge({required this.score, required this.passed});
  final double score;
  final bool passed;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: (passed ? AppColors.success : AppColors.error).withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        children: [
          Text(
            '${score.toStringAsFixed(1)}',
            style: TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 18,
              color: passed ? AppColors.success : AppColors.error,
            ),
          ),
          Text(
            'điểm',
            style: TextStyle(
              fontSize: 10,
              color: passed ? AppColors.success : AppColors.error,
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13,
              color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 4),
          Text(label,
              style: TextStyle(
                  fontSize: 11,
                  color: theme.colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }
}

class _TimeRow extends StatelessWidget {
  const _TimeRow({this.start, this.end});
  final String? start;
  final String? end;

  String _fmt(String? iso) {
    if (iso == null) return '--';
    try {
      final dt = DateTime.parse(iso).toLocal();
      return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        Icon(Icons.schedule, size: 13,
            color: theme.colorScheme.onSurfaceVariant),
        const SizedBox(width: 4),
        Text(
          '${_fmt(start)} — ${_fmt(end)}',
          style: TextStyle(
              fontSize: 11,
              color: theme.colorScheme.onSurfaceVariant),
        ),
      ],
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.tab});
  final String tab;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.assignment_outlined, size: 64,
              color: Colors.grey),
          const SizedBox(height: 12),
          Text('Không có kỳ thi nào ($tab)',
              style: const TextStyle(color: Colors.grey)),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 48, color: Colors.red),
          const SizedBox(height: 8),
          Text(message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey)),
          const SizedBox(height: 12),
          FilledButton(onPressed: onRetry, child: const Text('Thử lại')),
        ],
      ),
    );
  }
}
