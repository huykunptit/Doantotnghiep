import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

import 'pump_app.dart';

/// Loads real Roboto + Material Icons from the Flutter SDK cache so screenshots
/// show real glyphs instead of the test-only "Ahem" boxes. Best effort: if the
/// SDK fonts are missing the test still runs (with boxes).
Future<void> loadPreviewFonts() async {
  final root =
      Platform.environment['FLUTTER_ROOT'] ??
      'C:/Users/huyng/AppData/Local/flutter';
  final dir = '$root/bin/cache/artifacts/material_fonts';

  Future<void> load(String family, Map<String, int?> files) async {
    final loader = FontLoader(family);
    var any = false;
    for (final f in files.keys) {
      final file = File('$dir/$f');
      if (file.existsSync()) {
        final bytes = file.readAsBytesSync();
        loader.addFont(Future.value(ByteData.view(bytes.buffer)));
        any = true;
      }
    }
    if (any) await loader.load();
  }

  await load('Roboto', {
    'roboto-regular.ttf': null,
    'roboto-medium.ttf': null,
    'roboto-bold.ttf': null,
  });
  await load('MaterialIcons', {'materialicons-regular.otf': null});
}

/// Renders [child] in a phone-sized frame and writes a PNG to
/// `build/preview/<name>.png`.
Future<void> shoot(
  WidgetTester tester,
  Widget child,
  String name, {
  Brightness brightness = Brightness.light,
  Size size = const Size(360, 740),
  double textScale = 1.0,
  bool settle = true,
}) async {
  final key = GlobalKey();
  tester.view.physicalSize = size * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    RepaintBoundary(
      key: key,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: testTheme(brightness),
        builder: (context, c) => MediaQuery(
          data: MediaQuery.of(
            context,
          ).copyWith(textScaler: TextScaler.linear(textScale)),
          child: c!,
        ),
        home: child,
      ),
    ),
  );
  if (settle) {
    await tester.pump(const Duration(milliseconds: 300));
    await tester.pump(const Duration(milliseconds: 300));
  }

  await tester.runAsync(() async {
    final boundary =
        key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
    final image = await boundary.toImage(pixelRatio: 1.5);
    final data = await image.toByteData(format: ui.ImageByteFormat.png);
    final out = File('build/preview/$name.png')..createSync(recursive: true);
    out.writeAsBytesSync(data!.buffer.asUint8List());
  });
}
