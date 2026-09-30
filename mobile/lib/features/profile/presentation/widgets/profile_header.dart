import 'package:cached_network_image/cached_network_image.dart';
import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';

String profileRoleLabel(String role) {
  switch (role) {
    case 'admin':
      return 'Quản trị viên';
    case 'instructor':
      return 'Giảng viên';
    default:
      return 'Học viên';
  }
}

/// Collapsing brand header: avatar (with edit button), name and role pill.
/// White-on-teal in both themes (fixed brand surface, see AppBrand).
class ProfileHeader extends StatelessWidget {
  const ProfileHeader({
    super.key,
    required this.name,
    required this.role,
    required this.avatarUrl,
    required this.onEdit,
  });

  final String name;
  final String role;
  final String? avatarUrl;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return SliverAppBar(
      expandedHeight: 220,
      pinned: true,
      foregroundColor: AppBrand.onHero,
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: context.isDark
                  ? AppBrand.heroDeepColors
                  : AppBrand.heroColors,
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: SafeArea(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                AppSpacing.h8,
                Stack(
                  alignment: Alignment.bottomRight,
                  children: [
                    CircleAvatar(
                      radius: 44,
                      backgroundColor: AppBrand.onHero.withValues(alpha: 0.22),
                      backgroundImage: avatarUrl != null
                          ? CachedNetworkImageProvider(avatarUrl!)
                          : null,
                      child: avatarUrl == null
                          ? Text(
                              name.isNotEmpty ? name[0].toUpperCase() : 'U',
                              style: context.tt.displayLarge?.copyWith(
                                color: AppBrand.onHero,
                              ),
                            )
                          : null,
                    ),
                    Semantics(
                      button: true,
                      label: 'Sửa hồ sơ',
                      child: GestureDetector(
                        onTap: onEdit,
                        behavior: HitTestBehavior.opaque,
                        child: Container(
                          // 48dp touch target around the 30dp visual badge.
                          padding: const EdgeInsets.all(9),
                          color: Colors.transparent,
                          child: Container(
                            padding: const EdgeInsets.all(7),
                            decoration: BoxDecoration(
                              color: cs.primary,
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: AppBrand.onHero,
                                width: 2,
                              ),
                            ),
                            child: Icon(
                              Icons.edit_rounded,
                              size: 14,
                              color: cs.onPrimary,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
                AppSpacing.h4,
                Text(
                  name,
                  style: context.tt.titleLarge?.copyWith(
                    color: AppBrand.onHero,
                  ),
                ),
                AppSpacing.h8,
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: AppBrand.onHero,
                    borderRadius: AppRadius.rFull,
                  ),
                  child: Text(
                    profileRoleLabel(role),
                    style: context.tt.labelMedium?.copyWith(
                      color: AppBrand.heroStart,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
