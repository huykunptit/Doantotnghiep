import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/course_catalog_provider.dart';
import '../data/models/course_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/widgets/error_state.dart';

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
    final catalogAsync = ref.watch(courseCatalogProvider(
      search: _searchQuery.isEmpty ? null : _searchQuery,
      categoryId: _selectedCategoryId,
    ));
    final categoriesAsync = ref.watch(courseCategoriesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Khám phá khoá học'),
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
                        icon: const Icon(Icons.clear_rounded, size: 18),
                        onPressed: () { _searchCtrl.clear(); _onSearchChanged(''); },
                      )
                    : null,
                filled: true,
                fillColor: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide.none,
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
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
                  final isSelected = isAll ? _selectedCategoryId == null : _selectedCategoryId == category?.id;
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(isAll ? 'Tất cả' : category!.name,
                          style: TextStyle(fontSize: 12, fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500)),
                      selected: isSelected,
                      onSelected: (_) => setState(() => _selectedCategoryId = isAll ? null : category!.id),
                      selectedColor: AppColors.primary100,
                      checkmarkColor: AppColors.primary600,
                      side: BorderSide(color: isSelected ? AppColors.primary200 : AppColors.neutral200),
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
                ref.invalidate(courseCatalogProvider(
                  search: _searchQuery.isEmpty ? null : _searchQuery,
                  categoryId: _selectedCategoryId,
                ));
                ref.invalidate(courseCategoriesProvider);
              },
              child: catalogAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
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
                          Icon(Icons.search_off_outlined, size: 64, color: theme.colorScheme.outline),
                          AppSpacing.h16,
                          Text('Không tìm thấy khoá học nào',
                              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                          AppSpacing.h8,
                          Text('Thử từ khoá khác hoặc xoá bộ lọc',
                              style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant)),
                        ],
                      ),
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
                    itemCount: courses.length,
                    itemBuilder: (context, index) => _CatalogCourseCard(course: courses[index]),
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
    final isDark = theme.brightness == Brightness.dark;

    return GestureDetector(
      onTap: () => context.push('/courses/${course.id}'),
      child: Container(
        decoration: BoxDecoration(
          color: isDark ? AppColors.darkSurface : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
          boxShadow: isDark ? [] : [
            BoxShadow(color: AppColors.neutral800.withValues(alpha: 0.06),
                blurRadius: 10, offset: const Offset(0, 2)),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: course.thumbnail != null
                    ? CachedNetworkImage(
                        imageUrl: course.thumbnail!,
                        fit: BoxFit.cover,
                        errorWidget: (_, _, _) => _placeholder(),
                      )
                    : _placeholder(),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(course.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                            fontWeight: FontWeight.w700, height: 1.3, fontSize: 12)),
                    const Spacer(),
                    Row(
                      children: [
                        Icon(Icons.star_rounded, color: Colors.amber.shade600, size: 12),
                        AppSpacing.w4,
                        Text(course.avgRating.toStringAsFixed(1),
                            style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700)),
                        AppSpacing.w4,
                        Text('(${course.enrollmentsCount})',
                            style: TextStyle(fontSize: 10, color: theme.colorScheme.onSurfaceVariant)),
                      ],
                    ),
                    AppSpacing.h4,
                    Text(
                      course.price > 0 ? '${course.price}đ' : 'Miễn phí',
                      style: TextStyle(
                        fontSize: 12, fontWeight: FontWeight.w800,
                        color: course.price > 0 ? AppColors.primary600 : AppColors.success,
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

  Widget _placeholder() {
    return Container(
      color: AppColors.primary50,
      child: const Icon(Icons.school_rounded, color: AppColors.primary200, size: 32),
    );
  }
}
