import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../providers/dashboard_provider.dart';
import '../../data/repositories/dashboard_repository.dart';
import '../../../../core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import '../widgets/attendance_history_tab.dart';
import '../widgets/attendance_scanner_tab.dart';
import '../widgets/attendance_success_dialog.dart';

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

      final res = await ref
          .read(dashboardRepositoryProvider)
          .checkIn(
            qrToken: token,
            latitude: position.latitude,
            longitude: position.longitude,
            deviceInfo: 'ERIPT LMS Mobile',
          );

      if (!mounted) return;
      ref.invalidate(studentAttendanceHistoryProvider);
      _tokenController.clear();
      showAttendanceSuccessDialog(
        context,
        message: res.message,
        attendance: res.attendance,
      );
    } catch (e) {
      if (!mounted) return;
      _showErrorSnackBar(friendlyErrorMessage(e));
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
      SnackBar(content: Text(message), backgroundColor: context.cs.error),
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
          AttendanceScannerTab(
            scanner: _scannerController,
            tokenController: _tokenController,
            isSubmitting: _isSubmitting,
            statusHint: _statusHint,
            onDetect: _onDetect,
            onSubmitToken: _submitCheckIn,
          ),
          AttendanceHistoryTab(
            history: ref.watch(studentAttendanceHistoryProvider),
            onRefresh: () async =>
                ref.invalidate(studentAttendanceHistoryProvider),
          ),
        ],
      ),
    );
  }
}
