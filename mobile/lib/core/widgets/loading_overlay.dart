import 'package:flutter/material.dart';

import 'app_loader.dart';

/// Full-bleed overlay with branded loader while async work is in progress.
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
    return Container(
      color: backgroundColor ?? Colors.black.withValues(alpha: 0.42),
      alignment: Alignment.center,
      child: AppLoader(
        size: 84,
        message: message,
        color: indicatorColor ?? Colors.white,
      ),
    );
  }
}
