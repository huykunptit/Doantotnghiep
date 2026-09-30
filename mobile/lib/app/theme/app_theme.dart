import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_semantic_colors.dart';
import '../../core/theme/app_spacing.dart';
import '../../core/theme/app_typography.dart';

class AppTheme {
  AppTheme._();

  static const ColorScheme lightScheme = ColorScheme(
    brightness: Brightness.light,
    primary: AppColors.primary400,
    onPrimary: AppColors.neutral0,
    primaryContainer: AppColors.primary50,
    onPrimaryContainer: AppColors.primary800,
    secondary: AppColors.secondary400,
    onSecondary: AppColors.neutral0,
    secondaryContainer: AppColors.secondary50,
    onSecondaryContainer: AppColors.secondary800,
    // accent600 (not 400): white text on accent400 is only ~3.9:1.
    tertiary: AppColors.accent600,
    onTertiary: AppColors.neutral0,
    tertiaryContainer: AppColors.accent50,
    onTertiaryContainer: AppColors.accent600,
    error: Color(0xFFDC2626),
    onError: AppColors.neutral0,
    surface: AppColors.neutral50,
    onSurface: AppColors.neutral900,
    onSurfaceVariant: AppColors.neutral600,
    surfaceContainerLowest: AppColors.neutral0,
    surfaceContainerLow: AppColors.neutral100,
    surfaceContainer: AppColors.neutral100,
    surfaceContainerHigh: AppColors.neutral200,
    surfaceContainerHighest: AppColors.neutral200,
    outline: AppColors.secondary400,
    outlineVariant: AppColors.neutral200,
    scrim: Colors.black,
  );

  static const ColorScheme darkScheme = ColorScheme(
    brightness: Brightness.dark,
    primary: AppColors.darkPrimary,
    onPrimary: AppColors.darkOnPrimary,
    primaryContainer: AppColors.darkPrimaryContainer,
    onPrimaryContainer: AppColors.primary100,
    secondary: AppColors.neutral400,
    onSecondary: AppColors.neutral900,
    secondaryContainer: AppColors.darkSurfaceHigh,
    onSecondaryContainer: AppColors.darkTextPrimary,
    tertiary: AppColors.darkAccent,
    onTertiary: AppColors.darkTertiaryContainer,
    tertiaryContainer: AppColors.darkTertiaryContainer,
    onTertiaryContainer: AppColors.accent100,
    error: AppColors.darkError,
    onError: AppColors.darkOnError,
    surface: AppColors.darkBg,
    onSurface: AppColors.darkTextPrimary,
    onSurfaceVariant: AppColors.darkTextSecondary,
    surfaceContainerLowest: AppColors.darkSurface,
    surfaceContainerLow: AppColors.darkSurfaceLow,
    surfaceContainer: AppColors.darkSurface,
    surfaceContainerHigh: AppColors.darkSurfaceHigh,
    surfaceContainerHighest: AppColors.darkSurfaceHigh,
    outline: AppColors.darkTextMuted,
    outlineVariant: AppColors.darkBorder,
    scrim: Colors.black,
  );

  static ThemeData get light => build(lightScheme, AppSemanticColors.light);
  static ThemeData get dark => build(darkScheme, AppSemanticColors.dark);

