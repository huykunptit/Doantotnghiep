import 'package:eript_lms/core/theme/app_spacing.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/features/profile/presentation/widgets/profile_section_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/theme/theme_provider.dart';
import '../../../core/error/friendly_error.dart';
import '../../auth/providers/auth_provider.dart';
import 'widgets/profile_dialogs.dart';
import 'widgets/profile_header.dart';

class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authNotifierProvider);

    return Scaffold(
      body: authState.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(friendlyErrorMessage(e))),
        data: (user) {
          if (user == null) {
            WidgetsBinding.instance.addPostFrameCallback(
              (_) => context.go('/login'),
            );
            return const SizedBox.shrink();
          }
          return CustomScrollView(
            slivers: [
              ProfileHeader(
                name: user.name,
                role: user.role,
                avatarUrl: user.avatar,
                onEdit: () => showEditProfileDialog(
                  context,
                  ref,
                  currentName: user.name,
                  currentPhone: user.phone,
                ),
              ),
              SliverPadding(
                padding: AppSpacing.p16,
                sliver: SliverList(
                  delegate: SliverChildListDelegate([
                    ProfileSectionCard(
                      children: [
                        ProfileInfoRow(
                          icon: Icons.mail_outline_rounded,
                          label: 'Email',
                          value: user.email,
                        ),
                        if (user.studentCode != null)
                          ProfileInfoRow(
                            icon: Icons.numbers_rounded,
                            label: 'Mã sinh viên',
                            value: user.studentCode!,
                          ),
                        if (user.phone != null)
                          ProfileInfoRow(
                            icon: Icons.phone_outlined,
                            label: 'Điện thoại',
                            value: user.phone!,
                          ),
                      ],
                    ),
                    AppSpacing.h12,
                    _section(context, [
                      _Link(Icons.badge_outlined, 'Thẻ sinh viên', '/id-card'),
                      _Link(
                        Icons.receipt_long_outlined,
                        'Bảng điểm học tập',
                        '/transcript',
                      ),
                      _Link(
                        Icons.calendar_month_outlined,
                        'Thời khóa biểu',
                        '/timetable',
                      ),
                      _Link(
                        Icons.event_note_outlined,
                        'Lịch thi',
                        '/exam-calendar',
                      ),
                      _Link(Icons.task_alt_outlined, 'Nhiệm vụ', '/tasks'),
                      _Link(Icons.payments_outlined, 'Học phí', '/tuition'),
                    ]),
                    AppSpacing.h12,
                    _section(context, [
                      _Link(
                        Icons.stars_outlined,
                        'Điểm thưởng & voucher',
                        '/points',
                      ),
                      _Link(
                        Icons.local_library_outlined,
                        'Thư viện tài liệu',
                        '/library',
                      ),
                      _Link(
                        Icons.workspace_premium_outlined,
                        'Chứng chỉ của tôi',
                        '/certificates',
                      ),
                      _Link(
                        Icons.receipt_outlined,
                        'Lịch sử đơn hàng',
                        '/orders',
                      ),
                    ]),
                    AppSpacing.h12,
                    _section(context, [
                      _Link(
                        Icons.auto_graph_outlined,
                        'Cố vấn học tập AI',
                        '/study-advisor',
                      ),
                      _Link(Icons.psychology_outlined, 'AI Career', '/career'),
                    ]),
                    AppSpacing.h12,
                    ProfileSectionCard(
                      children: [
                        ProfileNavTile(
                          icon: Icons.lock_outline_rounded,
                          iconColor: context.cs.onSurfaceVariant,
                          title: 'Đổi mật khẩu',
                          onTap: () => showChangePasswordDialog(context, ref),
                        ),
                        ProfileNavTile(
                          icon: Icons.palette_outlined,
                          iconColor: context.cs.primary,
                          title: 'Giao diện hiển thị',
                          subtitle: themeModeLabel(
                            ref.watch(themeNotifierProvider),
                          ),
                          onTap: () => showThemeDialog(context, ref),
                          isLast: true,
                        ),
                      ],
                    ),
                    AppSpacing.h20,
                    OutlinedButton.icon(
                      onPressed: () async {
                        final confirm = await confirmLogout(context);
                        if (confirm == true && context.mounted) {
                          await ref
                              .read(authNotifierProvider.notifier)
                              .logout();
                          if (context.mounted) context.go('/login');
                        }
                      },
                      icon: Icon(Icons.logout_rounded, color: context.cs.error),
                      label: Text(
                        'Đăng xuất',
                        style: TextStyle(color: context.cs.error),
                      ),
                      style: OutlinedButton.styleFrom(
                        side: BorderSide(color: context.cs.error),
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

  Widget _section(BuildContext context, List<_Link> links) {
    return ProfileSectionCard(
      children: [
        for (var i = 0; i < links.length; i++)
          ProfileNavTile(
            icon: links[i].icon,
            iconColor: context.cs.primary,
            title: links[i].title,
            onTap: () => context.push(links[i].route),
            isLast: i == links.length - 1,
          ),
      ],
    );
  }
}

class _Link {
  const _Link(this.icon, this.title, this.route);

  final IconData icon;
  final String title;
  final String route;
}
