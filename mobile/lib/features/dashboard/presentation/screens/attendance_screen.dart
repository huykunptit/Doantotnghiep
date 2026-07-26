import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../providers/dashboard_provider.dart';
import '../../data/repositories/dashboard_repository.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class AttendanceScreen extends ConsumerStatefulWidget {
  const AttendanceScreen({super.key});
  static const routeName = '/attendance';

  @override
  ConsumerState<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends ConsumerState<AttendanceScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final _tokenController = TextEditingController();
  final MobileScannerController _scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
    formats: const [BarcodeFormat.qrCode],
  );

  bool _isSubmitting = false;
  bool _scanLocked = false;
  String? _statusHint;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _tokenController.dispose();
    _scannerController.dispose();
    super.dispose();
  }

  String? _extractToken(String raw) {
    final text = raw.trim();
    if (text.isEmpty) return null;
    if (text.startsWith('{')) {
      try {
        final decoded = jsonDecode(text);
        if (decoded is Map && decoded['token'] != null) {
          return decoded['token'].toString();
        }
      } catch (_) {
        /* fall through */
      }
    }
    return text;
  }

  Future<Position> _resolvePosition() async {
    final permission = await Permission.locationWhenInUse.request();
    if (!permission.isGranted) {
      throw Exception('Cần quyền vị trí để điểm danh trong bán kính 15m.');
    }

    final enabled = await Geolocator.isLocationServiceEnabled();
    if (!enabled) {
      throw Exception('Hãy bật GPS/Location trên thiết bị.');
    }

    var geoPermission = await Geolocator.checkPermission();
    if (geoPermission == LocationPermission.denied) {
      geoPermission = await Geolocator.requestPermission();
    }
    if (geoPermission == LocationPermission.denied ||
        geoPermission == LocationPermission.deniedForever) {
      throw Exception('Không có quyền truy cập vị trí.');
    }

    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
      ),
    );
  }

  Future<void> _submitCheckIn(String rawQr) async {
    final token = _extractToken(rawQr);
    if (token == null || token.isEmpty) {
      _showErrorSnackBar('Mã QR không hợp lệ.');
      return;
    }
    if (_isSubmitting) return;

    setState(() {
      _isSubmitting = true;
      _statusHint = 'Đang lấy vị trí GPS…';
    });

    try {
      final position = await _resolvePosition();
      if (!mounted) return;
      setState(() => _statusHint = 'Đang gửi điểm danh…');

      final res = await ref.read(dashboardRepositoryProvider).checkIn(
            qrToken: token,
            latitude: position.latitude,
            longitude: position.longitude,
            deviceInfo: 'Sylva LMS Mobile',
          );

      if (!mounted) return;
      ref.invalidate(studentAttendanceHistoryProvider);
      _tokenController.clear();
      _showSuccessDialog(res.message, res.attendance);
    } catch (e) {
      if (!mounted) return;
      _showErrorSnackBar(e.toString().replaceFirst('Exception: ', ''));
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
          _statusHint = null;
          _scanLocked = false;
        });
      }
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isSubmitting || _scanLocked) return;
    final raw = capture.barcodes
        .map((b) => b.rawValue)
        .whereType<String>()
        .firstWhere((v) => v.trim().isNotEmpty, orElse: () => '');
    if (raw.isEmpty) return;
    _scanLocked = true;
    _submitCheckIn(raw);
  }

  void _showErrorSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: AppColors.error,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }

  void _showSuccessDialog(String message, dynamic attendance) {
    final session = attendance.offlineSession;
    final theme = Theme.of(context);

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                color: AppColors.primary50,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.check_circle_rounded,
                color: AppColors.primary400,
                size: 48,
              ),
            ),
            AppSpacing.h20,
            Text(
              message,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.primary600,
              ),
              textAlign: TextAlign.center,
            ),
            AppSpacing.h16,
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppColors.neutral50,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.neutral100),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _dialogDetailRow('Khóa học:', session?.courseTitle ?? '—', theme),
                  AppSpacing.h8,
                  _dialogDetailRow(
                    'Phiên học:',
                    session?.lessonTitle ?? session?.title ?? '—',
                    theme,
                  ),
                  AppSpacing.h8,
                  _dialogDetailRow('Địa điểm:', session?.location ?? '—', theme),
                  AppSpacing.h8,
                  _dialogDetailRow(
                    'Trạng thái:',
                    attendance.status == 'present' ? 'Có mặt' : 'Đi muộn',
                    theme,
                    statusColor: attendance.status == 'present'
                        ? AppColors.success
                        : AppColors.warning,
                  ),
                  if (attendance.distanceMeters != null) ...[
                    AppSpacing.h8,
                    _dialogDetailRow(
                      'Khoảng cách:',
                      '${attendance.distanceMeters}m',
                      theme,
                    ),
                  ],
                ],
              ),
            ),
            AppSpacing.h24,
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary400,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Đóng'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _dialogDetailRow(String label, String value, ThemeData theme, {Color? statusColor}) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 90,
          child: Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w600,
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: theme.textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: statusColor ?? theme.colorScheme.onSurface,
            ),
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Điểm danh QR'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Quét mã QR'),
            Tab(text: 'Lịch sử'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildScannerTab(),
          _buildHistoryTab(),
        ],
      ),
    );
  }

  Widget _buildScannerTab() {
    final theme = Theme.of(context);

    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Quét mã QR tại lớp học / workshop',
            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h4,
          Text(
            'Cần camera + GPS. Chỉ điểm danh được khi bạn đứng trong bán kính 15m quanh vị trí phiên học.',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h20,
          AspectRatio(
            aspectRatio: 1,
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  MobileScanner(
                    controller: _scannerController,
                    onDetect: _onDetect,
                  ),
                  IgnorePointer(
                    child: CustomPaint(painter: _CornerPainter()),
                  ),
                  if (_isSubmitting)
                    ColoredBox(
                      color: Colors.black45,
                      child: Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const CircularProgressIndicator(color: Colors.white),
                            AppSpacing.h12,
                            Text(
                              _statusHint ?? 'Đang xử lý…',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
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
                  onPressed: _isSubmitting ? null : () => _scannerController.toggleTorch(),
                  icon: const Icon(Icons.flash_on_rounded, size: 18),
                  label: const Text('Đèn flash'),
                ),
              ),
              AppSpacing.w8,
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _isSubmitting ? null : () => _scannerController.switchCamera(),
                  icon: const Icon(Icons.cameraswitch_rounded, size: 18),
                  label: const Text('Đổi camera'),
                ),
              ),
            ],
          ),
          AppSpacing.h24,
          const Row(
            children: [
              Expanded(child: Divider()),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 16),
                child: Text('Hoặc dán mã QR / token', style: TextStyle(fontSize: 11, color: Colors.grey)),
              ),
              Expanded(child: Divider()),
            ],
          ),
          AppSpacing.h16,
          TextFormField(
            controller: _tokenController,
            maxLines: 2,
            decoration: InputDecoration(
              hintText: 'Dán nội dung QR hoặc token điểm danh',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
          AppSpacing.h12,
          SizedBox(
            height: 48,
            child: FilledButton(
              onPressed: _isSubmitting
                  ? null
                  : () {
                      if (_tokenController.text.trim().isEmpty) return;
                      _submitCheckIn(_tokenController.text);
                    },
              style: FilledButton.styleFrom(
                backgroundColor: AppColors.primary400,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Text('Xác nhận điểm danh'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryTab() {
    final historyAsync = ref.watch(studentAttendanceHistoryProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return historyAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: AppColors.error),
              AppSpacing.h12,
              Text('Lỗi: $e', textAlign: TextAlign.center),
            ],
          ),
        ),
      ),
      data: (list) {
        if (list.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.assignment_turned_in_outlined, size: 64, color: theme.colorScheme.outline),
                AppSpacing.h16,
                Text(
                  'Chưa có lịch sử điểm danh.',
                  style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(studentAttendanceHistoryProvider),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            itemCount: list.length,
            itemBuilder: (context, index) {
              final item = list[index];
              final session = item.offlineSession;
              final isPresent = item.status == 'present';

              DateTime? checkInDate;
              if (item.checkedInAt != null) {
                checkInDate = DateTime.tryParse(item.checkedInAt!);
              }

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkSurface : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: (isPresent ? AppColors.success : AppColors.warning).withValues(alpha: 0.1),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isPresent ? Icons.check_circle_outline_rounded : Icons.pending_actions_rounded,
                        color: isPresent ? AppColors.success : AppColors.warning,
                        size: 24,
                      ),
                    ),
                    AppSpacing.w16,
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            session?.courseTitle ?? session?.title ?? '—',
                            style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.bold),
                          ),
                          AppSpacing.h4,
                          Text(
                            session?.lessonTitle ?? '—',
                            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
                          ),
                          AppSpacing.h8,
                          Row(
                            children: [
                              Icon(Icons.location_on_outlined, size: 12, color: theme.colorScheme.onSurfaceVariant),
                              AppSpacing.w4,
                              Expanded(
                                child: Text(
                                  session?.location ?? '—',
                                  style: TextStyle(fontSize: 11, color: theme.colorScheme.onSurfaceVariant),
                                ),
                              ),
                            ],
                          ),
                          if (checkInDate != null) ...[
                            AppSpacing.h4,
                            Text(
                              'Điểm danh lúc: ${checkInDate.hour}:${checkInDate.minute.toString().padLeft(2, '0')} ngày ${checkInDate.day}/${checkInDate.month}/${checkInDate.year}',
                              style: TextStyle(fontSize: 11, color: theme.colorScheme.onSurfaceVariant),
                            ),
                          ],
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: (isPresent ? AppColors.success : AppColors.warning).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        isPresent ? 'Có mặt' : 'Đi muộn',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.bold,
                          color: isPresent ? AppColors.success : AppColors.warning,
                        ),
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
        );
      },
    );
  }
}

class _CornerPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = AppColors.primary400
      ..strokeWidth = 4
      ..style = PaintingStyle.stroke;

    const inset = 28.0;
    const length = 28.0;
    final left = inset;
    final top = inset;
    final right = size.width - inset;
    final bottom = size.height - inset;

    canvas.drawPath(
      Path()
        ..moveTo(left, top + length)
        ..lineTo(left, top)
        ..lineTo(left + length, top),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(right - length, top)
        ..lineTo(right, top)
        ..lineTo(right, top + length),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(left, bottom - length)
        ..lineTo(left, bottom)
        ..lineTo(left + length, bottom),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(right - length, bottom)
        ..lineTo(right, bottom)
        ..lineTo(right, bottom - length),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
