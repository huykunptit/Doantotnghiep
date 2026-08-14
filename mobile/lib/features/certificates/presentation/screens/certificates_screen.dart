import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/certificate_providers.dart';
import '../../data/models/certificate_model.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/error/friendly_error.dart';
import '../../../auth/providers/auth_provider.dart';

const _verifyBaseUrl = 'https://eript-lms.io.vn/certificates/verify';

class CertificatesScreen extends ConsumerWidget {
  const CertificatesScreen({super.key});
  static const routeName = '/certificates';

  void _viewCertificate(BuildContext context, WidgetRef ref, UserCertificateModel cert) {
    final studentName = ref.read(authNotifierProvider).valueOrNull?.name;
    showDialog(
      context: context,
      builder: (_) => _CertificateViewDialog(cert: cert, studentName: studentName),
    );
  }

  Future<void> _copyLink(BuildContext context, String credentialId) async {
    final url = '$_verifyBaseUrl/$credentialId';
    await Clipboard.setData(ClipboardData(text: url));
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: const Text('Đã sao chép liên kết xác minh!'),
        backgroundColor: AppColors.success,
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
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(myCertificatesProvider),
          ),
        ],
      ),
      body: certificatesAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.error_outline, size: 48, color: AppColors.error),
                AppSpacing.h12,
                Text('Lỗi: ${friendlyErrorMessage(e)}', textAlign: TextAlign.center),
                AppSpacing.h16,
                FilledButton.icon(
                  onPressed: () => ref.invalidate(myCertificatesProvider),
                  icon: const Icon(Icons.refresh),
                  label: const Text('Thử lại'),
                ),
              ],
            ),
          ),
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
                      width: 80, height: 80,
                      decoration: BoxDecoration(
                        color: Colors.amber.shade50,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(Icons.workspace_premium_outlined,
                          size: 42, color: Colors.amber.shade700),
                    ),
                    AppSpacing.h20,
                    Text('Chưa có chứng chỉ nào',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    AppSpacing.h8,
                    Text('Hoàn thành các khoá học để nhận chứng chỉ của bạn.',
                        style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant, height: 1.5),
                        textAlign: TextAlign.center),
                    AppSpacing.h24,
                    FilledButton.icon(
                      onPressed: () => context.pop(),
                      icon: const Icon(Icons.school_outlined, size: 18),
                      label: const Text('Tiếp tục học tập'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary400,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
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
            itemBuilder: (context, index) => _CertificateCard(
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

class _CertificateCard extends StatelessWidget {
  const _CertificateCard({
    required this.cert,
    required this.onCopyLink,
    required this.onView,
  });

  final UserCertificateModel cert;
  final VoidCallback onCopyLink;
  final VoidCallback onView;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    String issuedDateStr = '';
    try {
      final parsed = DateTime.parse(cert.issuedAt);
      issuedDateStr = '${parsed.day}/${parsed.month}/${parsed.year}';
    } catch (_) {
      issuedDateStr = cert.issuedAt;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
        boxShadow: isDark ? [] : [
          BoxShadow(color: AppColors.neutral800.withValues(alpha: 0.06),
              blurRadius: 12, offset: const Offset(0, 3)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Thumbnail with gradient overlay
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: AspectRatio(
              aspectRatio: 2.2,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  cert.template?.backgroundImageUrl != null
                      ? CachedNetworkImage(
                          imageUrl: cert.template!.backgroundImageUrl!,
                          fit: BoxFit.cover,
                          errorWidget: (_, _, _) => _placeholder(),
                        )
                      : _placeholder(),
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Colors.transparent, Colors.black.withValues(alpha: 0.75)],
                        stops: const [0.4, 1.0],
                      ),
                    ),
                  ),
                  // Badge
                  Positioned(
                    top: 12, right: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.amber.shade600,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.workspace_premium_rounded, size: 13, color: Colors.white),
                          AppSpacing.w4,
                          const Text('Chứng chỉ',
                              style: TextStyle(fontSize: 11, color: Colors.white, fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 14, left: 16, right: 16,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(cert.courseTitle,
                            style: const TextStyle(color: Colors.white, fontSize: 15,
                                fontWeight: FontWeight.w800, shadows: [
                              Shadow(color: Colors.black54, blurRadius: 4),
                            ]),
                            maxLines: 2, overflow: TextOverflow.ellipsis),
                        AppSpacing.h4,
                        Text('Cấp ngày $issuedDateStr',
                            style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Credential ID + actions
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Mã chứng nhận',
                    style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                        fontSize: 11, letterSpacing: 0.4, fontWeight: FontWeight.w700)),
                AppSpacing.h8,
                Material(
                  color: isDark ? AppColors.darkBg.withValues(alpha: 0.5) : AppColors.neutral50,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    onTap: () async {
                      await Clipboard.setData(ClipboardData(text: cert.credentialId));
                      if (!context.mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: const Text('Đã sao chép mã chứng nhận'),
                          backgroundColor: AppColors.success,
                          behavior: SnackBarBehavior.floating,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(10),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(cert.credentialId,
                                style: const TextStyle(
                                  fontFamily: 'monospace', fontWeight: FontWeight.w700, fontSize: 12,
                                  letterSpacing: 0.4,
                                )),
                          ),
                          Icon(Icons.copy_rounded, size: 16, color: theme.colorScheme.onSurfaceVariant),
                        ],
                      ),
                    ),
                  ),
                ),
                AppSpacing.h12,
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: onCopyLink,
                        icon: const Icon(Icons.link_rounded, size: 16),
                        label: const Text('Sao chép link', style: TextStyle(fontSize: 12)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary600,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          side: const BorderSide(color: AppColors.primary200),
                        ),
                      ),
                    ),
                    AppSpacing.w8,
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: onView,
                        icon: const Icon(Icons.visibility_rounded, size: 16),
                        label: const Text('Xem chứng chỉ', style: TextStyle(fontSize: 12)),
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primary400,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _placeholder() {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [AppColors.primary600, AppColors.primary400],
          begin: Alignment.topLeft, end: Alignment.bottomRight,
        ),
      ),
      child: const Icon(Icons.workspace_premium_rounded, size: 56, color: Colors.white24),
    );
  }
}

