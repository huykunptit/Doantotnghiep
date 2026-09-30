import 'package:eript_lms/app/theme/app_theme.dart';
import 'package:eript_lms/core/theme/app_semantic_colors.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Theme without Google Fonts (tests must not hit the network).
ThemeData testTheme(Brightness b) {
  final light = b == Brightness.light;
  return AppTheme.build(
    light ? AppTheme.lightScheme : AppTheme.darkScheme,
    light ? AppSemanticColors.light : AppSemanticColors.dark,
    baseTextTheme: ThemeData(brightness: b).textTheme,
  );
}

Future<void> pumpThemed(
  WidgetTester tester,
  Widget child, {
  Brightness brightness = Brightness.light,
  double textScale = 1.0,
  Size size = const Size(360, 640),
}) async {
  tester.view.physicalSize = size * tester.view.devicePixelRatio;
  addTearDown(tester.view.resetPhysicalSize);
  await tester.pumpWidget(
    MaterialApp(
      theme: testTheme(brightness),
      builder: (context, c) => MediaQuery(
        data: MediaQuery.of(
          context,
        ).copyWith(textScaler: TextScaler.linear(textScale)),
        child: c!,
      ),
      home: Scaffold(body: SafeArea(child: child)),
    ),
  );
}

/// Runs [body] once in light and once in dark.
void testBothModes(
  String name,
  Future<void> Function(WidgetTester tester, Brightness mode) body,
) {
  for (final mode in Brightness.values) {
    testWidgets('$name (${mode.name})', (t) => body(t, mode));
  }
}
