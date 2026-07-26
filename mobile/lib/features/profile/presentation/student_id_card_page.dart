import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../auth/providers/auth_provider.dart';
import '../data/repositories/profile_repository.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';

/// Plain (non code-generated) provider — avoids a build_runner pass just to
/// surface a couple of read-only fields (class/program/major) for the card.
final learnerProfileProvider = FutureProvider.autoDispose<Map<String, dynamic>?>((ref) async {
  try {
    return await ref.read(profileRepositoryProvider).getLearnerProfile();
  } catch (_) {
    return null;
  }
});

class StudentIdCardPage extends ConsumerWidget {
  const StudentIdCardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authNotifierProvider);
    final learnerProfileAsync = ref.watch(learnerProfileProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Thẻ sinh viên')),
      body: authState.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (user) {
          if (user == null) return const SizedBox.shrink();

          final profileUser = learnerProfileAsync.valueOrNull?['user'] as Map<String, dynamic>?;
          final studentCode = (user.studentCode?.isNotEmpty ?? false)
              ? user.studentCode!
              : (profileUser?['student_code']?.toString() ?? '—');

          final adminClass = profileUser?['administrative_class'] as Map<String, dynamic>?;
          final className = (adminClass?['code'] ?? adminClass?['name'])?.toString() ?? '—';

          final program = profileUser?['program'] as Map<String, dynamic>?;
          final major = profileUser?['major'] as Map<String, dynamic>?;
          final programLabel = [program?['name'], major?['name']]
              .whereType<String>()
              .where((s) => s.trim().isNotEmpty)
              .join(' — ');

          return SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                _IdCard(
                  name: user.name,
                  studentCode: studentCode,
                  className: className,
                  programLabel: programLabel.isEmpty ? '—' : programLabel,
                  avatarUrl: user.avatar,
                  loadingDetails: learnerProfileAsync.isLoading,
                ),
                AppSpacing.h20,
                Text(
                  'Xuất trình thẻ này (trên màn hình) khi làm thủ tục học vụ, thi cử tại trường.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _IdCard extends StatelessWidget {
  const _IdCard({
    required this.name,
    required this.studentCode,
    required this.className,
    required this.programLabel,
    required this.avatarUrl,
    required this.loadingDetails,
  });

  final String name;
  final String studentCode;
  final String className;
  final String programLabel;
  final String? avatarUrl;
  final bool loadingDetails;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 320,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary400, AppColors.primary600, AppColors.primary800],
        ),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary900.withValues(alpha: 0.35),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned.fill(
            child: Align(
              alignment: Alignment.center,
              child: Transform.rotate(
                angle: -0.38,
                child: const Text(
                  'THẺ SINH VIÊN',
                  style: TextStyle(fontSize: 34, fontWeight: FontWeight.w900, color: Colors.white10, letterSpacing: 2),
                ),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 34, height: 34,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: const Icon(Icons.account_balance_rounded, color: Colors.white, size: 18),
                  ),
                  AppSpacing.w12,
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('TRƯỜNG ĐẠI HỌC SYLVA',
                            style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: .5)),
                        SizedBox(height: 2),
                        Text('THẺ SINH VIÊN',
                            style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w900)),
                        Text('STUDENT ID CARD',
                            style: TextStyle(color: Colors.white70, fontSize: 9, fontWeight: FontWeight.w600, letterSpacing: 2)),
                      ],
                    ),
                  ),
                ],
              ),
              AppSpacing.h16,
              Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Container(
                    width: 68, height: 82,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.white70, width: 2),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: avatarUrl != null
                        ? CachedNetworkImage(imageUrl: avatarUrl!, fit: BoxFit.cover)
                        : const Icon(Icons.person, color: Colors.white70, size: 34),
                  ),
                  AppSpacing.w12,
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('HỌ VÀ TÊN',
                            style: TextStyle(color: Colors.white60, fontSize: 9, fontWeight: FontWeight.w700, letterSpacing: 1)),
                        Text(name,
                            style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800)),
                      ],
                    ),
                  ),
                ],
              ),
              AppSpacing.h16,
              _CardField(label: 'Mã SV', value: studentCode, mono: true),
              _CardField(label: 'Lớp', value: className),
              _CardField(
                label: 'Hệ / Ngành',
                value: loadingDetails ? 'Đang tải…' : programLabel,
              ),
              AppSpacing.h12,
              Container(
                padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 10),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(
                  children: [
                    SizedBox(
                      height: 32,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: _barcodeBars(studentCode),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      studentCode,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 2,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  List<Widget> _barcodeBars(String code) {
    final chars = code == '—' ? '00000000'.split('') : code.split('');
    return List.generate(chars.length, (i) {
      final v = chars[i].codeUnitAt(0) + i;
      final width = (2 + v % 4).toDouble();
      final tall = v % 5 != 0;
      return Container(
        width: width,
        height: tall ? 32 : 20,
        margin: const EdgeInsets.symmetric(horizontal: 0.8),
        color: Colors.white,
      );
    });
  }
}

class _CardField extends StatelessWidget {
  const _CardField({required this.label, required this.value, this.mono = false});
  final String label;
  final String value;
  final bool mono;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 78,
            child: Text(label.toUpperCase(),
                style: const TextStyle(color: Colors.white60, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: .5)),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w700,
                fontFamily: mono ? 'monospace' : null,
                letterSpacing: mono ? 1 : 0,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
