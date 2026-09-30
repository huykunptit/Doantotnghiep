import 'package:flutter/material.dart';

/// Status colors that adapt to light/dark. Each status has a solid color
/// (icons, dots), a soft background and a foreground for text on that
/// background. Access via `context.sem`.
@immutable
class AppSemanticColors extends ThemeExtension<AppSemanticColors> {
  const AppSemanticColors({
    required this.success,
    required this.successBg,
    required this.successFg,
    required this.warning,
    required this.warningBg,
    required this.warningFg,
    required this.danger,
    required this.dangerBg,
    required this.dangerFg,
    required this.info,
    required this.infoBg,
    required this.infoFg,
    required this.series,
  });

  final Color success;
  final Color successBg;
  final Color successFg;
  final Color warning;
  final Color warningBg;
  final Color warningFg;
  final Color danger;
  final Color dangerBg;
  final Color dangerFg;
  final Color info;
  final Color infoBg;
  final Color infoFg;

  /// Categorical colors for charts / grouped data. Always pair with a label.
  final List<Color> series;

  static const AppSemanticColors light = AppSemanticColors(
    success: Color(0xFF16A34A),
    successBg: Color(0xFFDCFCE7),
    successFg: Color(0xFF166534),
    warning: Color(0xFFD97706),
    warningBg: Color(0xFFFEF3C7),
    warningFg: Color(0xFF92400E),
    danger: Color(0xFFDC2626),
    dangerBg: Color(0xFFFEE2E2),
    dangerFg: Color(0xFFB91C1C),
    info: Color(0xFF2563EB),
    infoBg: Color(0xFFDBEAFE),
    infoFg: Color(0xFF1D4ED8),
    series: [
      Color(0xFF0F766E),
      Color(0xFF2563EB),
      Color(0xFFD97706),
      Color(0xFF7C3AED),
      Color(0xFFDB2777),
    ],
  );

  static const AppSemanticColors dark = AppSemanticColors(
    success: Color(0xFF4ADE80),
    successBg: Color(0x294ADE80),
    successFg: Color(0xFF4ADE80),
    warning: Color(0xFFFBBF24),
    warningBg: Color(0x29FBBF24),
    warningFg: Color(0xFFFBBF24),
    danger: Color(0xFFF87171),
    dangerBg: Color(0x29F87171),
    dangerFg: Color(0xFFF87171),
    info: Color(0xFF60A5FA),
    infoBg: Color(0x2960A5FA),
    infoFg: Color(0xFF60A5FA),
    series: [
      Color(0xFF2DD4BF),
      Color(0xFF60A5FA),
      Color(0xFFFBBF24),
      Color(0xFFA78BFA),
      Color(0xFFF472B6),
    ],
  );

  @override
  AppSemanticColors copyWith({
    Color? success,
    Color? successBg,
    Color? successFg,
    Color? warning,
    Color? warningBg,
    Color? warningFg,
    Color? danger,
    Color? dangerBg,
    Color? dangerFg,
    Color? info,
    Color? infoBg,
    Color? infoFg,
    List<Color>? series,
  }) {
    return AppSemanticColors(
      success: success ?? this.success,
      successBg: successBg ?? this.successBg,
      successFg: successFg ?? this.successFg,
      warning: warning ?? this.warning,
      warningBg: warningBg ?? this.warningBg,
      warningFg: warningFg ?? this.warningFg,
      danger: danger ?? this.danger,
      dangerBg: dangerBg ?? this.dangerBg,
      dangerFg: dangerFg ?? this.dangerFg,
      info: info ?? this.info,
      infoBg: infoBg ?? this.infoBg,
      infoFg: infoFg ?? this.infoFg,
      series: series ?? this.series,
    );
  }

  @override
  AppSemanticColors lerp(ThemeExtension<AppSemanticColors>? other, double t) {
    if (other is! AppSemanticColors) return this;
    Color l(Color a, Color b) => Color.lerp(a, b, t)!;
    return AppSemanticColors(
      success: l(success, other.success),
      successBg: l(successBg, other.successBg),
      successFg: l(successFg, other.successFg),
      warning: l(warning, other.warning),
      warningBg: l(warningBg, other.warningBg),
      warningFg: l(warningFg, other.warningFg),
      danger: l(danger, other.danger),
      dangerBg: l(dangerBg, other.dangerBg),
      dangerFg: l(dangerFg, other.dangerFg),
      info: l(info, other.info),
      infoBg: l(infoBg, other.infoBg),
      infoFg: l(infoFg, other.infoFg),
      series: t < 0.5 ? series : other.series,
    );
  }
}
