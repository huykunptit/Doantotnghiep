import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/models/student_models.dart';
import '../../providers/student_providers.dart';
import '../../../../core/error/friendly_error.dart';

class TasksScreen extends ConsumerStatefulWidget {
  const TasksScreen({super.key});

  @override
  ConsumerState<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends ConsumerState<TasksScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nhiệm vụ của tôi',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Chưa hoàn thành'),
            Tab(text: 'Đã hoàn thành'),
          ],
          labelStyle: const TextStyle(fontWeight: FontWeight.w600),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: const [
          _TaskList(done: false),
          _TaskList(done: true),
        ],
      ),
    );
  }
}

class _TaskList extends ConsumerWidget {
  const _TaskList({required this.done});
  final bool done;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tasksAsync = ref.watch(myTasksProvider(done: done));

    return tasksAsync.when(
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
                onPressed: () => ref.invalidate(myTasksProvider(done: done)),
                child: const Text('Thử lại')),
          ],
        ),
      ),
      data: (tasks) {
        if (tasks.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  done
                      ? Icons.task_alt
                      : Icons.assignment_outlined,
                  size: 64,
                  color: Colors.grey,
                ),
                const SizedBox(height: 12),
                Text(
                  done
                      ? 'Chưa có nhiệm vụ nào hoàn thành'
                      : 'Không có nhiệm vụ nào cần làm',
                  style: const TextStyle(color: Colors.grey),
                ),
              ],
            ),
          );
        }
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(myTasksProvider(done: done)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: tasks.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, i) => _TaskCard(task: tasks[i]),
          ),
        );
      },
    );
  }
}

class _TaskCard extends StatelessWidget {
  const _TaskCard({required this.task});
  final TaskModel task;

  static const _typeIcon = {
    'assignment': Icons.assignment_outlined,
    'quiz': Icons.quiz_outlined,
    'exam': Icons.description_outlined,
    'survey': Icons.poll_outlined,
  };

  static const _typeLabel = {
    'assignment': 'Bài tập',
    'quiz': 'Bài kiểm tra',
    'exam': 'Thi',
    'survey': 'Khảo sát',
  };

  static const _typeColor = {
    'assignment': AppColors.secondary400,
    'quiz': AppColors.warning,
    'exam': AppColors.error,
    'survey': AppColors.success,
  };

  bool get _isOverdue {
    if (task.isDone || task.dueDate == null) return false;
    final due = DateTime.tryParse(task.dueDate!);
    return due != null && due.isBefore(DateTime.now());
  }

  String _fmtDue() {
    if (task.dueDate == null) return '';
    try {
      final dt = DateTime.parse(task.dueDate!).toLocal();
      return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {
      return task.dueDate!;
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = _typeColor[task.type] ?? AppColors.primary400;
    final icon = _typeIcon[task.type] ?? Icons.task_outlined;
    final typeLabel = _typeLabel[task.type] ?? task.type;
    final overdue = _isOverdue;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: overdue
              ? AppColors.error.withOpacity(0.4)
              : theme.colorScheme.outlineVariant,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: color.withOpacity(0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              alignment: Alignment.center,
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: color.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(typeLabel,
                            style: TextStyle(
                                color: color,
                                fontSize: 10,
                                fontWeight: FontWeight.w700)),
                      ),
                      if (overdue) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.error.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: const Text('Quá hạn',
                              style: TextStyle(
                                  color: AppColors.error,
                                  fontSize: 10,
                                  fontWeight: FontWeight.w700)),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    task.title,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      decoration: task.isDone
                          ? TextDecoration.lineThrough
                          : null,
                      color: task.isDone
                          ? theme.colorScheme.onSurfaceVariant
                          : null,
                    ),
                  ),
                  if (task.course != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      task.course!.title,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                  if (task.dueDate != null) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(
                          Icons.schedule,
                          size: 12,
                          color: overdue
                              ? AppColors.error
                              : theme.colorScheme.onSurfaceVariant,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          'Hạn: ${_fmtDue()}',
                          style: TextStyle(
                            fontSize: 11,
                            color: overdue
                                ? AppColors.error
                                : theme.colorScheme.onSurfaceVariant,
                            fontWeight: overdue ? FontWeight.w600 : null,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            if (task.isDone)
              const Icon(Icons.check_circle,
                  color: AppColors.success, size: 20),
          ],
        ),
      ),
    );
  }
}
