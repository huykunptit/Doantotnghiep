import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../data/models/career_model.dart';

/// Intro banner explaining the AI Career flow.
class CareerIntroHeader extends StatelessWidget {
  const CareerIntroHeader({super.key});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return AppCard(
      tone: AppCardTone.tinted,
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: cs.primary,
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.work_outline, color: cs.onPrimary, size: 28),
          ),
          AppSpacing.w16,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'AI Career',
                  style: context.tt.titleMedium?.copyWith(
                    color: cs.onPrimaryContainer,
                  ),
                ),
                AppSpacing.h4,
                Text(
                  'Upload hoặc điền form CV → đánh giá → chọn vị trí & mức lương → nhận khóa học gợi ý.',
                  style: context.tt.bodySmall?.copyWith(
                    color: cs.onPrimaryContainer,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// "Upload CV" tab: drop zone when there is no uploaded CV, else file row.
class CvUploadPanel extends StatelessWidget {
  const CvUploadPanel({
    super.key,
    required this.cv,
    required this.busy,
    required this.onPick,
  });

  final UserCvModel? cv;
  final bool busy;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final current = cv;
    if (current == null || current.source == 'form') {
      return InkWell(
        onTap: busy ? null : onPick,
        borderRadius: AppRadius.rLg,
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
          decoration: BoxDecoration(
            color: cs.surfaceContainerLow,
            borderRadius: AppRadius.rLg,
            border: Border.all(color: cs.outline),
          ),
          child: Column(
            children: [
              Icon(Icons.cloud_upload_outlined, size: 48, color: cs.primary),
              AppSpacing.h12,
              Text('Tải lên CV (PDF/DOC/DOCX)', style: context.tt.titleMedium),
              AppSpacing.h12,
              FilledButton.icon(
                onPressed: busy ? null : onPick,
                icon: const Icon(Icons.file_open_outlined),
                label: const Text('Chọn file'),
              ),
            ],
          ),
        ),
      );
    }

    return AppCard(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      child: Row(
        children: [
          Icon(Icons.description, color: cs.primary),
          AppSpacing.w12,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  current.fileName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: context.tt.titleSmall,
                ),
                Text(
                  'Đã tải lên ${current.createdAt.split('T').first}',
                  style: context.tt.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Tải CV khác',
            icon: const Icon(Icons.upload_file_outlined),
            onPressed: busy ? null : onPick,
          ),
        ],
      ),
    );
  }
}

/// Controllers behind the CV form (owned and disposed by the parent State).
class CvFormControllers {
  CvFormControllers()
    : fullName = TextEditingController(),
      email = TextEditingController(),
      phone = TextEditingController(),
      headline = TextEditingController(),
      summary = TextEditingController(),
      skills = TextEditingController(),
      targetRole = TextEditingController(),
      salary = TextEditingController(text: '8000000');

  final TextEditingController fullName;
  final TextEditingController email;
  final TextEditingController phone;
  final TextEditingController headline;
  final TextEditingController summary;
  final TextEditingController skills;
  final TextEditingController targetRole;
  final TextEditingController salary;

  void dispose() {
    for (final c in [
      fullName,
      email,
      phone,
      headline,
      summary,
      skills,
      targetRole,
      salary,
    ]) {
      c.dispose();
    }
  }
}

/// "Form CV" tab.
class CvFormPanel extends StatelessWidget {
  const CvFormPanel({
    super.key,
    required this.formKey,
    required this.controllers,
    required this.busy,
    required this.onSave,
  });

  final GlobalKey<FormState> formKey;
  final CvFormControllers controllers;
  final bool busy;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    Widget field(
      TextEditingController c,
      String label, {
      int maxLines = 1,
      bool digits = false,
      String? Function(String?)? validator,
    }) {
      return Padding(
        padding: const EdgeInsets.only(bottom: AppSpacing.space3),
        child: TextFormField(
          controller: c,
          maxLines: maxLines,
          keyboardType: digits ? TextInputType.number : null,
          inputFormatters: digits
              ? [FilteringTextInputFormatter.digitsOnly]
              : null,
          decoration: InputDecoration(labelText: label),
          validator: validator,
        ),
      );
    }

    return Form(
      key: formKey,
      child: Column(
        children: [
          field(
            controllers.fullName,
            'Họ tên *',
            validator: (v) =>
                (v == null || v.trim().isEmpty) ? 'Bắt buộc' : null,
          ),
          field(controllers.email, 'Email'),
          field(controllers.phone, 'SĐT'),
          field(controllers.headline, 'Headline / Vị trí'),
          field(controllers.summary, 'Tóm tắt', maxLines: 3),
          field(controllers.skills, 'Kỹ năng (cách nhau bởi dấu phẩy)'),
          field(controllers.targetRole, 'Vị trí mục tiêu'),
          field(controllers.salary, 'Mức lương mong muốn (VND)', digits: true),
          AppSpacing.h4,
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: busy ? null : onSave,
              icon: const Icon(Icons.save_outlined),
              label: const Text('Lưu form & đánh giá'),
            ),
          ),
        ],
      ),
    );
  }
}

/// Target role + salary inputs used for evaluation / recommendations.
class CareerTargetPanel extends StatelessWidget {
  const CareerTargetPanel({
    super.key,
    required this.formKey,
    required this.jobTitle,
    required this.salary,
  });

  final GlobalKey<FormState> formKey;
  final TextEditingController jobTitle;
  final TextEditingController salary;

  @override
  Widget build(BuildContext context) {
    return Form(
      key: formKey,
      child: AppCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Vị trí & mức lương mục tiêu', style: context.tt.titleMedium),
            AppSpacing.h12,
            TextFormField(
              controller: jobTitle,
              decoration: const InputDecoration(
                labelText: 'Vị trí mục tiêu (VD: Backend Developer)',
              ),
              validator: (v) => (v == null || v.trim().isEmpty)
                  ? 'Nhập vị trí mục tiêu'
                  : null,
            ),
            AppSpacing.h12,
            TextFormField(
              controller: salary,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                labelText: 'Mức lương mong muốn (VND)',
              ),
            ),
          ],
        ),
      ),
    );
  }
}
