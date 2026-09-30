import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'widgets/learning_segment_switch.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/course_catalog_provider.dart';
import '../data/models/course_model.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/widgets/error_state.dart';
import '../../../../core/utils/format_vnd.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';

class CourseCatalogPage extends ConsumerStatefulWidget {
  const CourseCatalogPage({super.key});
  static const routeName = '/catalog';

  @override
  ConsumerState<CourseCatalogPage> createState() => _CourseCatalogPageState();
}

class _CourseCatalogPageState extends ConsumerState<CourseCatalogPage> {
  final _searchCtrl = TextEditingController();
  Timer? _debounce;
  String _searchQuery = '';
  int? _selectedCategoryId;

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
    final catalogAsync = ref.watch(
      courseCatalogProvider(
        search: _searchQuery.isEmpty ? null : _searchQuery,
        categoryId: _selectedCategoryId,
      ),
    );
    final categoriesAsync = ref.watch(courseCategoriesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Học tập'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(56),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Tìm kiếm khoá học...',
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
      body: Column(
        children: [
          const LearningSegmentSwitch(mine: false),
          // Category chips
          categoriesAsync.when(
            loading: () => const SizedBox(height: 44),
            error: (_, _) => const SizedBox.shrink(),
            data: (categories) => SizedBox(
              height: 44,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 4),
                itemCount: categories.length + 1,
                itemBuilder: (context, index) {
                  final isAll = index == 0;
                  final category = isAll ? null : categories[index - 1];
                  final isSelected = isAll
                      ? _selectedCategoryId == null
                      : _selectedCategoryId == category?.id;
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(
                        isAll ? 'Tất cả' : category!.name,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: isSelected
                              ? FontWeight.w700
                              : FontWeight.w500,
                        ),
                      ),
                      selected: isSelected,
                      onSelected: (_) => setState(
                        () => _selectedCategoryId = isAll ? null : category!.id,
                      ),
                      selectedColor: context.cs.primaryContainer,
                      checkmarkColor: context.cs.primary,
                      side: BorderSide(
                        color: isSelected
                            ? context.cs.primaryContainer
                            : context.cs.outlineVariant,
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                    ),
                  );
                },
              ),
            ),
          ),
          AppSpacing.h4,

          // Grid
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async {
                ref.invalidate(
                  courseCatalogProvider(
                    search: _searchQuery.isEmpty ? null : _searchQuery,
                    categoryId: _selectedCategoryId,
                  ),
                );
                ref.invalidate(courseCategoriesProvider);
              },
              child: catalogAsync.when(
                loading: () => const SkeletonList(),
                error: (e, _) => ErrorStateWidget(
                  error: e,
                  onRetry: () => ref.invalidate(courseCatalogProvider()),
                ),
                data: (courses) {
                  if (courses.isEmpty) {
                    return Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.search_off_outlined,
                            size: 64,
                            color: theme.colorScheme.outline,
                          ),
                          AppSpacing.h16,
                          Text(
                            'Không tìm thấy khoá học nào',
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          AppSpacing.h8,
                          Text(
                            'Thử từ khoá khác hoặc xoá bộ lọc',
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                  return GridView.builder(
                    padding: const EdgeInsets.all(16),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          childAspectRatio: 0.68,
                        ),
                    itemCount: courses.length,
                    itemBuilder: (context, index) =>
                        _CatalogCourseCard(course: courses[index]),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CatalogCourseCard extends StatelessWidget {
  const _CatalogCourseCard({required this.course});
  final CourseListItemModel course;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return GestureDetector(
      onTap: () => context.push('/courses/${course.id}'),
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
                child: course.thumbnail != null
                    ? CachedNetworkImage(
                        imageUrl: course.thumbnail!,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => _placeholder(context),
                      )
                    : _placeholder(context),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(10, 10, 10, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      course.title,
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
                          Icons.star_rounded,
                          color: course.reviewsCount > 0
                              ? context.sem.warning
                              : theme.colorScheme.outline,
                          size: 12,
                        ),
                        AppSpacing.w4,
                        Flexible(
                          child: Text(
                            course.reviewsCount > 0
                                ? '${course.avgRating.toStringAsFixed(1)} (${course.reviewsCount})'
                                : 'Chưa có đánh giá',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: course.reviewsCount > 0
                                  ? theme.colorScheme.onSurface
                                  : theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ),
                      ],
                    ),
                    AppSpacing.h8,
                    Text(
                      formatVnd(course.price),
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: course.price > 0
                            ? context.cs.primary
                            : context.sem.successFg,
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
      child: Icon(Icons.school_rounded, color: context.cs.primary, size: 32),
    );
  }
}
