import 'dart:math';
import 'package:flutter/material.dart';

// ── Custom Painter to render gorgeous Circular Score Ring ────────────

class ExamWorkspaceScoreRingPainter extends CustomPainter {
  final double percentage;
  final Color strokeColor;
  final Color trackColor;

  ExamWorkspaceScoreRingPainter({
    required this.percentage,
    required this.strokeColor,
    required this.trackColor,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = (size.width - 12) / 2;

    // 1. Draw track ring
    final trackPaint = Paint()
      ..color = trackColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = 10;
    canvas.drawCircle(center, radius, trackPaint);

    // 2. Draw fill arc
    final fillPaint = Paint()
      ..color = strokeColor
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = 10;

    // We rotate -pi/2 to start from top center
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -pi / 2,
      2 * pi * percentage,
      false,
      fillPaint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => true;
}
