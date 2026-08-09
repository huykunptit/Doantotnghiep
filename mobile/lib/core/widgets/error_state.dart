import 'package:flutter/material.dart';
import '../error/friendly_error.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Standard "something went wrong" panel for AsyncValue.error branches.
/// Always shows friendlyErrorMessage(error) — never the raw exception.
class ErrorStateWidget extends StatelessWidget {
  const ErrorStateWidget({
    super.key,
    required this.error,
    this.onRetry,
    this.icon = Icons.error_outline_rounded,
  });

  final Object error;
  final VoidCallback? onRetry;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: AppColors.error),
            AppSpacing.h12,
            Text(
              friendlyErrorMessage(error),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            if (onRetry != null) ...[
              AppSpacing.h16,
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Thử lại'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
