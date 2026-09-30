import 'package:flutter/material.dart';
import 'package:eript_lms/features/certificates/data/models/certificate_model.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/features/certificates/presentation/widgets/certificate_card.dart';

class CertificateViewDialog extends StatelessWidget {
  const CertificateViewDialog({
    super.key,
    required this.cert,
    this.studentName,
  });

  final UserCertificateModel cert;
  final String? studentName;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    String issuedDateStr = '';
    try {
      final parsed = DateTime.parse(cert.issuedAt);
      issuedDateStr = '${parsed.day}/${parsed.month}/${parsed.year}';
    } catch (_) {
      issuedDateStr = cert.issuedAt;
    }

    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      backgroundColor: context.cs.surfaceContainerLowest,
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
                        ? certificatesCertificateBackgroundImage(
                            url: cert.template!.backgroundImageUrl!,
                            fit: BoxFit.cover,
                            errorBuilder: () => Container(
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: AppBrand.heroColors,
                                  begin: Alignment.topLeft,
                                  end: Alignment.bottomRight,
                                ),
                              ),
                            ),
                          )
                        : Container(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: AppBrand.heroColors,
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                              ),
                            ),
                          ),
                    Container(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            Colors.black.withValues(alpha: 0.78),
                          ],
                          stops: const [0.45, 1.0],
                        ),
                      ),
                    ),
                    Positioned(
                      left: 18,
                      right: 18,
                      bottom: 16,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (studentName != null && studentName!.isNotEmpty)
                            Text(
                              studentName!,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                shadows: [
                                  Shadow(color: Colors.black54, blurRadius: 4),
                                ],
                              ),
                            ),
                          AppSpacing.h4,
                          Text(
                            cert.courseTitle,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
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
                              color: context.cs.surfaceContainerLowest
                                  .withValues(alpha: 0.8),
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
            AppSpacing.h16,
            Text(
              'Mã chứng nhận',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
                fontSize: 12,
                letterSpacing: 0.4,
                fontWeight: FontWeight.w700,
              ),
            ),
            AppSpacing.h4,
            SelectableText(
              cert.credentialId,
              style: const TextStyle(
                fontFamily: 'monospace',
                fontWeight: FontWeight.w700,
                fontSize: 13,
                letterSpacing: 0.4,
              ),
            ),
            AppSpacing.h16,
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              style: FilledButton.styleFrom(
                backgroundColor: context.cs.primary,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: const Text('Đóng'),
            ),
          ],
        ),
      ),
    );
  }
}
