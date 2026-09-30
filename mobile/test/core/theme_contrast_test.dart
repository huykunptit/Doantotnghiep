import 'package:eript_lms/app/theme/app_theme.dart';
import 'package:eript_lms/core/theme/app_semantic_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

double _lum(Color c) {
  double ch(double v) =>
      v <= 0.03928 ? v / 12.92 : _pow((v + 0.055) / 1.055, 2.4);
  return 0.2126 * ch(c.r) + 0.7152 * ch(c.g) + 0.0722 * ch(c.b);
}

double _pow(double b, double e) {
  // exp(e * ln b) without dart:math import noise
  return _exp(e * _ln(b));
}

double _ln(double x) {
  // series via atanh: ln x = 2 * atanh((x-1)/(x+1))
  final y = (x - 1) / (x + 1);
  var sum = 0.0, term = y;
  for (var i = 1; i < 200; i += 2) {
    sum += term / i;
    term *= y * y;
  }
  return 2 * sum;
}

double _exp(double x) {
  var sum = 1.0, term = 1.0;
  for (var i = 1; i < 60; i++) {
    term *= x / i;
    sum += term;
  }
  return sum;
}

/// Contrast ratio of [fg] over [bg]; a translucent [bg] is flattened on [base].
double contrast(Color fg, Color bg, {Color? base}) {
  var b = bg;
  if (b.a < 1 && base != null) b = Color.alphaBlend(b, base);
  final l1 = _lum(fg), l2 = _lum(b);
  final hi = l1 > l2 ? l1 : l2, lo = l1 > l2 ? l2 : l1;
  return (hi + 0.05) / (lo + 0.05);
}

void main() {
  for (final entry in {
    'light': (AppTheme.lightScheme, AppSemanticColors.light),
    'dark': (AppTheme.darkScheme, AppSemanticColors.dark),
  }.entries) {
    final cs = entry.value.$1;
    final sem = entry.value.$2;

    group('${entry.key} contrast', () {
      void expectAa(
        String name,
        Color fg,
        Color bg, {
        double min = 4.5,
        Color? base,
      }) {
        test(name, () {
          final r = contrast(fg, bg, base: base);
          expect(
            r,
            greaterThanOrEqualTo(min),
            reason: '$name = ${r.toStringAsFixed(2)}',
          );
        });
      }

      expectAa('onSurface / surface', cs.onSurface, cs.surface);
      expectAa('onSurface / card', cs.onSurface, cs.surfaceContainerLowest);
      expectAa('onSurfaceVariant / surface', cs.onSurfaceVariant, cs.surface);
      expectAa(
        'onSurfaceVariant / card',
        cs.onSurfaceVariant,
        cs.surfaceContainerLowest,
      );
      expectAa('onPrimary / primary', cs.onPrimary, cs.primary);
      expectAa('primary / surface (links)', cs.primary, cs.surface);
      expectAa(
        'onPrimaryContainer / primaryContainer',
        cs.onPrimaryContainer,
        cs.primaryContainer,
      );
      expectAa(
        'onTertiaryContainer / tertiaryContainer',
        cs.onTertiaryContainer,
        cs.tertiaryContainer,
      );
      expectAa('onTertiary / tertiary', cs.onTertiary, cs.tertiary);
      expectAa('tertiary / surface', cs.tertiary, cs.surface);
      expectAa('error / surface', cs.error, cs.surface);
      expectAa('onError / error', cs.onError, cs.error);
      for (final t in {
        'success': (sem.successFg, sem.successBg),
        'warning': (sem.warningFg, sem.warningBg),
        'danger': (sem.dangerFg, sem.dangerBg),
        'info': (sem.infoFg, sem.infoBg),
      }.entries) {
        expectAa(
          '${t.key}Fg / ${t.key}Bg',
          t.value.$1,
          t.value.$2,
          base: cs.surfaceContainerLowest,
        );
      }
      // Non-text UI parts (icons, borders) need 3:1.
      expectAa('outline / surface (UI 3:1)', cs.outline, cs.surface, min: 3.0);
      expectAa(
        'primary / card (UI 3:1)',
        cs.primary,
        cs.surfaceContainerLowest,
        min: 3.0,
      );
    });
  }
}