class _CertificateViewDialog extends StatelessWidget {
  const _CertificateViewDialog({required this.cert, this.studentName});

  final UserCertificateModel cert;
  final String? studentName;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    String issuedDateStr = '';
    try {
      final parsed = DateTime.parse(cert.issuedAt);
      issuedDateStr = '${parsed.day}/${parsed.month}/${parsed.year}';
    } catch (_) {
      issuedDateStr = cert.issuedAt;
    }

    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      backgroundColor: isDark ? AppColors.darkSurface : Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: AspectRatio(
                aspectRatio: 1.6,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    cert.template?.backgroundImageUrl != null
                        ? CachedNetworkImage(
                            imageUrl: cert.template!.backgroundImageUrl!,
                            fit: BoxFit.cover,
                            errorWidget: (_, _, _) => Container(
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [AppColors.primary600, AppColors.primary400],
                                  begin: Alignment.topLeft, end: Alignment.bottomRight,
                                ),
                              ),
                            ),
                          )
                        : Container(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [AppColors.primary600, AppColors.primary400],
                                begin: Alignment.topLeft, end: Alignment.bottomRight,
                              ),
                            ),
                          ),
                    Container(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [Colors.transparent, Colors.black.withValues(alpha: 0.78)],
                          stops: const [0.45, 1.0],
                        ),
                      ),
                    ),
                    Positioned(
                      left: 18, right: 18, bottom: 16,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (studentName != null && studentName!.isNotEmpty)
                            Text(studentName!,
                                style: const TextStyle(color: Colors.white, fontSize: 18,
                                    fontWeight: FontWeight.w800, shadows: [
                                  Shadow(color: Colors.black54, blurRadius: 4),
                                ])),
                          AppSpacing.h4,
                          Text(cert.courseTitle,
                              style: const TextStyle(color: Colors.white, fontSize: 14,
                                  fontWeight: FontWeight.w600, shadows: [
                                Shadow(color: Colors.black54, blurRadius: 4),
                              ]),
                              maxLines: 2, overflow: TextOverflow.ellipsis),
                          AppSpacing.h4,
                          Text('Cấp ngày $issuedDateStr',
                              style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 12)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            AppSpacing.h16,
            Text('Mã chứng nhận',
                style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontSize: 11, letterSpacing: 0.4, fontWeight: FontWeight.w700)),
            AppSpacing.h4,
            SelectableText(cert.credentialId,
                style: const TextStyle(
                  fontFamily: 'monospace', fontWeight: FontWeight.w700, fontSize: 13,
                  letterSpacing: 0.4,
                )),
            AppSpacing.h16,
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary400,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Đóng'),
            ),
          ],
        ),
      ),
    );
  }
}
