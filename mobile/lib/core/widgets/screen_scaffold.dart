import 'package:flutter/material.dart';

import '../theme/app_spacing.dart';

/// Standard screen frame: SafeArea, consistent gutter, optional pull-to-refresh.
/// [body] should be scrollable when [onRefresh] is set.
class ScreenScaffold extends StatelessWidget {
  const ScreenScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.onRefresh,
    this.floatingActionButton,
    this.padded = false,
  });

  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Future<void> Function()? onRefresh;
  final Widget? floatingActionButton;

  /// Wrap [body] in the horizontal page gutter.
  final bool padded;

  @override
  Widget build(BuildContext context) {
    Widget content = padded
        ? Padding(padding: AppInsets.screen, child: body)
        : body;
    if (onRefresh != null) {
      content = RefreshIndicator(onRefresh: onRefresh!, child: content);
    }
    return Scaffold(
      appBar: AppBar(title: Text(title), actions: actions),
      body: SafeArea(top: false, child: content),
      floatingActionButton: floatingActionButton,
    );
  }
}
