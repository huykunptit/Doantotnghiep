import 'dart:convert';
import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../../core/error/friendly_error.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../data/models/exam_precheck_model.dart';
import '../../data/repositories/quiz_repository.dart';

/// Pre-exam face gate: camera preview → detect face → verify/enroll via API.
class FaceVerificationGate extends ConsumerStatefulWidget {
  const FaceVerificationGate({
    super.key,
    required this.examId,
    required this.precheck,
    required this.onVerified,
    this.onCancel,
  });

  final int examId;
  final ExamPrecheckModel precheck;
  final VoidCallback onVerified;
  final VoidCallback? onCancel;

  @override
  ConsumerState<FaceVerificationGate> createState() =>
      _FaceVerificationGateState();
}

class _FaceVerificationGateState extends ConsumerState<FaceVerificationGate>
    with WidgetsBindingObserver {
  CameraController? _controller;
  FaceDetector? _detector;
  bool _initializing = true;
  bool _verifying = false;
  bool _verified = false;
  String? _error;
  String? _status;

  bool get _enrollMode => widget.precheck.canEnrollFace;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _detector = FaceDetector(
      options: FaceDetectorOptions(
        performanceMode: FaceDetectorMode.accurate,
        enableContours: false,
        enableLandmarks: false,
      ),
    );
    _initCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller?.dispose();
    _detector?.close();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final cam = _controller;
    if (cam == null || !cam.value.isInitialized) return;
    if (state == AppLifecycleState.inactive) {
      cam.dispose();
      _controller = null;
    } else if (state == AppLifecycleState.resumed) {
      _initCamera();
    }
  }

  Future<void> _initCamera() async {
    setState(() {
      _initializing = true;
      _error = null;
    });

    final status = await Permission.camera.request();
    if (!status.isGranted) {
      if (!mounted) return;
      setState(() {
        _initializing = false;
        _error = 'Cần cấp quyền camera để xác thực khuôn mặt.';
      });
      return;
    }

    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) {
        throw Exception('Không tìm thấy camera trên thiết bị.');
      }
      final front = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );

      final controller = CameraController(
        front,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );
      await controller.initialize();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _initializing = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _initializing = false;
        _error = friendlyErrorMessage(e);
      });
    }
  }

  Future<void> _captureAndVerify() async {
    final cam = _controller;
    final detector = _detector;
    if (_verifying || _verified || cam == null || !cam.value.isInitialized || detector == null) {
      return;
    }

    setState(() {
      _verifying = true;
      _status = null;
      _error = null;
    });

    try {
      final shot = await cam.takePicture();
      final input = InputImage.fromFilePath(shot.path);
      final faces = await detector.processImage(input);

      if (faces.isEmpty) {
        setState(() {
          _status = 'Không phát hiện khuôn mặt. Hãy nhìn thẳng camera và thử lại.';
        });
        return;
      }
      if (faces.length > 1) {
        setState(() {
          _status = 'Phát hiện nhiều khuôn mặt. Chỉ một người trong khung hình.';
        });
        return;
      }

      final bytes = await File(shot.path).readAsBytes();
      if (bytes.length < 1000) {
        setState(() => _status = 'Ảnh chụp không hợp lệ, vui lòng thử lại.');
        return;
      }

      final dataUrl = 'data:image/jpeg;base64,${base64Encode(bytes)}';
      final result = await ref.read(quizRepositoryProvider).verifyFace(
            widget.examId,
            imageDataUrl: dataUrl,
            enroll: _enrollMode,
            faceDetected: true,
            score: _enrollMode ? 1.0 : null,
          );

      if (!mounted) return;
      if (!result.ok) {
        setState(() => _status = result.message.isNotEmpty
            ? result.message
            : 'Xác thực thất bại. Vui lòng thử lại.');
        return;
      }

      setState(() {
        _verified = true;
        _status = result.enrolled
            ? 'Đã lưu ảnh khuôn mặt và xác thực thành công.'
            : (result.message.isNotEmpty
                ? result.message
                : 'Xác thực khuôn mặt thành công.');
      });
      await Future<void>.delayed(const Duration(milliseconds: 600));
      if (mounted) widget.onVerified();
    } catch (e) {
      if (!mounted) return;
      setState(() => _status = friendlyErrorMessage(e));
    } finally {
      if (mounted) setState(() => _verifying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      appBar: AppBar(
        title: const Text('Xác thực khuôn mặt',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
        leading: widget.onCancel == null
            ? null
            : IconButton(
                icon: const Icon(Icons.close_rounded),
                onPressed: _verifying ? null : widget.onCancel,
              ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _enrollMode
                    ? 'Ảnh thẻ trên hồ sơ chưa dùng được. Nhìn thẳng camera — ảnh chụp sẽ được lưu làm ảnh khuôn mặt.'
                    : 'Kỳ thi bật giám sát. Hãy chụp khuôn mặt để xác thực trước khi vào thi.',
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                  height: 1.45,
                ),
              ),
              AppSpacing.h16,
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: ColoredBox(
                    color: Colors.black,
                    child: _buildCameraPane(theme),
                  ),
                ),
              ),
              AppSpacing.h16,
              if (_status != null)
                Text(
                  _status!,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: _verified ? AppColors.success : theme.colorScheme.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              if (_error != null) ...[
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: theme.colorScheme.error,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                AppSpacing.h8,
                OutlinedButton.icon(
                  onPressed: _initializing ? null : _initCamera,
                  icon: const Icon(Icons.refresh_rounded),
                  label: const Text('Thử lại camera'),
                ),
              ],
              AppSpacing.h12,
              FilledButton.icon(
                onPressed: (_initializing ||
                        _verifying ||
                        _verified ||
                        _controller == null ||
                        !(_controller?.value.isInitialized ?? false))
                    ? null
                    : _captureAndVerify,
                icon: _verifying
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(_enrollMode
                        ? Icons.camera_alt_rounded
                        : Icons.verified_user_rounded),
                label: Text(
                  _verifying
                      ? 'Đang xác thực…'
                      : _enrollMode
                          ? 'Chụp & lưu ảnh thẻ'
                          : 'Chụp & xác thực',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary400,
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCameraPane(ThemeData theme) {
    if (_initializing) {
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }
    final cam = _controller;
    if (cam == null || !cam.value.isInitialized) {
      return Center(
        child: Text(
          _error ?? 'Camera chưa sẵn sàng',
          style: const TextStyle(color: Colors.white70),
          textAlign: TextAlign.center,
        ),
      );
    }

    return Stack(
      fit: StackFit.expand,
      children: [
        FittedBox(
          fit: BoxFit.cover,
          child: SizedBox(
            width: cam.value.previewSize?.height ?? 480,
            height: cam.value.previewSize?.width ?? 640,
            child: CameraPreview(cam),
          ),
        ),
        if (_verified)
          ColoredBox(
            color: Colors.green.withValues(alpha: 0.28),
            child: const Center(
              child: Icon(Icons.check_circle_rounded,
                  color: Colors.white, size: 72),
            ),
          ),
        if (_verifying && !_verified)
          const ColoredBox(
            color: Colors.black45,
            child: Center(
              child: CircularProgressIndicator(color: Colors.white),
            ),
          ),
      ],
    );
  }
}
