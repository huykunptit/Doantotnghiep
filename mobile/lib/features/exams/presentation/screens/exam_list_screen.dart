import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/error/friendly_error.dart';
import '../../providers/exam_providers.dart';
import 'package:eript_lms/features/exams/presentation/widgets/exam_card.dart';
import 'package:eript_lms/features/exams/presentation/widgets/exam_list_empty_state.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';

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
        title: const Text(
          'Kỳ thi của tôi',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelStyle: const TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
          unselectedLabelStyle: const TextStyle(
            fontWeight: FontWeight.w400,
            fontSize: 13,
          ),
          indicatorSize: TabBarIndicatorSize.label,
          tabs: _tabs.map((t) => Tab(text: t.$2)).toList(),
        ),
      ),
      body: examsAsync.when(
        loading: () => const SkeletonList(),
        error: (e, _) => ExamListErrorState(
          message: friendlyErrorMessage(e),
          onRetry: () => ref.invalidate(myExamsProvider(tab: tab)),
        ),
        data: (exams) {
          if (exams.isEmpty) {
            return ExamListEmptyState(tab: _tabs[_tabIndex].$2);
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myExamsProvider(tab: tab)),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: exams.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) => ExamCard(exam: exams[index]),
            ),
          );
        },
      ),
    );
  }
}
