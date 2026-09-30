import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../providers/points_providers.dart';
import '../../data/models/points_model.dart';
import '../../data/repositories/points_repository.dart';
import '../../../../core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/core/widgets/error_state.dart';

class VoucherShopScreen extends ConsumerWidget {
  const VoucherShopScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shopAsync = ref.watch(voucherShopProvider);

    return Scaffold(
      backgroundColor: context.cs.surface,
      appBar: AppBar(
        title: const Text(
          'Cửa hàng đổi quà',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        backgroundColor: AppBrand.heroStart,
        foregroundColor: Colors.white,
      ),
      body: shopAsync.when(
        data: (vouchers) => vouchers.isEmpty
            ? Center(
                child: Text(
                  'Hiện chưa có quà nào trong cửa hàng',
                  style: TextStyle(color: context.cs.onSurfaceVariant),
                ),
              )
            : _VoucherGrid(vouchers: vouchers),
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => ErrorStateWidget(
          error: e,
          onRetry: () => ref.invalidate(voucherShopProvider),
        ),
      ),
    );
  }
}

class _VoucherGrid extends ConsumerStatefulWidget {
  final List<VoucherModel> vouchers;

  const _VoucherGrid({required this.vouchers});

  @override
  ConsumerState<_VoucherGrid> createState() => _VoucherGridState();
}

class _VoucherGridState extends ConsumerState<_VoucherGrid> {
  final Set<int> _redeeming = {};

  Future<void> _redeem(VoucherModel voucher) async {
    final summaryAsync = ref.read(pointsSummaryProvider);
    final currentBalance = summaryAsync.valueOrNull?.balance ?? 0;

    if (currentBalance < voucher.pointsCost) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Bạn không đủ điểm để đổi quà này'),
          backgroundColor: context.sem.danger,
        ),
      );
      return;
    }

    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xác nhận đổi quà'),
        content: Text(
          'Đổi "${voucher.name}" với ${NumberFormat('#,###').format(voucher.pointsCost)} điểm?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Huỷ'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppBrand.heroStart,
            ),
            child: const Text('Đổi quà', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _redeeming.add(voucher.id));
    try {
      await ref.read(pointsRepositoryProvider).redeemVoucher(voucher.id);
      if (!mounted) return;
      ref.invalidate(pointsSummaryProvider);
      ref.invalidate(myVouchersProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Đổi quà thành công! Kiểm tra "Voucher của tôi" để dùng.',
          ),
          backgroundColor: context.sem.success,
          action: SnackBarAction(
            label: 'Xem',
            textColor: Colors.white,
            onPressed: () {},
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Lỗi đổi quà: ${friendlyErrorMessage(e)}'),
          backgroundColor: context.sem.danger,
        ),
      );
    } finally {
      if (mounted) setState(() => _redeeming.remove(voucher.id));
    }
  }

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.72,
      ),
      itemCount: widget.vouchers.length,
      itemBuilder: (context, i) => _VoucherCard(
        voucher: widget.vouchers[i],
        isRedeeming: _redeeming.contains(widget.vouchers[i].id),
        onRedeem: () => _redeem(widget.vouchers[i]),
      ),
    );
  }
}

class _VoucherCard extends StatelessWidget {
  final VoucherModel voucher;
  final bool isRedeeming;
  final VoidCallback onRedeem;

  const _VoucherCard({
    required this.voucher,
    required this.isRedeeming,
    required this.onRedeem,
  });

  Color _typeColor(BuildContext context) {
    switch (voucher.type) {
      case 'discount_percent':
      case 'discount_fixed':
        return context.cs.primary;
      case 'free_course':
        return context.sem.success;
      case 'physical_gift':
        return context.sem.warning;
      case 'ai_quota':
        return context.sem.info;
      default:
        return context.cs.onSurfaceVariant;
    }
  }

  IconData get _typeIcon {
    switch (voucher.type) {
      case 'discount_percent':
      case 'discount_fixed':
        return Icons.local_offer_outlined;
      case 'free_course':
        return Icons.school_outlined;
      case 'physical_gift':
        return Icons.card_giftcard_outlined;
      case 'ai_quota':
        return Icons.auto_awesome_outlined;
      default:
        return Icons.redeem;
    }
  }

  @override
  Widget build(BuildContext context) {
    final remaining = voucher.remaining;
    final isSoldOut = remaining != null && remaining <= 0;

    return Container(
      decoration: BoxDecoration(
        color: context.cs.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.cs.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: _typeColor(context).withValues(alpha: 0.1),
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(12),
              ),
            ),
            child: Column(
              children: [
                Icon(_typeIcon, color: _typeColor(context), size: 36),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: _typeColor(context),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    voucher.typeLabel,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    voucher.name,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (voucher.description != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      voucher.description!,
                      style: TextStyle(
                        fontSize: 12,
                        color: context.cs.onSurfaceVariant,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                  const Spacer(),
                  if (remaining != null)
                    Text(
                      'Còn lại: $remaining',
                      style: TextStyle(
                        fontSize: 12,
                        color: remaining < 10
                            ? context.sem.danger
                            : context.cs.onSurfaceVariant,
                      ),
                    ),
                  const SizedBox(height: 6),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: isSoldOut || isRedeeming ? null : onRedeem,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _typeColor(context),
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: isRedeeming
                          ? SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: context.cs.onPrimary,
                              ),
                            )
                          : Column(
                              children: [
                                Text(
                                  isSoldOut ? 'Hết hàng' : 'Đổi ngay',
                                  style: TextStyle(
                                    color: context.cs.onPrimary,
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                if (!isSoldOut)
                                  Text(
                                    '${NumberFormat('#,###').format(voucher.pointsCost)} điểm',
                                    style: TextStyle(
                                      color: context.cs.onPrimary.withValues(
                                        alpha: 0.8,
                                      ),
                                      fontSize: 12,
                                    ),
                                  ),
                              ],
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
