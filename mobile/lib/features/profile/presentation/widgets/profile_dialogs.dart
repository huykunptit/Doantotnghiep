import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../app/theme/theme_provider.dart';
import '../../providers/profile_provider.dart';

String themeModeLabel(ThemeMode mode) {
  switch (mode) {
    case ThemeMode.light:
      return 'Giao diện sáng';
    case ThemeMode.dark:
      return 'Giao diện tối';
    case ThemeMode.system:
      return 'Theo hệ thống';
  }
}

Future<bool?> confirmLogout(BuildContext context) {
  return showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Đăng xuất'),
      content: const Text('Bạn có chắc muốn đăng xuất không?'),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, true),
          style: FilledButton.styleFrom(
            backgroundColor: ctx.cs.error,
            foregroundColor: ctx.cs.onError,
          ),
          child: const Text('Đăng xuất'),
        ),
      ],
    ),
  );
}

void showEditProfileDialog(
  BuildContext context,
  WidgetRef ref, {
  required String currentName,
  required String? currentPhone,
}) {
  final nameCtrl = TextEditingController(text: currentName);
  final phoneCtrl = TextEditingController(text: currentPhone ?? '');
  showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Sửa hồ sơ'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: nameCtrl,
            decoration: const InputDecoration(labelText: 'Họ tên'),
          ),
          AppSpacing.h12,
          TextField(
            controller: phoneCtrl,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(labelText: 'Số điện thoại'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () async {
            Navigator.pop(ctx);
            await ref
                .read(profileNotifierProvider.notifier)
                .updateProfile(
                  name: nameCtrl.text.trim(),
                  phone: phoneCtrl.text.trim().isEmpty
                      ? null
                      : phoneCtrl.text.trim(),
                );
          },
          child: const Text('Lưu'),
        ),
      ],
    ),
  ).whenComplete(() {
    nameCtrl.dispose();
    phoneCtrl.dispose();
  });
}

void showChangePasswordDialog(BuildContext context, WidgetRef ref) {
  final currentCtrl = TextEditingController();
  final newCtrl = TextEditingController();
  showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Đổi mật khẩu'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: currentCtrl,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Mật khẩu hiện tại'),
          ),
          AppSpacing.h12,
          TextField(
            controller: newCtrl,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Mật khẩu mới'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Huỷ'),
        ),
        FilledButton(
          onPressed: () async {
            Navigator.pop(ctx);
            final error = await ref
                .read(profileNotifierProvider.notifier)
                .changePassword(
                  currentPassword: currentCtrl.text,
                  newPassword: newCtrl.text,
                );
            if (!context.mounted) return;
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(error ?? 'Đổi mật khẩu thành công'),
                backgroundColor: error == null
                    ? context.sem.success
                    : context.cs.error,
              ),
            );
          },
          child: const Text('Xác nhận'),
        ),
      ],
    ),
  ).whenComplete(() {
    currentCtrl.dispose();
    newCtrl.dispose();
  });
}

void showThemeDialog(BuildContext context, WidgetRef ref) {
  final currentMode = ref.read(themeNotifierProvider);
  showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Giao diện hiển thị'),
      content: RadioGroup<ThemeMode>(
        groupValue: currentMode,
        onChanged: (mode) {
          if (mode != null) {
            ref.read(themeNotifierProvider.notifier).setThemeMode(mode);
            Navigator.pop(ctx);
          }
        },
        child: const Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            RadioListTile<ThemeMode>(
              title: Text('Sáng'),
              value: ThemeMode.light,
            ),
            RadioListTile<ThemeMode>(title: Text('Tối'), value: ThemeMode.dark),
            RadioListTile<ThemeMode>(
              title: Text('Theo mặc định hệ thống'),
              value: ThemeMode.system,
            ),
          ],
        ),
      ),
    ),
  );
}
