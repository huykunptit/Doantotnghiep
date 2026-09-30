import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:eript_lms/features/certificates/data/models/certificate_model.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';

const certificatesVerifyBaseUrl = 'https://eript-lms.io.vn/certificates/verify';

Widget certificatesCertificateBackgroundImage({
  required String url,
  required BoxFit fit,
  required Widget Function() errorBuilder,
}) {
  if (url.toLowerCase().endsWith('.svg')) {
    return SvgPicture.network(
      url,
      fit: fit,
      placeholderBuilder: (_) => errorBuilder(),
    );
  }
  return CachedNetworkImage(
    imageUrl: url,
    fit: fit,
    errorWidget: (_, _, _) => errorBuilder(),
  );
}

class CertificateCard extends StatelessWidget {
  const CertificateCard({
    super.key,
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
        color: context.cs.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.cs.outlineVariant),
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
                      ? certificatesCertificateBackgroundImage(
                          url: cert.template!.backgroundImageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: _placeholder,
                        )
                      : _placeholder(),
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.transparent,
                          Colors.black.withValues(alpha: 0.75),
                        ],
                        stops: const [0.4, 1.0],
                      ),
                    ),
                  ),
                  // Badge
                  Positioned(
                    top: 12,
                    right: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: context.sem.warning,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.workspace_premium_rounded,
                            size: 13,
                            color: Colors.white,
                          ),
                          AppSpacing.w4,
                          const Text(
                            'Chứng chỉ',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 14,
                    left: 16,
                    right: 16,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          cert.courseTitle,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            shadows: [
                              Shadow(color: Colors.black54, blurRadius: 4),
                            ],
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        AppSpacing.h4,
                        Text(
                          'Cấp ngày $issuedDateStr',
                          style: TextStyle(
                            color: context.cs.surfaceContainerLowest.withValues(
                              alpha: 0.8,
                            ),
                            fontSize: 12,
                          ),
                        ),
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
                Text(
                  'Mã chứng nhận',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontSize: 12,
                    letterSpacing: 0.4,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                AppSpacing.h8,
                Material(
                  color: isDark
                      ? context.cs.surface.withValues(alpha: 0.5)
                      : context.cs.surfaceContainerLow,
                  borderRadius: BorderRadius.circular(10),
                  child: InkWell(
                    onTap: () async {
                      await Clipboard.setData(
                        ClipboardData(text: cert.credentialId),
                      );
                      if (!context.mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: const Text('Đã sao chép mã chứng nhận'),
                          backgroundColor: context.sem.success,
                          behavior: SnackBarBehavior.floating,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(10),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              cert.credentialId,
                              style: const TextStyle(
                                fontFamily: 'monospace',
                                fontWeight: FontWeight.w700,
                                fontSize: 12,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ),
                          Icon(
                            Icons.copy_rounded,
                            size: 16,
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
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
                        label: const Text(
                          'Sao chép link',
                          style: TextStyle(fontSize: 12),
                        ),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: context.cs.primary,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          side: BorderSide(color: context.cs.primaryContainer),
                        ),
                      ),
                    ),
                    AppSpacing.w8,
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: onView,
                        icon: const Icon(Icons.visibility_rounded, size: 16),
                        label: const Text(
                          'Xem chứng chỉ',
                          style: TextStyle(fontSize: 12),
                        ),
                        style: FilledButton.styleFrom(
                          backgroundColor: context.cs.primary,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
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
          colors: AppBrand.heroColors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: const Icon(
        Icons.workspace_premium_rounded,
        size: 56,
        color: Colors.white24,
      ),
    );
  }
}
