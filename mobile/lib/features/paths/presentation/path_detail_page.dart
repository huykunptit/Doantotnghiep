import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/path_detail_provider.dart';
import 'widgets/path_detail_view.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class PathDetailPage extends ConsumerWidget {
  const PathDetailPage({super.key, required this.slug});
  final String slug;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pathAsync = ref.watch(pathDetailProvider(slug));

    return pathAsync.when(
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(pathDetailProvider(slug)),
        ),
      ),
      data: (path) => PathDetailView(path: path, slug: slug),
    );
  }
}
