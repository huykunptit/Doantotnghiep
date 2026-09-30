import 'package:eript_lms/core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_loader.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../data/models/attachment_model.dart';

/// "Tài liệu" tab: downloadable files attached to the lesson.
class LessonAttachmentsTab extends StatelessWidget {
  const LessonAttachmentsTab({super.key, required this.attachments});

  final AsyncValue<List<AttachmentModel>> attachments;

  @override
  Widget build(BuildContext context) {
    return attachments.when(
      loading: () => const Center(
        child: AppLoader(
          compact: true,
          size: 64,
          message: 'Đang tải tài liệu...',
        ),
      ),
      error: (e, _) => EmptyState(
        icon: Icons.error_outline_rounded,
        title: 'Không tải được tài liệu',
        message: friendlyErrorMessage(e),
      ),
      data: (files) {
        if (files.isEmpty) {
          return const EmptyState(
            icon: Icons.folder_open_outlined,
            title: 'Không có tài liệu đính kèm',
            message: 'Bài học này chưa có tài liệu.',
          );
        }
        return ListView.builder(
          itemCount: files.length,
          itemBuilder: (context, i) {
            final file = files[i];
            return ListTile(
              leading: Icon(
                Icons.download_for_offline_outlined,
                color: context.sem.info,
              ),
              title: Text(file.title),
              subtitle: file.fileSize != null
                  ? Text('${(file.fileSize! / 1024).toStringAsFixed(1)} KB')
                  : null,
              onTap: () async {
                final uri = Uri.parse(file.fileUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                } else if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Không thể mở liên kết tải file.'),
                    ),
                  );
                }
              },
            );
          },
        );
      },
    );
  }
}