  /// Builds the app theme. [baseTextTheme] exists so tests can avoid loading
  /// Google Fonts over the network; production code leaves it null.
  static ThemeData build(
    ColorScheme cs,
    AppSemanticColors sem, {
    TextTheme? baseTextTheme,
  }) {
    final textTheme = (baseTextTheme ?? AppTypography.textTheme).apply(
      bodyColor: cs.onSurface,
      displayColor: cs.onSurface,
    );

    OutlineInputBorder inputBorder(Color color, [double width = 1]) =>
        OutlineInputBorder(
          borderRadius: AppRadius.rLg,
          borderSide: BorderSide(color: color, width: width),
        );

    final roundedButtonShape = RoundedRectangleBorder(
      borderRadius: AppRadius.rLg,
    );
    const minButtonSize = Size(64, 48);
    const buttonPadding = EdgeInsets.symmetric(horizontal: 20, vertical: 12);

    return ThemeData(
      useMaterial3: true,
      brightness: cs.brightness,
      colorScheme: cs,
      extensions: [sem],
      scaffoldBackgroundColor: cs.surface,
      textTheme: textTheme,
      splashFactory: InkSparkle.splashFactory,
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: {
          TargetPlatform.android: FadeForwardsPageTransitionsBuilder(),
          TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
        },
      ),
      cardTheme: CardThemeData(
        color: cs.surfaceContainerLowest,
        elevation: 0,
        shadowColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.rXl,
          side: BorderSide(color: cs.outlineVariant),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: cs.surfaceContainerLowest,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 14,
        ),
        border: inputBorder(cs.outlineVariant),
        enabledBorder: inputBorder(cs.outlineVariant),
        focusedBorder: inputBorder(cs.primary, 2),
        errorBorder: inputBorder(cs.error),
        focusedErrorBorder: inputBorder(cs.error, 2),
        labelStyle: textTheme.bodyMedium?.copyWith(color: cs.onSurfaceVariant),
        hintStyle: textTheme.bodyMedium?.copyWith(color: cs.outline),
        errorStyle: textTheme.bodySmall?.copyWith(color: cs.error),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: cs.surface,
        foregroundColor: cs.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        titleTextStyle: textTheme.titleLarge,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 68,
        backgroundColor: cs.surfaceContainerLowest,
        indicatorColor: cs.primaryContainer,
        surfaceTintColor: Colors.transparent,
        shadowColor: Colors.transparent,
        elevation: 0,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return textTheme.labelMedium?.copyWith(
            color: selected ? cs.primary : cs.onSurfaceVariant,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            size: 24,
            color: selected ? cs.primary : cs.onSurfaceVariant,
          );
        }),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: cs.surfaceContainerHigh,
        selectedColor: cs.primaryContainer,
        side: BorderSide.none,
        labelStyle: textTheme.labelLarge?.copyWith(color: cs.onSurface),
        secondaryLabelStyle: textTheme.labelLarge?.copyWith(
          color: cs.onPrimaryContainer,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 8),
        shape: const StadiumBorder(),
      ),
      dividerTheme: DividerThemeData(
        color: cs.outlineVariant,
        thickness: 1,
        space: 1,
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: cs.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: cs.surfaceContainerLowest,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        showDragHandle: true,
        dragHandleColor: cs.outlineVariant,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.rSheet),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: cs.onSurface,
        contentTextStyle: textTheme.bodyMedium?.copyWith(color: cs.surface),
        actionTextColor: cs.primaryContainer,
        elevation: 0,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.rLg),
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: cs.primary,
        linearTrackColor: cs.surfaceContainerHigh,
        circularTrackColor: cs.surfaceContainerHigh,
        linearMinHeight: 8,
        borderRadius: AppRadius.rFull,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: cs.primary,
          foregroundColor: cs.onPrimary,
          disabledBackgroundColor: cs.onSurface.withValues(alpha: 0.12),
          disabledForegroundColor: cs.onSurface.withValues(alpha: 0.38),
          minimumSize: minButtonSize,
          padding: buttonPadding,
          shape: roundedButtonShape,
          textStyle: textTheme.labelLarge,
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: cs.primary,
          side: BorderSide(color: cs.primary, width: 1.5),
          minimumSize: minButtonSize,
          padding: buttonPadding,
          shape: roundedButtonShape,
          textStyle: textTheme.labelLarge,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: cs.primary,
          minimumSize: const Size(48, 48),
          shape: roundedButtonShape,
          textStyle: textTheme.labelLarge,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize: const Size(48, 48),
          foregroundColor: cs.onSurface,
        ),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: cs.onSurfaceVariant,
        textColor: cs.onSurface,
        minVerticalPadding: 8,
        contentPadding: AppInsets.screen,
      ),
    );
  }
}
