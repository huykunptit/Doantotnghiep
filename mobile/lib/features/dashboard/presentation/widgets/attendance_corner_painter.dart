import 'package:flutter/material.dart';

class AttendanceCornerPainter extends CustomPainter {
  AttendanceCornerPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = 4
      ..style = PaintingStyle.stroke;

    const inset = 28.0;
    const length = 28.0;
    final left = inset;
    final top = inset;
    final right = size.width - inset;
    final bottom = size.height - inset;

    canvas.drawPath(
      Path()
        ..moveTo(left, top + length)
        ..lineTo(left, top)
        ..lineTo(left + length, top),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(right - length, top)
        ..lineTo(right, top)
        ..lineTo(right, top + length),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(left, bottom - length)
        ..lineTo(left, bottom)
        ..lineTo(left + length, bottom),
      paint,
    );
    canvas.drawPath(
      Path()
        ..moveTo(right - length, bottom)
        ..lineTo(right, bottom)
        ..lineTo(right, bottom - length),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
