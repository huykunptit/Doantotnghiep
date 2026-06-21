import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/course_catalog_provider.dart';
import '../data/models/course_model.dart';
import '../../../../core/theme/app_spacing.dart';

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
      setState(() {
        _searchQuery = query;
      });
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
        title: const Text('Khám phá khóa học'),
      ),
      body: Column(
        children: [
          // Search Input
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextFormField(
              controller: _searchCtrl,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'Tìm kiếm khóa học...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchCtrl.clear();
                          _onSearchChanged('');
                        },
                      )
                    : null,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              ),
            ),
          ),

          // Categories Horizontal List
          categoriesAsync.when(
            loading: () => const SizedBox(height: 48, child: Center(child: CircularProgressIndicator())),
            error: (_, _) => const SizedBox.shrink(),
            data: (categories) => SizedBox(
              height: 40,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: categories.length + 1,
                itemBuilder: (context, index) {
                  final isAll = index == 0;
                  final category = isAll ? null : categories[index - 1];
                  final isSelected = isAll
                      ? _selectedCategoryId == null
                      : _selectedCategoryId == category?.id;

                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(isAll ? 'Tất cả' : category!.name),
                      selected: isSelected,
                      onSelected: (selected) {
                        setState(() {
                          _selectedCategoryId = isAll ? null : category!.id;
                        });
                      },
                    ),
                  );
                },
              ),
            ),
          ),
          AppSpacing.h12,

          // Course Catalog Grid
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
                error: (e, _) => Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.error_outline, size: 48, color: Colors.red),
                      AppSpacing.h12,
                      Text('Lỗi tải danh mục khóa học: $e'),
                      AppSpacing.h16,
                      ElevatedButton(
                        onPressed: () => ref.invalidate(courseCatalogProvider()),
                        child: const Text('Thử lại'),
                      ),
                    ],
                  ),
                ),
                data: (courses) {
                  if (courses.isEmpty) {
                    return Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.search_off_outlined, size: 64, color: theme.colorScheme.outline),
                          AppSpacing.h16,
                          Text('Không tìm thấy khóa học nào phù hợp.', style: theme.textTheme.titleMedium),
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
                      childAspectRatio: 0.75,
                    ),
                    itemCount: courses.length,
                    itemBuilder: (context, index) {
                      final course = courses[index];
                      return _GridCourseCard(course: course);
                    },
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

class _GridCourseCard extends StatelessWidget {
  const _GridCourseCard({required this.course});

  final CourseListItemModel course;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => context.push('/courses/${course.id}'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Course Thumbnail
            AspectRatio(
              aspectRatio: 1.5,
              child: course.thumbnail != null
                  ? CachedNetworkImage(
                      imageUrl: course.thumbnail!,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      errorWidget: (_, _, _) => _placeholder(theme),
                    )
                  : _placeholder(theme),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(8.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      course.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                      ),
                    ),
                    Row(
                      children: [
                        Icon(Icons.star, color: Colors.amber.shade700, size: 12),
                        AppSpacing.w4,
                        Text(
                          course.avgRating.toStringAsFixed(1),
                          style: theme.textTheme.bodySmall?.copyWith(fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                        AppSpacing.w8,
                        Text(
                          '(${course.enrollmentsCount})',
                          style: theme.textTheme.bodySmall?.copyWith(fontSize: 10, color: theme.colorScheme.onSurfaceVariant),
                        ),
                      ],
                    ),
                    Text(
                      course.price > 0 ? '${course.price}đ' : 'Miễn phí',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.primary,
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
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

  Widget _placeholder(ThemeData theme) {
    return Container(
      color: theme.colorScheme.surfaceContainerHighest,
      alignment: Alignment.center,
      child: Icon(Icons.school_outlined, size: 36, color: theme.colorScheme.outline),
    );
  }
}
