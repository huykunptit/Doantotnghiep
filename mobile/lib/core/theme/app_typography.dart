import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTypography {
  AppTypography._();

  static TextStyle get display => GoogleFonts.roboto(
        fontSize: 32,
        fontWeight: FontWeight.w600,
        height: 1.15,
      );

  static TextStyle get h1 => GoogleFonts.roboto(
        fontSize: 26,
        fontWeight: FontWeight.w600,
        height: 1.2,
      );

  static TextStyle get h2 => GoogleFonts.roboto(
        fontSize: 22,
        fontWeight: FontWeight.w600,
        height: 1.25,
      );

  static TextStyle get h3 => GoogleFonts.roboto(
        fontSize: 18,
        fontWeight: FontWeight.w500,
        height: 1.3,
      );

  static TextStyle get h4 => GoogleFonts.roboto(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        height: 1.4,
      );

  static TextStyle get bodyLarge => GoogleFonts.roboto(
        fontSize: 16,
        fontWeight: FontWeight.w400,
        height: 1.7,
      );

  static TextStyle get bodyMedium => GoogleFonts.roboto(
        fontSize: 14,
        fontWeight: FontWeight.w400,
        height: 1.6,
      );

  static TextStyle get bodySmall => GoogleFonts.roboto(
        fontSize: 12,
        fontWeight: FontWeight.w400,
        height: 1.5,
      );

  static TextStyle get caption => GoogleFonts.roboto(
        fontSize: 11,
        fontWeight: FontWeight.w500,
        height: 1.4,
      );

  /// Monospaced style reserved for voucher/redeem codes where character
  /// alignment matters (e.g. `AAAA-1111`).
  static TextStyle get mono => GoogleFonts.robotoMono(
        fontSize: 13,
        fontWeight: FontWeight.bold,
        letterSpacing: 1.5,
      );
}
