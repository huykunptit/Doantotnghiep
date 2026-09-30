import 'package:flutter/material.dart';
import 'app_semantic_colors.dart';

/// Short accessors for theme data. Widgets in `features/` should read colors
/// and text styles through these instead of using literals or `AppColors`.
extension ThemeContext on BuildContext {
  ColorScheme get cs => Theme.of(this).colorScheme;
  TextTheme get tt => Theme.of(this).textTheme;
  AppSemanticColors get sem => Theme.of(this).extension<AppSemanticColors>()!;
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
}
