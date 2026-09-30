import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/widgets/app_filled_button.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import 'attendance_corner_painter.dart';

/// "Quét mã QR" tab: camera preview with overlay, torch/camera switch, and a
/// manual token field. Camera/submit state is owned by the parent.
/// The dark scrim over the camera is intentional (media surface).
class AttendanceScannerTab extends StatelessWidget {
  const AttendanceScannerTab({
    super.key,
    required this.scanner,
    required this.tokenController,
    required this.isSubmitting,
    required this.statusHint,
    required this.onDetect,
    required this.onSubmitToken,
  });

  final MobileScannerController scanner;
  final TextEditingController tokenController;
  final bool isSubmitting;
  final String? statusHint;
  final void Function(BarcodeCapture capture) onDetect;
  final void Function(String rawToken) onSubmitToken;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return SingleChildScrollView(
      padding: AppSpacing.p20,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Quét mã QR tại lớp học / workshop',
            style: context.tt.titleMedium,
            textAlign: TextAlign.center,
          ),
          AppSpacing.h4,
          Text(
            'Cần camera + GPS. Chỉ điểm danh được khi bạn đứng trong bán kính 15m quanh vị trí phiên học.',
            style: context.tt.bodySmall?.copyWith(color: cs.onSurfaceVariant),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h20,
          AspectRatio(
            aspectRatio: 1,
            child: ClipRRect(
              borderRadius: AppRadius.r2Xl,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  MobileScanner(controller: scanner, onDetect: onDetect),
                  IgnorePointer(
                    child: CustomPaint(
                      painter: AttendanceCornerPainter(cs.primary),
                    ),
                  ),
                  if (isSubmitting)
                    ColoredBox(
                      color: Colors.black45,
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const CircularProgressIndicator(
                              color: Colors.white,
                            ),
                            AppSpacing.h12,
                            Text(
                              statusHint ?? 'Đang xử lý…',
                              style: context.tt.labelLarge?.copyWith(
                                color: Colors.white,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
          AppSpacing.h16,
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: isSubmitting ? null : scanner.toggleTorch,
                  icon: const Icon(Icons.flash_on_rounded, size: 18),
                  label: const Text('Đèn flash'),
                ),
              ),
              AppSpacing.w8,
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: isSubmitting ? null : scanner.switchCamera,
                  icon: const Icon(Icons.cameraswitch_rounded, size: 18),
                  label: const Text('Đổi camera'),
                ),
              ),
            ],
          ),
          AppSpacing.h24,
          Row(
            children: [
              const Expanded(child: Divider()),
              Padding(
                padding: AppInsets.screen,
                child: Text(
                  'Hoặc dán mã QR / token',
                  style: context.tt.bodySmall?.copyWith(
                    color: cs.onSurfaceVariant,
                  ),
                ),
              ),
              const Expanded(child: Divider()),
            ],
          ),
          AppSpacing.h16,
          TextFormField(
            controller: tokenController,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Mã QR / token điểm danh',
              hintText: 'Dán nội dung QR hoặc token điểm danh',
            ),
          ),
          AppSpacing.h12,
          AppFilledButton(
            label: 'Xác nhận điểm danh',
            loading: isSubmitting,
            expanded: true,
            onPressed: () {
              if (tokenController.text.trim().isEmpty) return;
              onSubmitToken(tokenController.text);
            },
          ),
        ],
      ),
    );
  }
}
