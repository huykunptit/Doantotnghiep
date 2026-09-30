import 'package:flutter/material.dart';
import '../../../../core/constants/branding.dart';
import '../../../../core/widgets/app_loader.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';

class SplashPage extends StatelessWidget {
  const SplashPage({super.key});

  static const routeName = '/';

  @override
  Widget build(BuildContext context) {
    // Navigation is handled by GoRouter redirect once auth settles.
    return Scaffold(
      backgroundColor: AppBrand.ink,
      body: Stack(
        fit: StackFit.expand,
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(
                center: const Alignment(0, -0.2),
                radius: 1.1,
                colors: [
                  AppBrand.heroDeepEnd.withValues(alpha: 0.55),
                  AppBrand.ink,
                ],
              ),
            ),
          ),
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const AppLoader(size: 120),
                const SizedBox(height: 22),
                Text(
                  Branding.name,
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.2,
                    color: context.cs.primaryContainer.withValues(alpha: 0.95),
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  Branding.tagline,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    letterSpacing: 0.3,
                    color: context.cs.primaryContainer.withValues(alpha: 0.72),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
