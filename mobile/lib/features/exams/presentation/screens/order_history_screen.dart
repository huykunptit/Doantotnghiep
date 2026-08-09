import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../data/models/exam_list_model.dart';
import '../../providers/exam_providers.dart';
import '../../../../core/error/friendly_error.dart';

class OrderHistoryScreen extends ConsumerWidget {
  const OrderHistoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(myOrdersProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Lịch sử mua hàng',
            style: TextStyle(fontWeight: FontWeight.w700)),
        centerTitle: false,
        surfaceTintColor: Colors.transparent,
      ),
      body: ordersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 8),
              Text(friendlyErrorMessage(e), textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(
                  onPressed: () => ref.invalidate(myOrdersProvider),
                  child: const Text('Thử lại')),
            ],
          ),
        ),
        data: (orders) {
          if (orders.isEmpty) {
            return const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.receipt_long_outlined,
                      size: 64, color: Colors.grey),
                  SizedBox(height: 12),
                  Text('Chưa có đơn hàng nào',
                      style: TextStyle(color: Colors.grey)),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(myOrdersProvider),
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: orders.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, i) => _OrderCard(order: orders[i]),
            ),
          );
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({required this.order});
  final OrderModel order;

  static const _statusLabel = {
    'paid': 'Đã thanh toán',
    'pending': 'Chờ thanh toán',
    'failed': 'Thất bại',
  };

  static const _statusColor = {
    'paid': AppColors.success,
    'pending': AppColors.warning,
    'failed': AppColors.error,
  };

  String _fmtDate(String iso) {
    try {
      return DateFormat('dd/MM/yyyy HH:mm').format(DateTime.parse(iso).toLocal());
    } catch (_) {
      return iso;
    }
  }

  String _fmtAmount(double amount) {
    if (amount == 0) return 'Miễn phí';
    return NumberFormat.currency(locale: 'vi_VN', symbol: '₫')
        .format(amount);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final statusColor =
        _statusColor[order.status] ?? Colors.grey;
    final statusText =
        _statusLabel[order.status] ?? order.status;

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(color: theme.colorScheme.outlineVariant),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header row
            Row(
              children: [
                Text(
                  '#${order.id}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const Spacer(),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    statusText,
                    style: TextStyle(
                        color: statusColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            // Course info
            if (order.course != null)
              Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: order.course!.thumbnail != null
                        ? CachedNetworkImage(
                            imageUrl: order.course!.thumbnail!,
                            width: 56,
                            height: 56,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) =>
                                _CoursePlaceholder(title: order.course!.title),
                          )
                        : _CoursePlaceholder(title: order.course!.title),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      order.course!.title,
                      style: theme.textTheme.bodyMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            const SizedBox(height: 12),
            const Divider(height: 1),
            const SizedBox(height: 12),
            // Amount + date
            Row(
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Tổng tiền',
                      style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant),
                    ),
                    Text(
                      _fmtAmount(order.amount),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.primary400,
                      ),
                    ),
                  ],
                ),
                const Spacer(),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      order.status == 'paid' && order.paidAt != null
                          ? 'Đã thanh toán lúc'
                          : 'Tạo lúc',
                      style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant),
                    ),
                    Text(
                      _fmtDate(order.paidAt ?? order.createdAt),
                      style: theme.textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ],
            ),
            if (order.paymentMethod != null) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.payment, size: 14, color: Colors.grey),
                  const SizedBox(width: 4),
                  Text(
                    'Thanh toán qua: ${order.paymentMethod}',
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: Colors.grey),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _CoursePlaceholder extends StatelessWidget {
  const _CoursePlaceholder({required this.title});
  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: AppColors.primary400.withOpacity(0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      alignment: Alignment.center,
      child: Text(
        title.isNotEmpty ? title[0].toUpperCase() : 'K',
        style: const TextStyle(
            color: AppColors.primary400,
            fontWeight: FontWeight.w700,
            fontSize: 20),
      ),
    );
  }
}
