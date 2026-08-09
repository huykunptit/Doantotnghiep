import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/models/student_models.dart';
import '../../providers/student_providers.dart';
import '../../../../core/error/friendly_error.dart';

class LibraryScreen extends ConsumerStatefulWidget {
  const LibraryScreen({super.key});

  @override
  ConsumerState<LibraryScreen> createState() => _LibraryScreenState();
}

class _LibraryScreenState extends ConsumerState<LibraryScreen> {
  String _search = '';
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final libraryAsync = ref.watch(myLibraryProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tài liệu học tập',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: libraryAsync.when(
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
                  onPressed: () => ref.invalidate(myLibraryProvider),
                  child: const Text('Thử lại')),
            ],
          ),
        ),
        data: (attachments) {
          final filtered = _search.isEmpty
              ? attachments
              : attachments.where((a) {
                  final q = _search.toLowerCase();
                  return a.title.toLowerCase().contains(q) ||
                      a.courseTitle.toLowerCase().contains(q) ||
                      a.lessonTitle.toLowerCase().contains(q);
                }).toList();

          // Group by course
          final grouped = <String, List<LibraryAttachmentModel>>{};
          for (final a in filtered) {
            grouped.putIfAbsent(a.courseTitle, () => []).add(a);
          }

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: TextField(
                  controller: _controller,
                  decoration: InputDecoration(
                    hintText: 'Tìm kiếm tài liệu...',
                    prefixIcon: const Icon(Icons.search, size: 20),
                    suffixIcon: _search.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () {
                              _controller.clear();
                              setState(() => _search = '');
                            },
                          )
                        : null,
                    isDense: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide(
                        color: Theme.of(context)
                            .colorScheme
                            .outlineVariant,
                      ),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide(
                        color: Theme.of(context)
                            .colorScheme
                            .outlineVariant,
                      ),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                        horizontal: 12, vertical: 10),
                  ),
                  onChanged: (v) => setState(() => _search = v),
                ),
              ),
              Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                child: Row(
                  children: [
                    Text('${filtered.length} tài liệu',
                        style: TextStyle(
                            fontSize: 12,
                            color: Theme.of(context)
                                .colorScheme
                                .onSurfaceVariant)),
                  ],
                ),
              ),
              Expanded(
                child: filtered.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.folder_open,
                                size: 64, color: Colors.grey),
                            SizedBox(height: 12),
                            Text('Không tìm thấy tài liệu nào',
                                style: TextStyle(color: Colors.grey)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () async =>
                            ref.invalidate(myLibraryProvider),
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                          itemCount: grouped.length,
                          itemBuilder: (context, i) {
                            final courseTitle =
                                grouped.keys.elementAt(i);
                            final items = grouped[courseTitle]!;
                            return _CourseSection(
                              courseTitle: courseTitle,
                              items: items,
                            );
                          },
                        ),
                      ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _CourseSection extends StatefulWidget {
  const _CourseSection(
      {required this.courseTitle, required this.items});
  final String courseTitle;
  final List<LibraryAttachmentModel> items;

  @override
  State<_CourseSection> createState() => _CourseSectionState();
}

class _CourseSectionState extends State<_CourseSection> {
  bool _expanded = true;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: () => setState(() => _expanded = !_expanded),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Row(
              children: [
                const Icon(Icons.folder, size: 18, color: AppColors.warning),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    widget.courseTitle,
                    style: theme.textTheme.bodyMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.primary400.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${widget.items.length}',
                    style: const TextStyle(
                        color: AppColors.primary400,
                        fontWeight: FontWeight.w700,
                        fontSize: 12),
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  _expanded
                      ? Icons.keyboard_arrow_up
                      : Icons.keyboard_arrow_down,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ],
            ),
          ),
        ),
        if (_expanded)
          ...widget.items
              .map((item) => _AttachmentTile(item: item))
              .toList(),
        const SizedBox(height: 4),
      ],
    );
  }
}

class _AttachmentTile extends StatelessWidget {
  const _AttachmentTile({required this.item});
  final LibraryAttachmentModel item;

  static const _typeIconData = {
    'PDF': (Icons.picture_as_pdf, AppColors.error),
    'Word': (Icons.article, AppColors.secondary400),
    'Excel': (Icons.table_chart, AppColors.success),
    'PowerPoint': (Icons.slideshow, AppColors.warning),
    'Archive': (Icons.archive, Colors.brown),
    'Video': (Icons.videocam, AppColors.primary400),
    'Hình ảnh': (Icons.image, AppColors.secondary400),
  };

  Future<void> _open() async {
    if (item.fileUrl == null) return;
    final uri = Uri.tryParse(item.fileUrl!);
    if (uri != null && await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final typeInfo = _typeIconData[item.displayFileType];
    final icon = typeInfo?.$1 ?? Icons.insert_drive_file;
    final color = typeInfo?.$2 ?? AppColors.primary400;

    return Card(
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: theme.colorScheme.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: item.fileUrl != null ? _open : null,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
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
                    Text(
                      item.title,
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.lessonTitle.isNotEmpty
                          ? item.lessonTitle
                          : item.courseTitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (item.fileSizeText.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        '${item.displayFileType} • ${item.fileSizeText}',
                        style: TextStyle(
                            fontSize: 11, color: Colors.grey.shade500),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                Icons.download_outlined,
                color: theme.colorScheme.onSurfaceVariant,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
