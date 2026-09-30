import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Switches between "Của tôi" (/my-courses) and "Khám phá" (/catalog).
/// Both routes highlight the same bottom-nav tab ("Học tập").
class LearningSegmentSwitch extends StatelessWidget {
  const LearningSegmentSwitch({super.key, required this.mine});

  final bool mine;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
      child: SizedBox(
        width: double.infinity,
        child: SegmentedButton<bool>(
          showSelectedIcon: false,
          segments: const [
            ButtonSegment(
              value: true,
              label: Text('Của tôi'),
              icon: Icon(Icons.school_outlined),
            ),
            ButtonSegment(
              value: false,
              label: Text('Khám phá'),
              icon: Icon(Icons.explore_outlined),
            ),
          ],
          selected: {mine},
          onSelectionChanged: (s) {
            final toMine = s.first;
            if (toMine != mine) context.go(toMine ? '/my-courses' : '/catalog');
          },
        ),
      ),
    );
  }
}
