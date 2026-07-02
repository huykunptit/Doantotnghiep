import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class VerifyEmailPromptPage extends ConsumerStatefulWidget {
  const VerifyEmailPromptPage({super.key, required this.email});
  final String email;
  static const routeName = '/verify-email-prompt';

  @override
  ConsumerState<VerifyEmailPromptPage> createState() => _VerifyEmailPromptPageState();
}

class _VerifyEmailPromptPageState extends ConsumerState<VerifyEmailPromptPage> {
  bool _isLoading = false;
  bool _resent = false;

  Future<void> _resend() async {
    setState(() { _isLoading = true; _resent = false; });
    try {
      await ref.read(authNotifierProvider.notifier).resendVerificationEmail(email: widget.email);
      if (mounted) setState(() => _resent = true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      backgroundColor: theme.colorScheme.surface,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Icon
                  Center(
                    child: Container(
                      width: 88, height: 88,
                      decoration: BoxDecoration(
                        color: AppColors.primary50,
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.primary100, width: 2),
                      ),
                      child: const Icon(Icons.mark_email_unread_rounded, size: 44, color: AppColors.primary600),
                    ),
                  ),
                  AppSpacing.h24,
                  Text('Xác nhận email',
                      style: theme.textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w800, letterSpacing: -0.5),
                      textAlign: TextAlign.center),
                  AppSpacing.h12,
                  Text('Chúng tôi đã gửi liên kết xác nhận đến',
                      style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant),
                      textAlign: TextAlign.center),
                  AppSpacing.h4,
                  Text(widget.email,
                      style: theme.textTheme.bodyLarge?.copyWith(
                          fontWeight: FontWeight.w700, color: AppColors.primary600),
                      textAlign: TextAlign.center),
                  AppSpacing.h12,
                  Text('Vui lòng kiểm tra hộp thư và nhấp vào liên kết để kích hoạt tài khoản.',
                      style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant, height: 1.6),
                      textAlign: TextAlign.center),
                  AppSpacing.h32,

                  if (_resent) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      decoration: BoxDecoration(
                        color: AppColors.primary50,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.primary100),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.check_circle_rounded, size: 18, color: AppColors.primary600),
                          AppSpacing.w8,
                          Text('Email đã được gửi lại!',
                              style: TextStyle(color: AppColors.primary600, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ),
                    AppSpacing.h16,
                  ],

                  FilledButton.icon(
                    onPressed: _isLoading ? null : _resend,
                    icon: _isLoading
                        ? const SizedBox(height: 18, width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send_rounded, size: 18),
                    label: Text(_isLoading ? 'Đang gửi...' : 'Gửi lại email xác nhận'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary400,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  AppSpacing.h12,
                  OutlinedButton(
                    onPressed: () => context.go('/login'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      side: BorderSide(color: theme.colorScheme.outlineVariant),
                    ),
                    child: Text('Quay lại đăng nhập',
                        style: TextStyle(fontWeight: FontWeight.w600, color: theme.colorScheme.onSurface)),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
