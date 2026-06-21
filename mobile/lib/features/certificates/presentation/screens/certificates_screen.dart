import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/certificate_providers.dart';
import '../../data/models/certificate_model.dart';
import '../../../../core/theme/app_spacing.dart';

class CertificatesScreen extends ConsumerWidget {
  const CertificatesScreen({super.key});

  static const routeName = '/certificates';

  Future<void> _openVerifyUrl(BuildContext context, String credentialId) async {
    // In mobile, we verify through the web URL directly
    final url = Uri.parse('https://sylvalms.wetech.vn/certificates/verify/$credentialId');
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    } else {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Không thể mở liên kết xác minh.')),
        );
      }
    }
  }

  void _copyLink(BuildContext context, String credentialId) {
    final url = 'https://sylvalms.wetech.vn/certificates/verify/$credentialId';
    Clipboard.setData(ClipboardData(text: url)).then((_) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Đã sao chép liên kết xác minh vào bộ nhớ tạm!'),
          backgroundColor: Colors.green,
        ),
      );
    });
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
            icon: const Icon(Icons.refresh),
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
                const Icon(Icons.error_outline, size: 48, color: Colors.red),
                AppSpacing.h12,
                Text('Lỗi: $e', textAlign: TextAlign.center),
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
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.workspace_premium_outlined, size: 64, color: theme.colorScheme.outline),
                  AppSpacing.h16,
                  const Text('Bạn chưa nhận được chứng chỉ nào.'),
                  AppSpacing.h16,
                  FilledButton(
                    onPressed: () => context.pop(),
                    child: const Text('Tiếp tục học tập'),
                  ),
                ],
              ),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: certs.length,
            itemBuilder: (context, index) {
              final cert = certs[index];
              return _buildCertificateCard(context, cert);
            },
          );
        },
      ),
    );
  }

  Widget _buildCertificateCard(BuildContext context, UserCertificateModel cert) {
    final theme = Theme.of(context);
    
    // Attempt formatting issued date cleanly
    String issuedDateStrStr = '';
    try {
      final parsed = DateTime.parse(cert.issuedAt);
      issuedDateStrStr = '${parsed.day}/${parsed.month}/${parsed.year}';
    } catch (_) {
      issuedDateStrStr = cert.issuedAt;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      clipBehavior: Clip.antiAlias,
      elevation: 2,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Visual thumbnail with overlay
          AspectRatio(
            aspectRatio: 16 / 10,
            child: Stack(
              children: [
                if (cert.template?.backgroundImageUrl != null)
                  CachedNetworkImage(
                    imageUrl: cert.template!.backgroundImageUrl!,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    errorWidget: (_, _, _) => _buildPlaceholder(context),
                  )
                else
                  _buildPlaceholder(context),
                
                // Dark overlay
                Positioned.fill(
                  child: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.transparent,
                          Colors.black.withValues(alpha: 0.8),
                        ],
                      ),
                    ),
                  ),
                ),
                
                // Overlay text
                Positioned(
                  bottom: 16,
                  left: 16,
                  right: 16,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        cert.courseTitle,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      AppSpacing.h4,
                      Text(
                        'Cấp ngày $issuedDateStrStr',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.8),
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Certificate details and action row
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'MÃ CHỨNG NHẬN',
                  style: theme.textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: theme.colorScheme.onSurfaceVariant,
                    letterSpacing: 1.1,
                  ),
                ),
                AppSpacing.h4,
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: theme.colorScheme.outlineVariant),
                  ),
                  child: Text(
                    cert.credentialId,
                    style: const TextStyle(
                      fontFamily: 'Courier',
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                ),
                AppSpacing.h16,
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _copyLink(context, cert.credentialId),
                        icon: const Icon(Icons.link, size: 16),
                        label: const Text('Copy link', style: TextStyle(fontSize: 13)),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                      ),
                    ),
                    AppSpacing.w12,
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: () => _openVerifyUrl(context, cert.credentialId),
                        icon: const Icon(Icons.open_in_new, size: 16),
                        label: const Text('Xác minh', style: TextStyle(fontSize: 13)),
                        style: FilledButton.styleFrom(
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

  Widget _buildPlaceholder(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      color: theme.colorScheme.primaryContainer,
      alignment: Alignment.center,
      child: Icon(
        Icons.workspace_premium,
        size: 64,
        color: theme.colorScheme.primary,
      ),
    );
  }
}
