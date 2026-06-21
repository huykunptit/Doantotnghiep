import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/auth_provider.dart';
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
  String? _successMessage;

  Future<void> _resend() async {
    setState(() {
      _isLoading = true;
      _successMessage = null;
    });

    try {
      final msg = await ref.read(authNotifierProvider.notifier).resendVerificationEmail(
            email: widget.email,
          );
      setState(() => _successMessage = msg);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: Colors.red),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(
                    Icons.mark_email_read_outlined,
                    size: 72,
                    color: Colors.blue,
                  ),
                  AppSpacing.h24,
                  Text(
                    'Xác nhận Email',
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: theme.colorScheme.primary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  AppSpacing.h16,
                  Text(
                    'Chúng tôi đã gửi một liên kết xác nhận đến địa chỉ:',
                    style: theme.textTheme.bodyMedium,
                    textAlign: TextAlign.center,
                  ),
                  AppSpacing.h8,
                  Text(
                    widget.email,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: theme.colorScheme.primary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  AppSpacing.h16,
                  Text(
                    'Vui lòng nhấp vào liên kết trong email để xác minh tài khoản trước khi đăng nhập.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  AppSpacing.h32,
                  if (_successMessage != null) ...[
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: Colors.green.shade200),
                      ),
                      child: Text(
                        _successMessage!,
                        style: TextStyle(color: Colors.green.shade800),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    AppSpacing.h24,
                  ],
                  FilledButton.icon(
                    onPressed: _isLoading ? null : _resend,
                    icon: _isLoading
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.send),
                    label: const Text('Gửi lại email xác nhận'),
                  ),
                  AppSpacing.h16,
                  OutlinedButton(
                    onPressed: () => context.go('/login'),
                    child: const Text('Quay lại Đăng nhập'),
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
