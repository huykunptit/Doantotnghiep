import 'package:flutter/material.dart';

import 'app_colors.dart';

/// Fixed brand surfaces that look the same in light and dark mode: hero
/// gradients with white content on top (home banner, profile header, ID card).
/// Use only where the content on top is `AppBrand.onHero*`; everywhere else use
/// `context.cs` / `context.sem`.
class AppBrand {
  AppBrand._();

  static const Color heroStart = AppColors.primary600;
  static const Color heroEnd = AppColors.primary400;
  static const Color heroDeepStart = AppColors.primary900;
  static const Color heroDeepEnd = AppColors.primary800;

  static const List<Color> heroColors = [heroStart, heroEnd];
  static const List<Color> heroDeepColors = [heroDeepStart, heroDeepEnd];

  static const Color onHero = Colors.white;
  static const Color onHeroMuted = Colors.white70;

  /// Near-black used behind logos / splash.
  static const Color ink = AppColors.brandBlack;
}
