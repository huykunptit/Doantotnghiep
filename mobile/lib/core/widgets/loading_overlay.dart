import 'package:flutter/material.dart';

/// A simple full-bleed overlay showing a spinner (and optional message),
/// meant to be stacked on top of content while an async operation such as
/// payment processing or video initialization is in progress.
class LoadingOverlay extends StatelessWidget {
  const LoadingOverlay({
    super.key,
    this.message,
    this.backgroundColor,
    this.indicatorColor,
  });

  final String? message;
  final Color? backgroundColor;
  final Color? indicatorColor;

  @override
  Widget build(BuildContext context) {
    final fgColor = indicatorColor ?? Colors.white;

    return Container(
      color: backgroundColor ?? Colors.black.withValues(alpha: 0.35),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircularProgressIndicator(color: fgColor),
          if (message != null) ...[
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Text(
                message!,
                style: TextStyle(color: fgColor, fontWeight: FontWeight.w600),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
