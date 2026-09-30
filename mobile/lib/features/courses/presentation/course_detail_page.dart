import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/course_detail_provider.dart';
import 'widgets/course_detail_view.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class CourseDetailPage extends ConsumerWidget {
  const CourseDetailPage({super.key, required this.courseId});
  final int courseId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final courseAsync = ref.watch(courseDetailProvider(courseId));

    return courseAsync.when(
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(courseDetailProvider(courseId)),
        ),
      ),
      data: (course) => CourseDetailView(course: course),
    );
  }
}
