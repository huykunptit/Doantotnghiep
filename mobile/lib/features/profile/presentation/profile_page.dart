import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/profile_provider.dart';
import '../../auth/providers/auth_provider.dart';
import '../../../app/theme/theme_provider.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../core/error/friendly_error.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authNotifierProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      body: authState.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(friendlyErrorMessage(e))),
        data: (user) {
          if (user == null) {
            WidgetsBinding.instance.addPostFrameCallback((_) => context.go('/login'));
            return const SizedBox.shrink();
          }
          return CustomScrollView(
            slivers: [
              // Header with avatar
              SliverAppBar(
                expandedHeight: 220,
                pinned: true,
                flexibleSpace: FlexibleSpaceBar(
                  background: Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: isDark
                            ? [AppColors.primary900, AppColors.primary800]
                            : [AppColors.primary600, AppColors.primary400],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                    child: SafeArea(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const SizedBox(height: 8),
                          Stack(
                            alignment: Alignment.bottomRight,
                            children: [
                              CircleAvatar(
                                radius: 44,
                                backgroundColor: Colors.white.withValues(alpha: 0.22),
                                backgroundImage: user.avatar != null
                                    ? CachedNetworkImageProvider(user.avatar!)
                                    : null,
                                child: user.avatar == null
                                    ? Text(
                                        user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                                        style: const TextStyle(
                                          fontSize: 36, fontWeight: FontWeight.w800, color: Colors.white),
                                      )
                                    : null,
                              ),
                              GestureDetector(
                                onTap: () => _showEditDialog(context, ref, user.name, user.phone),
                                child: Container(
                                  padding: const EdgeInsets.all(7),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary400,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 2),
                                    boxShadow: [
                                      BoxShadow(
                                        color: Colors.black.withValues(alpha: 0.18),
                                        blurRadius: 6,
                                        offset: const Offset(0, 2),
                                      ),
                                    ],
                                  ),
                                  child: const Icon(Icons.edit_rounded, size: 14, color: Colors.white),
                                ),
                              ),
                            ],
                          ),
                          AppSpacing.h8,
                          Text(user.name,
                              style: const TextStyle(
                                  fontSize: 18, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: -0.3)),
                          AppSpacing.h8,
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(_roleLabel(user.role),
                                style: const TextStyle(fontSize: 12, color: AppColors.primary600, fontWeight: FontWeight.w700)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

              SliverPadding(
                padding: const EdgeInsets.all(16),
                sliver: SliverList(
                  delegate: SliverChildListDelegate([
                    // Info card
                    _SectionCard(
                      children: [
                        _InfoRow(icon: Icons.mail_outline_rounded, label: 'Email', value: user.email),
                        if (user.studentCode != null)
                          _InfoRow(icon: Icons.numbers_rounded, label: 'Mã sinh viên', value: user.studentCode!),
                        if (user.phone != null)
                          _InfoRow(icon: Icons.phone_outlined, label: 'Điện thoại', value: user.phone!),
                      ],
                    ),
                    AppSpacing.h12,

                    // Academic section
                    _SectionCard(
                      children: [
                        _NavTile(
                          icon: Icons.badge_outlined,
                          iconColor: AppColors.primary600,
                          title: 'Thẻ sinh viên',
                          onTap: () => context.push('/id-card'),
                        ),
                        _NavTile(
                          icon: Icons.receipt_long_outlined,
                          iconColor: AppColors.secondary600,
                          title: 'Bảng điểm học tập',
                          onTap: () => context.push('/transcript'),
                        ),
                        _NavTile(
                          icon: Icons.calendar_month_outlined,
                          iconColor: AppColors.primary600,
                          title: 'Thời khóa biểu',
                          onTap: () => context.push('/timetable'),
                        ),
                        _NavTile(
                          icon: Icons.payments_outlined,
                          iconColor: Colors.teal.shade700,
                          title: 'Học phí',
                          onTap: () => context.push('/tuition'),
                        ),
                        _NavTile(
                          icon: Icons.workspace_premium_outlined,
                          iconColor: Colors.amber.shade700,
                          title: 'Chứng chỉ của tôi',
                          onTap: () => context.push('/certificates'),
                        ),
                        _NavTile(
                          icon: Icons.auto_graph_outlined,
                          iconColor: AppColors.primary400,
                          title: 'Cố vấn học tập AI',
                          onTap: () => context.push('/study-advisor'),
                        ),
                        _NavTile(
                          icon: Icons.psychology_outlined,
                          iconColor: AppColors.accent600,
                          title: 'AI Career',
                          onTap: () => context.push('/career'),
                          isLast: true,
                        ),
                      ],
                    ),
                    AppSpacing.h12,

                    // Settings section
                    _SectionCard(
                      children: [
                        _NavTile(
                          icon: Icons.lock_outline_rounded,
                          iconColor: AppColors.neutral600,
                          title: 'Đổi mật khẩu',
                          onTap: () => _showChangePasswordDialog(context, ref),
                        ),
                        _NavTile(
                          icon: Icons.palette_outlined,
                          iconColor: AppColors.primary400,
                          title: 'Giao diện hiển thị',
                          subtitle: _themeModeLabel(ref.watch(themeNotifierProvider)),
                          onTap: () => _showThemeDialog(context, ref),
                          isLast: true,
                        ),
                      ],
                    ),
                    AppSpacing.h20,

                    // Logout
                    OutlinedButton.icon(
                      onPressed: () async {
                        final confirm = await _confirmLogout(context);
                        if (confirm == true && context.mounted) {
                          await ref.read(authNotifierProvider.notifier).logout();
                          if (context.mounted) context.go('/login');
                        }
                      },
                      icon: const Icon(Icons.logout_rounded, color: AppColors.error, size: 18),
                      label: const Text('Đăng xuất', style: TextStyle(color: AppColors.error, fontWeight: FontWeight.w700)),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        side: const BorderSide(color: AppColors.error),
                      ),
                    ),
                    AppSpacing.h8,
                  ]),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  String _roleLabel(String role) {
    switch (role) {
      case 'admin': return 'Quản trị viên';
      case 'instructor': return 'Giảng viên';
      default: return 'Học viên';
    }
  }

  String _themeModeLabel(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light: return 'Giao diện sáng';
      case ThemeMode.dark: return 'Giao diện tối';
      default: return 'Theo hệ thống';
    }
  }

  Future<bool?> _confirmLogout(BuildContext context) {
    return showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Đăng xuất', style: TextStyle(fontWeight: FontWeight.w700)),
        content: const Text('Bạn có chắc muốn đăng xuất không?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Huỷ')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Đăng xuất'),
          ),
        ],
      ),
    );
  }

  void _showEditDialog(BuildContext context, WidgetRef ref, String currentName, String? currentPhone) {
    final nameCtrl = TextEditingController(text: currentName);
    final phoneCtrl = TextEditingController(text: currentPhone ?? '');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Sửa hồ sơ', style: TextStyle(fontWeight: FontWeight.w700)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: nameCtrl,
                decoration: InputDecoration(labelText: 'Họ tên',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)))),
            AppSpacing.h12,
            TextField(controller: phoneCtrl, keyboardType: TextInputType.phone,
                decoration: InputDecoration(labelText: 'Số điện thoại',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)))),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Huỷ')),
          FilledButton(
            onPressed: () async {
              Navigator.pop(ctx);
              await ref.read(profileNotifierProvider.notifier).updateProfile(
                    name: nameCtrl.text.trim(),
                    phone: phoneCtrl.text.trim().isEmpty ? null : phoneCtrl.text.trim(),
                  );
            },
            child: const Text('Lưu'),
          ),
        ],
      ),
    );
  }

  void _showChangePasswordDialog(BuildContext context, WidgetRef ref) {
    final currentCtrl = TextEditingController();
    final newCtrl = TextEditingController();
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Đổi mật khẩu', style: TextStyle(fontWeight: FontWeight.w700)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: currentCtrl, obscureText: true,
                decoration: InputDecoration(labelText: 'Mật khẩu hiện tại',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)))),
            AppSpacing.h12,
            TextField(controller: newCtrl, obscureText: true,
                decoration: InputDecoration(labelText: 'Mật khẩu mới',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)))),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Huỷ')),
          FilledButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final error = await ref.read(profileNotifierProvider.notifier).changePassword(
                    currentPassword: currentCtrl.text, newPassword: newCtrl.text,
                  );
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                content: Text(error ?? 'Đổi mật khẩu thành công'),
                backgroundColor: error == null ? AppColors.success : AppColors.error,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ));
            },
            child: const Text('Xác nhận'),
          ),
        ],
      ),
    );
  }

  void _showThemeDialog(BuildContext context, WidgetRef ref) {
    final currentMode = ref.read(themeNotifierProvider);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Giao diện hiển thị', style: TextStyle(fontWeight: FontWeight.w700)),
        content: RadioGroup<ThemeMode>(
          groupValue: currentMode,
          onChanged: (mode) {
            if (mode != null) {
              ref.read(themeNotifierProvider.notifier).setThemeMode(mode);
              Navigator.pop(ctx);
            }
          },
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              RadioListTile<ThemeMode>(title: const Text('Sáng'), value: ThemeMode.light),
              RadioListTile<ThemeMode>(title: const Text('Tối (Rừng về đêm)'), value: ThemeMode.dark),
              RadioListTile<ThemeMode>(title: const Text('Theo mặc định hệ thống'), value: ThemeMode.system),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurface : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isDark ? AppColors.darkBorder : AppColors.neutral200),
        boxShadow: isDark ? [] : [
          BoxShadow(color: AppColors.neutral800.withValues(alpha: 0.05),
              blurRadius: 10, offset: const Offset(0, 2)),
        ],
      ),
      child: Column(children: children),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(color: AppColors.neutral100, borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, size: 18, color: AppColors.neutral600),
          ),
          AppSpacing.w12,
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant, fontSize: 11)),
                Text(value, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  const _NavTile({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.isLast = false,
  });
  final IconData icon;
  final Color iconColor;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        InkWell(
          borderRadius: isLast
              ? const BorderRadius.vertical(bottom: Radius.circular(16))
              : BorderRadius.zero,
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: iconColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, size: 18, color: iconColor),
                ),
                AppSpacing.w12,
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                      if (subtitle != null)
                        Text(subtitle!, style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant, fontSize: 11)),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded, size: 18, color: theme.colorScheme.onSurfaceVariant),
              ],
            ),
          ),
        ),
        if (!isLast) Divider(height: 1, indent: 64, color: theme.colorScheme.outlineVariant.withValues(alpha: 0.5)),
      ],
    );
  }
}
