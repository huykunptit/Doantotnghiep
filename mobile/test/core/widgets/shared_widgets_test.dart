import 'package:eript_lms/core/theme/app_semantic_colors.dart';
import 'package:eript_lms/core/widgets/app_card.dart';
import 'package:eript_lms/core/widgets/app_filled_button.dart';
import 'package:eript_lms/core/widgets/app_list_tile.dart';
import 'package:eript_lms/core/widgets/app_progress_bar.dart';
import 'package:eript_lms/core/widgets/empty_state.dart';
import 'package:eript_lms/core/widgets/screen_scaffold.dart';
import 'package:eript_lms/core/widgets/section_header.dart';
import 'package:eript_lms/core/widgets/skeleton.dart';
import 'package:eript_lms/core/widgets/stat_tile.dart';
import 'package:eript_lms/core/widgets/status_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../../helpers/pump_app.dart';

void main() {
  group('AppCard', () {
    testBothModes('renders child and fires onTap', (t, mode) async {
      var taps = 0;
      await pumpThemed(
        t,
        AppCard(onTap: () => taps++, child: const Text('hello')),
        brightness: mode,
      );
      expect(find.text('hello'), findsOneWidget);
      await t.tap(find.byType(AppCard));
      expect(taps, 1);
    });

    testWidgets('uses card surface color from theme', (t) async {
      await pumpThemed(t, const AppCard(child: Text('x')));
      final box = t.widget<Container>(
        find
            .descendant(
              of: find.byType(AppCard),
              matching: find.byType(Container),
            )
            .first,
      );
      final deco = box.decoration as BoxDecoration;
      expect(
        deco.color,
        testTheme(Brightness.light).colorScheme.surfaceContainerLowest,
      );
    });
  });

  group('StatusBadge', () {
    testBothModes('shows label for every tone', (t, mode) async {
      await pumpThemed(
        t,
        Column(
          children: [
            for (final tone in StatusTone.values)
              StatusBadge(label: tone.name, tone: tone),
          ],
        ),
        brightness: mode,
      );
      for (final tone in StatusTone.values) {
        expect(find.text(tone.name), findsOneWidget);
      }
    });

    testWidgets('success tone uses semantic colors', (t) async {
      await pumpThemed(
        t,
        const StatusBadge(label: 'ok', tone: StatusTone.success),
      );
      expect(
        t.widget<Text>(find.text('ok')).style?.color,
        AppSemanticColors.light.successFg,
      );
    });

    testWidgets('dark mode uses dark semantic colors', (t) async {
      await pumpThemed(
        t,
        const StatusBadge(label: 'bad', tone: StatusTone.danger),
        brightness: Brightness.dark,
      );
      expect(
        t.widget<Text>(find.text('bad')).style?.color,
        AppSemanticColors.dark.dangerFg,
      );
    });
  });

  group('StatTile', () {
    testBothModes('shows value and label', (t, mode) async {
      await pumpThemed(
        t,
        const StatTile(label: 'Khóa học', value: '12', icon: Icons.school),
        brightness: mode,
      );
      expect(find.text('12'), findsOneWidget);
      expect(find.text('Khóa học'), findsOneWidget);
    });

    testWidgets('long label does not overflow at 1.3x text scale', (t) async {
      await pumpThemed(
        t,
        const SizedBox(
          width: 160,
          child: StatTile(
            label: 'Một nhãn rất dài rất dài rất dài',
            value: '1.234.567',
            icon: Icons.school,
          ),
        ),
        textScale: 1.3,
      );
      expect(t.takeException(), isNull);
    });
  });

  group('SectionHeader', () {
    testWidgets('action is tappable', (t) async {
      var tapped = false;
      await pumpThemed(
        t,
        SectionHeader(
          title: 'Đang học',
          actionLabel: 'Xem tất cả',
          onAction: () => tapped = true,
        ),
      );
      await t.tap(find.text('Xem tất cả'));
      expect(tapped, isTrue);
    });

    testWidgets('hides action without callback', (t) async {
      await pumpThemed(t, const SectionHeader(title: 'A', actionLabel: 'B'));
      expect(find.text('B'), findsNothing);
    });

    testWidgets('height is at least 48', (t) async {
      await pumpThemed(t, const SectionHeader(title: 'A'));
      expect(
        t.getSize(find.byType(SectionHeader)).height,
        greaterThanOrEqualTo(48),
      );
    });
  });

  group('AppListTile', () {
    testBothModes('renders parts and taps', (t, mode) async {
      var taps = 0;
      await pumpThemed(
        t,
        AppListTile(
          title: 'Bảng điểm',
          subtitle: 'HK1',
          leading: const Icon(Icons.abc),
          showChevron: true,
          onTap: () => taps++,
        ),
        brightness: mode,
      );
      expect(find.text('Bảng điểm'), findsOneWidget);
      expect(find.text('HK1'), findsOneWidget);
      expect(find.byIcon(Icons.chevron_right_rounded), findsOneWidget);
      await t.tap(find.byType(AppListTile));
      expect(taps, 1);
    });

    testWidgets('min height 56', (t) async {
      await pumpThemed(t, const AppListTile(title: 'A'));
      expect(
        t.getSize(find.byType(AppListTile)).height,
        greaterThanOrEqualTo(56),
      );
    });
  });

  group('AppProgressBar', () {
    testWidgets('shows percent and clamps value', (t) async {
      await pumpThemed(t, const AppProgressBar(value: 1.7, label: 'Tiến độ'));
      expect(find.text('100%'), findsOneWidget);
      final bar = t.widget<LinearProgressIndicator>(
        find.byType(LinearProgressIndicator),
      );
      expect(bar.value, 1.0);
    });

    testWidgets('shows fractional percent', (t) async {
      await pumpThemed(t, const AppProgressBar(value: 0.25, label: 'Bài học'));
      expect(find.text('25%'), findsOneWidget);
    });
  });

  group('AppFilledButton', () {
    testWidgets('fires onPressed', (t) async {
      var n = 0;
      await pumpThemed(t, AppFilledButton(label: 'Lưu', onPressed: () => n++));
      await t.tap(find.text('Lưu'));
      expect(n, 1);
    });

    testWidgets('loading blocks taps and shows spinner', (t) async {
      var n = 0;
      await pumpThemed(
        t,
        AppFilledButton(label: 'Lưu', loading: true, onPressed: () => n++),
      );
      expect(find.byType(CircularProgressIndicator), findsOneWidget);
      await t.tap(find.byType(FilledButton), warnIfMissed: false);
      expect(n, 0);
    });

    testWidgets('expanded fills width', (t) async {
      await pumpThemed(
        t,
        AppFilledButton(label: 'Lưu', expanded: true, onPressed: () {}),
      );
      expect(t.getSize(find.byType(FilledButton)).width, 360);
    });
  });

  group('EmptyState', () {
    testBothModes('shows content and action', (t, mode) async {
      var n = 0;
      await pumpThemed(
        t,
        EmptyState(
          icon: Icons.inbox,
          title: 'Trống',
          message: 'Chưa có dữ liệu',
          actionLabel: 'Tải lại',
          onAction: () => n++,
        ),
        brightness: mode,
      );
      expect(find.text('Trống'), findsOneWidget);
      expect(find.text('Chưa có dữ liệu'), findsOneWidget);
      await t.tap(find.text('Tải lại'));
      expect(n, 1);
    });
  });

  group('Skeleton', () {
    testWidgets('SkeletonList builds requested count', (t) async {
      await pumpThemed(t, const SkeletonList(count: 3, itemHeight: 40));
      expect(find.byType(SkeletonBox), findsNWidgets(3));
    });

    testWidgets('renders a plain box when animations are disabled', (t) async {
      t.platformDispatcher.accessibilityFeaturesTestValue =
          const FakeAccessibilityFeatures(disableAnimations: true);
      addTearDown(t.platformDispatcher.clearAccessibilityFeaturesTestValue);
      await pumpThemed(t, const SkeletonBox(height: 20));
      expect(find.byType(SkeletonBox), findsOneWidget);
    });
  });

  group('ScreenScaffold', () {
    testWidgets('shows title, body, actions', (t) async {
      await t.pumpWidget(
        MaterialApp(
          theme: testTheme(Brightness.light),
          home: const ScreenScaffold(
            title: 'Tiêu đề',
            actions: [Icon(Icons.search)],
            body: Text('nội dung'),
          ),
        ),
      );
      expect(find.text('Tiêu đề'), findsOneWidget);
      expect(find.text('nội dung'), findsOneWidget);
      expect(find.byIcon(Icons.search), findsOneWidget);
    });

    testWidgets('pull to refresh calls onRefresh', (t) async {
      var n = 0;
      await t.pumpWidget(
        MaterialApp(
          theme: testTheme(Brightness.light),
          home: ScreenScaffold(
            title: 'T',
            onRefresh: () async => n++,
            body: ListView(
              children: const [SizedBox(height: 900, child: Text('x'))],
            ),
          ),
        ),
      );
      await t.fling(find.text('x'), const Offset(0, 400), 1000);
      await t.pumpAndSettle();
      expect(n, 1);
    });
  });
}
