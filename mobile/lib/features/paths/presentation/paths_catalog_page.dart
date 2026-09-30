import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/paths_catalog_provider.dart';
import '../data/models/career_path_model.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../core/utils/format_vnd.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class PathsCatalogPage extends ConsumerStatefulWidget {
  const PathsCatalogPage({super.key});
  static const routeName = '/paths';

  @override
  ConsumerState<PathsCatalogPage> createState() => _PathsCatalogPageState();
}

class _PathsCatalogPageState extends ConsumerState<PathsCatalogPage> {
  final _searchCtrl = TextEditingController();
  Timer? _debounce;
  String _searchQuery = '';

  @override
  void dispose() {
    _searchCtrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearchChanged(String query) {
    if (_debounce?.isActive ?? false) _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 500), () {
      setState(() => _searchQuery = query);
    });
  }

  @override
  Widget build(BuildContext context) {
    final pathsAsync = ref.watch(
      pathsCatalogProvider(search: _searchQuery.isEmpty ? null : _searchQuery),
    );
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lộ trình nghề nghiệp'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Tìm kiếm lộ trình...',
                prefixIcon: const Icon(Icons.search_rounded, size: 20),
                suffixIcon: _searchCtrl.text.isNotEmpty
                    ? IconButton(
                        tooltip: 'Xóa',
                        icon: const Icon(Icons.clear_rounded, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          _onSearchChanged('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: theme.colorScheme.surfaceContainerHighest.withValues(
                  alpha: 0.5,
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 10,
                ),
              ),
            ),
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(
            pathsCatalogProvider(
              search: _searchQuery.isEmpty ? null : _searchQuery,
            ),
          );
        },
        child: pathsAsync.when(
          loading: () => const SkeletonList(),
          error: (e, _) => ErrorStateWidget(
            error: e,
            onRetry: () => ref.invalidate(
              pathsCatalogProvider(
                search: _searchQuery.isEmpty ? null : _searchQuery,
              ),
            ),
          ),
          data: (paths) {
            if (paths.isEmpty) {
              return ListView(
                children: [
                  SizedBox(height: MediaQuery.of(context).size.height * 0.25),
                  Center(
                    child: Column(
                      children: [
                        Icon(
                          Icons.search_off_outlined,
                          size: 64,
                          color: theme.colorScheme.outline,
                        ),
                        AppSpacing.h16,
                        Text(
                          'Không tìm thấy lộ trình nào',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        AppSpacing.h8,
                        Text(
                          'Thử từ khoá khác',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              );
            }
            return GridView.builder(
              padding: const EdgeInsets.all(16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 0.72,
              ),
              itemCount: paths.length,
              itemBuilder: (context, index) => _PathCard(path: paths[index]),
            );
          },
        ),
      ),
    );
  }
}

class _PathCard extends StatelessWidget {
  const _PathCard({required this.path});
  final CareerPathListItem path;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return GestureDetector(
      onTap: () => context.push('/paths/${path.slug}'),
      child: Container(
        decoration: BoxDecoration(
          color: context.cs.surfaceContainerLowest,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: context.cs.outlineVariant),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(14),
              ),
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: path.coverUrl != null
                    ? CachedNetworkImage(
                        imageUrl: path.coverUrl!,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => _placeholder(context),
                      )
                    : _placeholder(context),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      path.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        height: 1.3,
                        fontSize: 12,
                      ),
                    ),
                    const Spacer(),
                    Row(
                      children: [
                        Icon(
                          Icons.menu_book_outlined,
                          size: 12,
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                        AppSpacing.w4,
                        Text(
                          '${path.pathCoursesCount} khoá',
                          style: TextStyle(
                            fontSize: 12,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h4,
                    Text(
                      formatVnd(path.price),
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        color: path.price > 0
                            ? context.cs.primary
                            : context.sem.success,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _placeholder(BuildContext context) {
    return Container(
      color: context.cs.primaryContainer,
      child: Icon(Icons.route_rounded, color: context.cs.primary, size: 32),
    );
  }
}
