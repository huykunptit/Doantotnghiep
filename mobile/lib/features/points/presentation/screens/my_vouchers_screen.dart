import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import '../../../providers/points_providers.dart';
import '../../../data/models/points_model.dart';

class MyVouchersScreen extends ConsumerStatefulWidget {
  const MyVouchersScreen({super.key});

  @override
  ConsumerState<MyVouchersScreen> createState() => _MyVouchersScreenState();
}

class _MyVouchersScreenState extends ConsumerState<MyVouchersScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String?> _statuses = [null, 'unused', 'used', 'expired'];
  final List<String> _tabLabels = ['Tất cả', 'Chưa dùng', 'Đã dùng', 'Hết hạn'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        title: const Text('Voucher của tôi',
            style: TextStyle(fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFF1565C0),
        foregroundColor: Colors.white,
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          indicatorColor: Colors.white,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          tabs: _tabLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: _statuses
            .map((s) => _VoucherTab(status: s))
            .toList(),
      ),
    );
  }
}

class _VoucherTab extends ConsumerWidget {
  final String? status;

  const _VoucherTab({this.status});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final provider = ref.watch(myVouchersProvider(status: status));

    return provider.when(
      data: (vouchers) => vouchers.isEmpty
          ? const Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.receipt_long_outlined,
                      size: 64, color: Colors.grey),
                  SizedBox(height: 12),
                  Text('Không có voucher nào',
                      style: TextStyle(color: Colors.grey)),
                ],
              ),
            )
          : RefreshIndicator(
              onRefresh: () async => ref.invalidate(myVouchersProvider),
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: vouchers.length,
                itemBuilder: (ctx, i) => _UserVoucherCard(uv: vouchers[i]),
              ),
            ),
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.grey),
              const SizedBox(height: 12),
              Text('Lỗi: $e', textAlign: TextAlign.center),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => ref.invalidate(myVouchersProvider),
                child: const Text('Thử lại'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _UserVoucherCard extends StatelessWidget {
  final UserVoucherModel uv;

  const _UserVoucherCard({required this.uv});

  Color get _statusColor {
    switch (uv.status) {
      case 'unused':
        return const Color(0xFF2E7D32);
      case 'used':
        return Colors.grey;
      case 'expired':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }

  String get _statusLabel {
    switch (uv.status) {
      case 'unused':
        return 'Chưa dùng';
      case 'used':
        return 'Đã dùng';
      case 'expired':
        return 'Hết hạn';
      default:
        return uv.status;
    }
  }

  @override
  Widget build(BuildContext context) {
    final voucher = uv.voucher;
    final isUnused = uv.status == 'unused';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
        border: Border.all(
          color: isUnused
              ? const Color(0xFF1565C0).withOpacity(0.3)
              : Colors.grey.withOpacity(0.2),
        ),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: isUnused
                        ? const Color(0xFF1565C0).withOpacity(0.1)
                        : Colors.grey.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    Icons.local_offer_outlined,
                    color: isUnused
                        ? const Color(0xFF1565C0)
                        : Colors.grey,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              voucher?.name ?? 'Voucher',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                                color: isUnused
                                    ? Colors.black87
                                    : Colors.grey,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 8, vertical: 2),
                            decoration: BoxDecoration(
                              color: _statusColor.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Text(
                              _statusLabel,
                              style: TextStyle(
                                  fontSize: 10,
                                  color: _statusColor,
                                  fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                      if (voucher?.typeLabel != null) ...[
                        const SizedBox(height: 2),
                        Text(
                          voucher!.typeLabel,
                          style: TextStyle(
                              fontSize: 12,
                              color: isUnused
                                  ? const Color(0xFF1565C0)
                                  : Colors.grey),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(
                horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: isUnused
                  ? const Color(0xFFF0F4FF)
                  : Colors.grey[50],
              borderRadius: const BorderRadius.vertical(
                  bottom: Radius.circular(12)),
            ),
            child: Row(
              children: [
                Icon(Icons.confirmation_number_outlined,
                    size: 14,
                    color: isUnused
                        ? const Color(0xFF1565C0)
                        : Colors.grey),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    uv.code,
                    style: TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 13,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 1.5,
                      color: isUnused
                          ? const Color(0xFF1565C0)
                          : Colors.grey,
                    ),
                  ),
                ),
                if (isUnused)
                  GestureDetector(
                    onTap: () {
                      // TODO: copy to clipboard
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                            content: Text('Đã sao chép mã voucher'),
                            duration: Duration(seconds: 1)),
                      );
                    },
                    child: const Icon(Icons.copy,
                        size: 16, color: Color(0xFF1565C0)),
                  ),
              ],
            ),
          ),
          if (uv.expiresAt != null && isUnused)
            Padding(
              padding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
              child: Row(
                children: [
                  Icon(Icons.access_time,
                      size: 12, color: Colors.orange[700]),
                  const SizedBox(width: 4),
                  Text(
                    'Hết hạn: ${_formatDate(uv.expiresAt!)}',
                    style: TextStyle(
                        fontSize: 11, color: Colors.orange[700]),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  String _formatDate(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr).toLocal();
      return DateFormat('dd/MM/yyyy').format(dt);
    } catch (_) {
      return dateStr;
    }
  }
}
