import 'package:flutter/material.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_typography.dart';
import '../../core/theme/app_spacing.dart';

class AppTheme {
  AppTheme._();

  static ThemeData get light {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: const ColorScheme(
        brightness: Brightness.light,
        primary: AppColors.primary400,
        onPrimary: Colors.white,
        primaryContainer: AppColors.primary50,
        onPrimaryContainer: AppColors.primary800,
        secondary: AppColors.secondary400,
        onSecondary: Colors.white,
        secondaryContainer: AppColors.secondary50,
        onSecondaryContainer: AppColors.secondary800,
        error: AppColors.error,
        onError: Colors.white,
        surface: AppColors.neutral50,
        onSurface: AppColors.neutral800,
        surfaceContainerLow: AppColors.neutral100,
        surfaceContainer: AppColors.neutral0,
        outline: AppColors.neutral200,
        outlineVariant: AppColors.neutral400,
      ),
      scaffoldBackgroundColor: AppColors.neutral50,
      cardTheme: CardThemeData(
        color: AppColors.neutral0,
        elevation: 2,
        shadowColor: Colors.black.withValues(alpha: 0.06),
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.rLg,
          side: const BorderSide(color: AppColors.neutral200, width: 1),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.neutral0,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.neutral200, width: 1),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.neutral200, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.primary400, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.error, width: 1),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.error, width: 2),
        ),
        labelStyle: const TextStyle(color: AppColors.neutral600),
        hintStyle: const TextStyle(color: AppColors.neutral400),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.neutral50,
        foregroundColor: AppColors.neutral800,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: AppTypography.h2.copyWith(color: AppColors.neutral800),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.neutral0,
        selectedItemColor: AppColors.primary400,
        unselectedItemColor: AppColors.neutral600,
        elevation: 8,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.neutral0,
        indicatorColor: AppColors.primary50,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return AppTypography.caption.copyWith(color: AppColors.primary800, fontWeight: FontWeight.bold);
          }
          return AppTypography.caption.copyWith(color: AppColors.neutral600);
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: AppColors.primary800, size: 24);
          }
          return const IconThemeData(color: AppColors.neutral600, size: 24);
        }),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primary400,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.rMd),
          textStyle: AppTypography.h4.copyWith(color: Colors.white, fontWeight: FontWeight.bold),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primary400,
          side: const BorderSide(color: AppColors.primary400, width: 1.5),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.rMd),
          textStyle: AppTypography.h4.copyWith(color: AppColors.primary400, fontWeight: FontWeight.bold),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary400,
          textStyle: AppTypography.h4.copyWith(color: AppColors.primary400, fontWeight: FontWeight.w600),
        ),
      ),
      textTheme: TextTheme(
        displayLarge: AppTypography.display,
        headlineLarge: AppTypography.h1,
        headlineMedium: AppTypography.h2,
        titleLarge: AppTypography.h3,
        titleMedium: AppTypography.h4,
        bodyLarge: AppTypography.bodyLarge,
        bodyMedium: AppTypography.bodyMedium,
        bodySmall: AppTypography.bodySmall,
        labelSmall: AppTypography.caption,
      ),
    );
  }

  static ThemeData get dark {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: const ColorScheme(
        brightness: Brightness.dark,
        primary: AppColors.darkPrimary,
        onPrimary: AppColors.neutral900,
        primaryContainer: AppColors.darkSurface,
        onPrimaryContainer: AppColors.darkTextPrimary,
        secondary: AppColors.secondary400,
        onSecondary: Colors.white,
        secondaryContainer: AppColors.darkSurface,
        onSecondaryContainer: AppColors.darkTextSecondary,
        error: AppColors.error,
        onError: Colors.white,
        surface: AppColors.darkBg,
        onSurface: AppColors.darkTextPrimary,
        surfaceContainerLow: AppColors.darkSurface,
        surfaceContainer: AppColors.darkSurface,
        outline: AppColors.darkBorder,
        outlineVariant: AppColors.darkTextMuted,
      ),
      scaffoldBackgroundColor: AppColors.darkBg,
      cardTheme: CardThemeData(
        color: AppColors.darkSurface,
        elevation: 2,
        shadowColor: Colors.black.withValues(alpha: 0.2),
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.rLg,
          side: const BorderSide(color: AppColors.darkBorder, width: 1),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.darkSurface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        border: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.darkBorder, width: 1),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.darkBorder, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.darkPrimary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.error, width: 1),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: AppRadius.rMd,
          borderSide: const BorderSide(color: AppColors.error, width: 2),
        ),
        labelStyle: const TextStyle(color: AppColors.darkTextSecondary),
        hintStyle: const TextStyle(color: AppColors.darkTextMuted),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.darkBg,
        foregroundColor: AppColors.darkTextPrimary,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: AppTypography.h2.copyWith(color: AppColors.darkTextPrimary),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.darkSurface,
        selectedItemColor: AppColors.darkPrimary,
        unselectedItemColor: AppColors.darkTextSecondary,
        elevation: 8,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.darkSurface,
        indicatorColor: AppColors.darkBorder,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return AppTypography.caption.copyWith(color: AppColors.darkTextPrimary, fontWeight: FontWeight.bold);
          }
          return AppTypography.caption.copyWith(color: AppColors.darkTextSecondary);
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: AppColors.darkTextPrimary, size: 24);
          }
          return const IconThemeData(color: AppColors.darkTextSecondary, size: 24);
        }),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.darkPrimary,
          foregroundColor: AppColors.neutral900,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.rMd),
          textStyle: AppTypography.h4.copyWith(color: AppColors.neutral900, fontWeight: FontWeight.bold),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.darkPrimary,
          side: const BorderSide(color: AppColors.darkPrimary, width: 1.5),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: AppRadius.rMd),
          textStyle: AppTypography.h4.copyWith(color: AppColors.darkPrimary, fontWeight: FontWeight.bold),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.darkPrimary,
          textStyle: AppTypography.h4.copyWith(color: AppColors.darkPrimary, fontWeight: FontWeight.w600),
        ),
      ),
      textTheme: TextTheme(
        displayLarge: AppTypography.display,
        headlineLarge: AppTypography.h1,
        headlineMedium: AppTypography.h2,
        titleLarge: AppTypography.h3,
        titleMedium: AppTypography.h4,
        bodyLarge: AppTypography.bodyLarge,
        bodyMedium: AppTypography.bodyMedium,
        bodySmall: AppTypography.bodySmall,
        labelSmall: AppTypography.caption,
      ),
    );
  }
}
