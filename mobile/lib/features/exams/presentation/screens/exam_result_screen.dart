import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/exam_providers.dart';
import 'package:eript_lms/features/exams/presentation/widgets/result_body.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class ExamResultScreen extends ConsumerWidget {
  const ExamResultScreen({super.key, required this.attemptId});
  final int attemptId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final resultAsync = ref.watch(examAttemptResultProvider(attemptId));

    return Scaffold(
      appBar: AppBar(
        title: const Text(
          'Kết quả chi tiết',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: resultAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(examAttemptResultProvider(attemptId)),
        ),
        data: (result) => ResultBody(result: result),
      ),
    );
  }
}
