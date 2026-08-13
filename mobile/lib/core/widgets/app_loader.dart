import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../constants/branding.dart';
import '../theme/app_colors.dart';

/// Branded loading indicator — pulsing logo + soft orbit ring.
class AppLoader extends StatefulWidget {
  const AppLoader({
    super.key,
    this.size = 72,
    this.message,
    this.compact = false,
    this.color,
  });

  final double size;
  final String? message;
  final bool compact;
  final Color? color;

  @override
  State<AppLoader> createState() => _AppLoaderState();
}

class _AppLoaderState extends State<AppLoader> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final accent = widget.color ?? AppColors.primary600;
    final logoBox = widget.compact ? widget.size * 0.72 : widget.size;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: widget.size,
          height: widget.size,
          child: AnimatedBuilder(
            animation: _controller,
            builder: (context, child) {
              final t = _controller.value;
              final pulse = 0.94 + (math.sin(t * math.pi * 2) * 0.06);
              return Stack(
                alignment: Alignment.center,
                children: [
                  Transform.rotate(
                    angle: t * math.pi * 2,
                    child: CustomPaint(
                      size: Size.square(widget.size),
                      painter: _OrbitPainter(
                        progress: t,
                        color: accent,
                      ),
                    ),
                  ),
                  Transform.scale(
                    scale: pulse,
                    child: child,
                  ),
                ],
              );
            },
            child: Container(
              width: logoBox * 0.68,
              height: logoBox * 0.68,
              decoration: BoxDecoration(
                color: AppColors.brandInk,
                borderRadius: BorderRadius.circular(logoBox * 0.22),
                border: Border.all(
                  color: accent.withValues(alpha: 0.28),
                ),
                boxShadow: [
                  BoxShadow(
                    color: accent.withValues(alpha: 0.18),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              clipBehavior: Clip.antiAlias,
              padding: EdgeInsets.all(logoBox * 0.1),
              child: Image.asset(
                Branding.logoAsset,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Icon(
                  Icons.school_rounded,
                  color: accent,
                  size: logoBox * 0.28,
                ),
              ),
            ),
          ),
        ),
        if (widget.message != null) ...[
          const SizedBox(height: 14),
          Text(
            widget.message!,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: accent.withValues(alpha: 0.9),
              fontWeight: FontWeight.w600,
              fontSize: widget.compact ? 12 : 13,
            ),
          ),
        ],
      ],
    );
  }
}

class _OrbitPainter extends CustomPainter {
  _OrbitPainter({required this.progress, required this.color});

  final double progress;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = size.shortestSide / 2 - 2;
    final track = Paint()
      ..color = color.withValues(alpha: 0.14)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.4;
    final arc = Paint()
      ..color = color.withValues(alpha: 0.85)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.6
      ..strokeCap = StrokeCap.round;

    canvas.drawCircle(center, radius, track);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -math.pi / 2,
      math.pi * 1.35,
      false,
      arc,
    );

    final sweep = progress * math.pi * 2;
    final dot = Offset(
      center.dx + radius * math.cos(sweep - math.pi / 2),
      center.dy + radius * math.sin(sweep - math.pi / 2),
    );
    canvas.drawCircle(dot, 3.2, Paint()..color = color);
  }

  @override
  bool shouldRepaint(covariant _OrbitPainter oldDelegate) =>
      oldDelegate.progress != progress || oldDelegate.color != color;
}
