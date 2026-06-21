import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/profile_provider.dart';
import '../../auth/providers/auth_provider.dart';
import '../../../app/theme/theme_provider.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authNotifierProvider);
    final user = authState.valueOrNull;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Hồ sơ'),
        centerTitle: false,
        actions: [
          if (user != null)
            TextButton.icon(
              onPressed: () => _showEditDialog(context, ref, user.name, user.phone),
              icon: const Icon(Icons.edit_outlined, size: 18),
              label: const Text('Sửa'),
            ),
        ],
      ),
      body: authState.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (user) {
          if (user == null) {
            WidgetsBinding.instance.addPostFrameCallback((_) => context.go('/login'));
            return const SizedBox.shrink();
          }
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Center(
                child: CircleAvatar(
                  radius: 48,
                  backgroundImage: user.avatar != null
                      ? CachedNetworkImageProvider(user.avatar!)
                      : null,
                  child: user.avatar == null
                      ? Text(
                          user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
                          style: const TextStyle(fontSize: 36),
                        )
                      : null,
                ),
              ),
              const SizedBox(height: 16),
              Center(
                child: Text(
                  user.name,
                  style: theme.textTheme.headlineSmall
                      ?.copyWith(fontWeight: FontWeight.bold),
                ),
              ),
              Center(
                child: Text(
                  user.email,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Card(
                child: Column(
                  children: [
                    _InfoTile(
                      icon: Icons.badge_outlined,
                      label: 'Vai trò',
                      value: _roleLabel(user.role),
                    ),
                    if (user.studentCode != null)
                      _InfoTile(
                        icon: Icons.numbers,
                        label: 'Mã sinh viên',
                        value: user.studentCode!,
                      ),
                    if (user.phone != null)
                      _InfoTile(
                        icon: Icons.phone_outlined,
                        label: 'Số điện thoại',
                        value: user.phone!,
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Column(
                  children: [
                    ListTile(
                      leading: const Icon(Icons.receipt_long_outlined),
                      title: const Text('Bảng điểm học tập'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push('/transcript'),
                    ),
                    const Divider(height: 1, indent: 16, endIndent: 16),
                    ListTile(
                      leading: const Icon(Icons.workspace_premium_outlined),
                      title: const Text('Chứng chỉ của tôi'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push('/certificates'),
                    ),
                    const Divider(height: 1, indent: 16, endIndent: 16),
                    ListTile(
                      leading: const Icon(Icons.psychology_outlined),
                      title: const Text('Tư vấn nghề nghiệp AI'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => context.push('/career'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Card(
                child: Column(
                  children: [
                    ListTile(
                      leading: const Icon(Icons.lock_outlined),
                      title: const Text('Đổi mật khẩu'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _showChangePasswordDialog(context, ref),
                    ),
                    const Divider(height: 1, indent: 16, endIndent: 16),
                    ListTile(
                      leading: const Icon(Icons.palette_outlined),
                      title: const Text('Giao diện hiển thị'),
                      subtitle: Text(_themeModeLabel(ref.watch(themeNotifierProvider))),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _showThemeDialog(context, ref),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              OutlinedButton.icon(
                onPressed: () async {
                  final confirm = await _confirmLogout(context);
                  if (confirm == true && context.mounted) {
                    await ref.read(authNotifierProvider.notifier).logout();
                    if (context.mounted) context.go('/login');
                  }
                },
                icon: const Icon(Icons.logout, color: Colors.red),
                label: const Text('Đăng xuất', style: TextStyle(color: Colors.red)),
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Colors.red),
                  padding: const EdgeInsets.symmetric(vertical: 14),
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
      case 'admin':
        return 'Quản trị viên';
      case 'instructor':
        return 'Giảng viên';
      default:
        return 'Học viên';
    }
  }

  Future<bool?> _confirmLogout(BuildContext context) {
    return showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Đăng xuất'),
        content: const Text('Bạn có chắc muốn đăng xuất không?'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Huỷ')),
          FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Đăng xuất')),
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
        title: const Text('Sửa hồ sơ'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration: const InputDecoration(labelText: 'Họ tên', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: phoneCtrl,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'Số điện thoại', border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Huỷ')),
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
        title: const Text('Đổi mật khẩu'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: currentCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mật khẩu hiện tại', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: newCtrl,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'Mật khẩu mới', border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Huỷ')),
          FilledButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final error = await ref.read(profileNotifierProvider.notifier).changePassword(
                    currentPassword: currentCtrl.text,
                    newPassword: newCtrl.text,
                  );
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                content: Text(error ?? 'Đổi mật khẩu thành công'),
                backgroundColor: error == null ? Colors.green : Colors.red,
              ));
            },
            child: const Text('Xác nhận'),
          ),
        ],
      ),
    );
  }

  String _themeModeLabel(ThemeMode mode) {
    switch (mode) {
      case ThemeMode.light:
        return 'Giao diện sáng';
      case ThemeMode.dark:
        return 'Giao diện tối (Rừng về đêm)';
      default:
        return 'Theo hệ thống';
    }
  }

  void _showThemeDialog(BuildContext context, WidgetRef ref) {
    final currentMode = ref.read(themeNotifierProvider);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Chọn giao diện hiển thị'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadioListTile<ThemeMode>(
              title: const Text('Sáng'),
              value: ThemeMode.light,
              groupValue: currentMode,
              onChanged: (mode) {
                if (mode != null) {
                  ref.read(themeNotifierProvider.notifier).setThemeMode(mode);
                  Navigator.pop(ctx);
                }
              },
            ),
            RadioListTile<ThemeMode>(
              title: const Text('Tối (Rừng về đêm)'),
              value: ThemeMode.dark,
              groupValue: currentMode,
              onChanged: (mode) {
                if (mode != null) {
                  ref.read(themeNotifierProvider.notifier).setThemeMode(mode);
                  Navigator.pop(ctx);
                }
              },
            ),
            RadioListTile<ThemeMode>(
              title: const Text('Theo mặc định hệ thống'),
              value: ThemeMode.system,
              groupValue: currentMode,
              onChanged: (mode) {
                if (mode != null) {
                  ref.read(themeNotifierProvider.notifier).setThemeMode(mode);
                  Navigator.pop(ctx);
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  const _InfoTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label, style: Theme.of(context).textTheme.bodySmall),
      subtitle: Text(value, style: Theme.of(context).textTheme.bodyMedium),
      dense: true,
    );
  }
}
