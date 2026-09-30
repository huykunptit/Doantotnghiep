import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../theme/app_brand.dart';
import '../theme/app_spacing.dart';
import '../theme/theme_context.dart';
import 'status_badge.dart';

/// Small rounded chip with an icon and a short label (rating, count, price…).
class InfoChip extends StatelessWidget {
  const InfoChip({
    super.key,
    required this.icon,
    required this.label,
    this.iconColor,
  });

  final IconData icon;
  final String label;
  final Color? iconColor;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: cs.surfaceContainerLowest,
        borderRadius: AppRadius.rFull,
        border: Border.all(color: cs.outlineVariant),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: iconColor ?? cs.onSurfaceVariant),
          AppSpacing.w4,
          Text(label, style: context.tt.labelMedium),
        ],
      ),
    );
  }
}

/// Section heading with an optional count pill ("Nội dung khoá học · 12 bài").
class SectionTitle extends StatelessWidget {
  const SectionTitle({super.key, required this.title, this.badge});

  final String title;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Flexible(
          child: Semantics(
            header: true,
            child: Text(title, style: context.tt.titleMedium),
          ),
        ),
        if (badge != null) ...[
          AppSpacing.w8,
          StatusBadge(label: badge!, tone: StatusTone.primary),
        ],
      ],
    );
  }
}

/// Collapsing app bar with a cover image (or brand gradient fallback) and a
/// scrim so the white title stays legible. The scrim is intentionally black
/// (media surface).
class DetailHeroAppBar extends StatelessWidget {
  const DetailHeroAppBar({
    super.key,
    required this.title,
    required this.imageUrl,
    required this.fallbackIcon,
  });

  final String title;
  final String? imageUrl;
  final IconData fallbackIcon;

  @override
  Widget build(BuildContext context) {
    final fallback = _Fallback(icon: fallbackIcon);
    return SliverAppBar(
      expandedHeight: 220,
      pinned: true,
      foregroundColor: AppBrand.onHero,
      flexibleSpace: FlexibleSpaceBar(
        titlePadding: const EdgeInsets.fromLTRB(56, 0, 16, 14),
        title: Text(
          title,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: context.tt.titleSmall?.copyWith(
            color: AppBrand.onHero,
            shadows: const [Shadow(color: Colors.black87, blurRadius: 12)],
          ),
        ),
        background: Stack(
          fit: StackFit.expand,
          children: [
            imageUrl != null
                ? CachedNetworkImage(
                    imageUrl: imageUrl!,
                    fit: BoxFit.cover,
                    errorWidget: (_, _, _) => fallback,
                  )
                : fallback,
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Color(0x66000000),
                    Color(0x00000000),
                    Color(0xCC000000),
                  ],
                  stops: [0.0, 0.38, 1.0],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: context.isDark
              ? AppBrand.heroDeepColors
              : AppBrand.heroColors,
        ),
      ),
      child: Icon(icon, size: 64, color: Colors.white30),
    );
  }
}

/// Bottom bar holding the primary action(s) of a detail screen.
class BottomActionBar extends StatelessWidget {
  const BottomActionBar({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 12),
        decoration: BoxDecoration(
          color: context.cs.surface,
          border: Border(top: BorderSide(color: context.cs.outlineVariant)),
        ),
        child: child,
      ),
    );
  }
}

/// Numbered row used for lessons / courses in a detail list.
class NumberedListTile extends StatelessWidget {
  const NumberedListTile({
    super.key,
    required this.leading,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.trailing,
    this.muted = false,
  });

  final Widget leading;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback onTap;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: cs.surfaceContainerLowest,
        borderRadius: AppRadius.rLg,
        border: Border.all(color: cs.outlineVariant),
      ),
      child: InkWell(
        borderRadius: AppRadius.rLg,
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 56),
          child: Padding(
            padding: AppSpacing.p12,
            child: Row(
              children: [
                leading,
                AppSpacing.w12,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: context.tt.titleSmall?.copyWith(
                          color: muted ? cs.onSurfaceVariant : cs.onSurface,
                        ),
                      ),
                      if (subtitle != null)
                        Text(
                          subtitle!,
                          style: context.tt.bodySmall?.copyWith(
                            color: cs.onSurfaceVariant,
                          ),
                        ),
                    ],
                  ),
                ),
                ?trailing,
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Circular badge (number / icon) for [NumberedListTile.leading].
class CircleBadge extends StatelessWidget {
  const CircleBadge({super.key, required this.child, this.active = true});

  final Widget child;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 32,
      height: 32,
      decoration: BoxDecoration(
        color: active
            ? context.cs.primaryContainer
            : context.cs.surfaceContainerLow,
        shape: BoxShape.circle,
      ),
      child: Center(child: child),
    );
  }
}

/// Floating snackbar with a tone (success / error / neutral).
void showAppSnack(
  BuildContext context,
  String message, {
  StatusTone tone = StatusTone.neutral,
}) {
  final Color? bg = switch (tone) {
    StatusTone.success => context.sem.success,
    StatusTone.danger => context.cs.error,
    _ => null,
  };
  ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(message), backgroundColor: bg));
}
