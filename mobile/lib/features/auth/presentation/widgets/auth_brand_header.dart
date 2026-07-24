import 'package:flutter/material.dart';
import '../../../../core/constants/branding.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class AuthBrandHeader extends StatelessWidget {
  const AuthBrandHeader({
    super.key,
    this.subtitle,
    this.compact = false,
    this.showWordmark = true,
  });

  final String? subtitle;
  final bool compact;
  final bool showWordmark;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final logoSize = compact ? 72.0 : 112.0;

    return Column(
      children: [
        Container(
          width: logoSize,
          height: logoSize,
          decoration: BoxDecoration(
            color: AppColors.brandBlack,
            borderRadius: BorderRadius.circular(compact ? 18 : 28),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary400.withValues(alpha: 0.28),
                blurRadius: 28,
                offset: const Offset(0, 10),
              ),
            ],
            border: Border.all(
              color: AppColors.primary200.withValues(alpha: 0.35),
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Padding(
            padding: EdgeInsets.all(compact ? 8 : 12),
            child: Image.asset(
              Branding.logoAsset,
              fit: BoxFit.contain,
              errorBuilder: (_, _, _) => Icon(
                Icons.park_rounded,
                size: logoSize * 0.45,
                color: AppColors.secondary400,
              ),
            ),
          ),
        ),
        if (showWordmark) ...[
          AppSpacing.h16,
          Text(
            Branding.name,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
              letterSpacing: -0.6,
              color: theme.colorScheme.onSurface,
            ),
          ),
        ],
        if (subtitle != null) ...[
          AppSpacing.h8,
          Text(
            subtitle!,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ],
    );
  }
}

class AuthBackgroundScaffold extends StatelessWidget {
  const AuthBackgroundScaffold({
    super.key,
    required this.child,
    this.appBar,
  });

  final Widget child;
  final PreferredSizeWidget? appBar;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: appBar,
      body: Stack(
        fit: StackFit.expand,
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              color: isDark ? AppColors.darkBg : AppColors.neutral50,
              image: isDark
                  ? null
                  : const DecorationImage(
                      image: AssetImage(Branding.bodyBgAsset),
                      fit: BoxFit.cover,
                      opacity: 0.55,
                    ),
            ),
          ),
          if (!isDark)
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    AppColors.primary50.withValues(alpha: 0.55),
                    AppColors.neutral50.withValues(alpha: 0.92),
                  ],
                ),
              ),
            ),
          SafeArea(child: child),
        ],
      ),
    );
  }
}
