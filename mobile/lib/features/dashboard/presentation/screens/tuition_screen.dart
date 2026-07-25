import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../providers/dashboard_provider.dart';
import '../../data/repositories/dashboard_repository.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';

class TuitionScreen extends ConsumerStatefulWidget {
  const TuitionScreen({super.key});
  static const routeName = '/tuition';

  @override
  ConsumerState<TuitionScreen> createState() => _TuitionScreenState();
}

class _TuitionScreenState extends ConsumerState<TuitionScreen> {
  int? _payingId;

  String _money(double n) =>
      NumberFormat.currency(locale: 'vi_VN', symbol: '₫', decimalDigits: 0).format(n);

  Future<void> _pay(int id, double amount) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Thanh toán học phí'),
        content: Text('Xác nhận thanh toán ${_money(amount)}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hủy')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Thanh toán')),
        ],
      ),
    );
    if (ok != true) return;

    setState(() => _payingId = id);
    try {
      await ref.read(dashboardRepositoryProvider).payTuition(id);
      ref.invalidate(studentTuitionProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Thanh toán học phí thành công'), backgroundColor: Colors.green),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Lỗi: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _payingId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(studentTuitionProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Học phí'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(studentTuitionProvider),
          ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Lỗi: $e')),
        data: (data) {
          if (data.items.isEmpty) {
            return const Center(child: Text('Chưa có dữ liệu học phí.'));
          }
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              Row(
                children: [
                  Expanded(
                    child: _SummaryCard(
                      label: 'Còn phải đóng',
                      value: _money(data.totalDue),
                      color: AppColors.error,
                    ),
                  ),
                  AppSpacing.w8,
                  Expanded(
                    child: _SummaryCard(
                      label: 'Đã đóng',
                      value: _money(data.totalPaid),
                      color: AppColors.success,
                    ),
                  ),
                ],
              ),
              AppSpacing.h16,
              ...data.items.map((item) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.neutral200),
                    color: theme.brightness == Brightness.dark ? AppColors.darkSurface : Colors.white,
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item.termName ?? 'Học kỳ', style: const TextStyle(fontWeight: FontWeight.w800)),
                            if (item.note != null)
                              Text(item.note!, style: theme.textTheme.bodySmall),
                            AppSpacing.h8,
                            Text(_money(item.amount), style: const TextStyle(fontWeight: FontWeight.w900)),
                          ],
                        ),
                      ),
                      if (item.isPaid)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppColors.primary50,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: const Text('Đã đóng', style: TextStyle(color: AppColors.primary600, fontWeight: FontWeight.w700)),
                        )
                      else
                        FilledButton(
                          onPressed: _payingId == item.id ? null : () => _pay(item.id, item.amount),
                          child: _payingId == item.id
                              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('Thanh toán'),
                        ),
                    ],
                  ),
                );
              }),
            ],
          );
        },
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.label, required this.value, required this.color});
  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.neutral200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(fontSize: 12, color: AppColors.neutral600, fontWeight: FontWeight.w600)),
          AppSpacing.h8,
          Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: color)),
        ],
      ),
    );
  }
}
