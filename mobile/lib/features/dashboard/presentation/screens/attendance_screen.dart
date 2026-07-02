import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
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
  final _codeController = TextEditingController();
  bool _isSubmitting = false;

  // Scanning animation
  late AnimationController _scanController;
  late Animation<double> _scanAnimation;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    
    _scanController = AnimationController(
      duration: const Duration(seconds: 2),
      vsync: this,
    )..repeat(reverse: true);
    
    _scanAnimation = Tween<double>(begin: 0.0, end: 1.0).animate(_scanController);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _codeController.dispose();
    _scanController.dispose();
    super.dispose();
  }

  Future<void> _submitCheckIn(String codeStr) async {
    final sessionId = int.tryParse(codeStr.trim());
    if (sessionId == null) {
      _showErrorSnackBar('Mã phiên học phải là một số nguyên.');
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final res = await ref.read(dashboardRepositoryProvider).checkIn(
            sessionId,
            deviceInfo: 'Thiết bị di động Học viên',
          );
      
      if (!mounted) return;
      
      // Refresh history list
      ref.invalidate(studentAttendanceHistoryProvider);
      
      // Clear manual text field
      _codeController.clear();
      
      // Show success dialog
      _showSuccessDialog(res.message, res.attendance);
    } catch (e) {
      if (!mounted) return;
      _showErrorSnackBar(e.toString());
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
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
                  _dialogDetailRow('Bài học:', session?.lessonTitle ?? '—', theme),
                  AppSpacing.h8,
                  _dialogDetailRow('Địa điểm:', session?.location ?? '—', theme),
                  AppSpacing.h8,
                  _dialogDetailRow('Trạng thái:', attendance.status == 'present' ? 'Có mặt' : 'Đi muộn', theme,
                      statusColor: attendance.status == 'present' ? AppColors.success : AppColors.warning),
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
          width: 80,
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
    final isDark = theme.brightness == Brightness.dark;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Quét mã QR tại lớp học',
            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h4,
          Text(
            'Hướng camera vào mã QR được cung cấp trên màn hình lớp học',
            style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.onSurfaceVariant),
            textAlign: TextAlign.center,
          ),
          AppSpacing.h24,

          // Scanner Simulated Box
          Center(
            child: Container(
              width: 260,
              height: 260,
              decoration: BoxDecoration(
                color: isDark ? AppColors.darkSurface : Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: AppColors.primary400, width: 2),
                boxShadow: isDark
                    ? []
                    : [
                        BoxShadow(
                          color: AppColors.neutral800.withValues(alpha: 0.08),
                          blurRadius: 24,
                          offset: const Offset(0, 8),
                        ),
                      ],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(22),
                child: Stack(
                  children: [
                    // Mock background camera pattern
                    Center(
                      child: Opacity(
                        opacity: 0.1,
                        child: Icon(Icons.qr_code_scanner_rounded, size: 160, color: theme.colorScheme.onSurface),
                      ),
                    ),
                    // Laser scanning animator line
                    AnimatedBuilder(
                      animation: _scanAnimation,
                      builder: (context, child) {
                        return Positioned(
                          top: _scanAnimation.value * 250,
                          left: 10,
                          right: 10,
                          child: Container(
                            height: 3,
                            decoration: BoxDecoration(
                              color: AppColors.primary400,
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.primary400.withValues(alpha: 0.8),
                                  blurRadius: 8,
                                  spreadRadius: 2,
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                    // Corner borders overlay
                    _buildScannerCorners(),
                  ],
                ),
              ),
            ),
          ),
          
          AppSpacing.h24,

          // Quick testing list (for dev validation without QR camera)
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: isDark ? AppColors.darkSurface : AppColors.primary50.withValues(alpha: 0.3),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.primary100),
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    const Icon(Icons.bug_report_outlined, color: AppColors.primary600, size: 18),
                    AppSpacing.w8,
                    Text(
                      'Môi trường phát triển / Mô phỏng',
                      style: theme.textTheme.bodySmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary800,
                      ),
                    ),
                  ],
                ),
                AppSpacing.h12,
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: _isSubmitting ? null : () => _submitCheckIn('1'),
                        icon: const Icon(Icons.qr_code_rounded, size: 16),
                        label: const Text('Quét QR Kì 1 (Session #1)'),
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primary600,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          textStyle: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                    AppSpacing.w8,
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _isSubmitting ? null : () => _submitCheckIn('2'),
                        icon: const Icon(Icons.qr_code_rounded, size: 16),
                        label: const Text('Quét QR Kì 2 (Session #2)'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary600,
                          side: const BorderSide(color: AppColors.primary400),
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          textStyle: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          AppSpacing.h24,
          const Row(
            children: [
              Expanded(child: Divider()),
              Padding(
                padding: EdgeInsets.symmetric(horizontal: 16),
                child: Text('Hoặc nhập mã số điểm danh', style: TextStyle(fontSize: 11, color: Colors.grey)),
              ),
              Expanded(child: Divider()),
            ],
          ),
          AppSpacing.h20,

          // Manual inputs
          Row(
            children: [
              Expanded(
                child: TextFormField(
                  controller: _codeController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    hintText: 'Nhập mã phiên học (Ví dụ: 1)',
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
              AppSpacing.w12,
              SizedBox(
                height: 48,
                child: FilledButton(
                  onPressed: _isSubmitting
                      ? null
                      : () {
                          if (_codeController.text.trim().isEmpty) return;
                          _submitCheckIn(_codeController.text);
                        },
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary400,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isSubmitting
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Xác nhận'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildScannerCorners() {
    return Positioned.fill(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: CustomPaint(
          painter: _CornerPainter(),
        ),
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
                  boxShadow: isDark
                      ? []
                      : [
                          BoxShadow(
                            color: AppColors.neutral800.withValues(alpha: 0.04),
                            blurRadius: 10,
                            offset: const Offset(0, 2),
                          ),
                        ],
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
                            session?.courseTitle ?? '—',
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
                              Text(
                                session?.location ?? '—',
                                style: TextStyle(fontSize: 11, color: theme.colorScheme.onSurfaceVariant),
                              ),
                            ],
                          ),
                          if (checkInDate != null) ...[
                            AppSpacing.h4,
                            Row(
                              children: [
                                Icon(Icons.access_time_rounded, size: 12, color: theme.colorScheme.onSurfaceVariant),
                                AppSpacing.w4,
                                Text(
                                  'Điểm danh lúc: ${checkInDate.hour}:${checkInDate.minute.toString().padLeft(2, '0')} ngày ${checkInDate.day}/${checkInDate.month}/${checkInDate.year}',
                                  style: TextStyle(fontSize: 11, color: theme.colorScheme.onSurfaceVariant),
                                ),
                              ],
                            ),
                          ],
                        ],
                      ),
                    ),
                    AppSpacing.w8,
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

    const length = 20.0;

    // Top Left
    canvas.drawPath(
      Path()
        ..moveTo(0, length)
        ..lineTo(0, 0)
        ..lineTo(length, 0),
      paint,
    );

    // Top Right
    canvas.drawPath(
      Path()
        ..moveTo(size.width - length, 0)
        ..lineTo(size.width, 0)
        ..lineTo(size.width, length),
      paint,
    );

    // Bottom Left
    canvas.drawPath(
      Path()
        ..moveTo(0, size.height - length)
        ..lineTo(0, size.height)
        ..lineTo(length, size.height),
      paint,
    );

    // Bottom Right
    canvas.drawPath(
      Path()
        ..moveTo(size.width - length, size.height)
        ..lineTo(size.width, size.height)
        ..lineTo(size.width, size.height - length),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
