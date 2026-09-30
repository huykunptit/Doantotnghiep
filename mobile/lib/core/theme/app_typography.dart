import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Be Vietnam Pro for headings, Inter for body/labels. No size below 12sp.
/// Prefer `Theme.of(context).textTheme` in widgets; these getters feed
/// the TextTheme built in `app/theme/app_theme.dart`.
class AppTypography {
  AppTypography._();

  static TextStyle get display => GoogleFonts.beVietnamPro(
    fontSize: 28,
    fontWeight: FontWeight.w700,
    height: 1.2,
  );

  static TextStyle get h1 => GoogleFonts.beVietnamPro(
    fontSize: 24,
    fontWeight: FontWeight.w700,
    height: 1.25,
  );

  static TextStyle get h2 => GoogleFonts.beVietnamPro(
    fontSize: 20,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  static TextStyle get h3 => GoogleFonts.beVietnamPro(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.35,
  );

  static TextStyle get h4 => GoogleFonts.beVietnamPro(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    height: 1.4,
  );

  static TextStyle get h5 => GoogleFonts.beVietnamPro(
    fontSize: 14,
    fontWeight: FontWeight.w600,
    height: 1.4,
  );

  static TextStyle get bodyLarge =>
      GoogleFonts.inter(fontSize: 16, fontWeight: FontWeight.w400, height: 1.5);

  static TextStyle get bodyMedium =>
      GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w400, height: 1.5);

  static TextStyle get bodySmall =>
      GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w400, height: 1.4);

  static TextStyle get button =>
      GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w600, height: 1.2);

  static TextStyle get badge =>
      GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600, height: 1.2);

  static TextStyle get caption =>
      GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w500, height: 1.3);

  /// Monospaced style reserved for voucher/redeem codes where character
  /// alignment matters (e.g. `AAAA-1111`).
  static TextStyle get mono => GoogleFonts.robotoMono(
    fontSize: 13,
    fontWeight: FontWeight.bold,
    letterSpacing: 1.5,
  );

  static TextTheme get textTheme => TextTheme(
    displayLarge: display,
    headlineLarge: h1,
    headlineMedium: h2,
    titleLarge: h3,
    titleMedium: h4,
    titleSmall: h5,
    bodyLarge: bodyLarge,
    bodyMedium: bodyMedium,
    bodySmall: bodySmall,
    labelLarge: button,
    labelMedium: badge,
    labelSmall: caption,
  );
}
