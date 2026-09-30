import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/certificate_providers.dart';
import '../../data/models/certificate_model.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../auth/providers/auth_provider.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/certificates/presentation/widgets/certificate_card.dart';
import 'package:eript_lms/features/certificates/presentation/widgets/certificate_view_dialog.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class CertificatesScreen extends ConsumerWidget {
  const CertificatesScreen({super.key});
  static const routeName = '/certificates';

  void _viewCertificate(
    BuildContext context,
    WidgetRef ref,
    UserCertificateModel cert,
  ) {
    final studentName = ref.read(authNotifierProvider).valueOrNull?.name;
    showDialog(
      context: context,
      builder: (_) =>
          CertificateViewDialog(cert: cert, studentName: studentName),
    );
  }

  Future<void> _copyLink(BuildContext context, String credentialId) async {
    final url = '$certificatesVerifyBaseUrl/$credentialId';
    await Clipboard.setData(ClipboardData(text: url));
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Đã sao chép liên kết xác minh!'),
        backgroundColor: context.sem.success,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final certificatesAsync = ref.watch(myCertificatesProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Chứng chỉ của tôi'),
        actions: [
          IconButton(
            tooltip: 'Làm mới',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(myCertificatesProvider),
          ),
        ],
      ),
      body: certificatesAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(myCertificatesProvider),
        ),
        data: (certs) {
          if (certs.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: context.sem.warningBg,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.workspace_premium_outlined,
                        size: 42,
                        color: context.sem.warningFg,
                      ),
                    ),
                    AppSpacing.h20,
                    Text(
                      'Chưa có chứng chỉ nào',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    AppSpacing.h8,
                    Text(
                      'Hoàn thành các khoá học để nhận chứng chỉ của bạn.',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        height: 1.5,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    AppSpacing.h24,
                    FilledButton.icon(
                      onPressed: () => context.pop(),
                      icon: const Icon(Icons.school_outlined, size: 18),
                      label: const Text('Tiếp tục học tập'),
                      style: FilledButton.styleFrom(
                        backgroundColor: context.cs.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            itemCount: certs.length,
            itemBuilder: (context, index) => CertificateCard(
              cert: certs[index],
              onCopyLink: () => _copyLink(context, certs[index].credentialId),
              onView: () => _viewCertificate(context, ref, certs[index]),
            ),
          );
        },
      ),
    );
  }
}
