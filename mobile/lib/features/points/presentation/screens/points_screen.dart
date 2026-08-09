import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../../providers/points_providers.dart';
import '../../data/models/points_model.dart';
import '../../data/repositories/points_repository.dart';
import '../../../../core/error/friendly_error.dart';

class PointsScreen extends ConsumerStatefulWidget {
  const PointsScreen({super.key});

  @override
  ConsumerState<PointsScreen> createState() => _PointsScreenState();
}

class _PointsScreenState extends ConsumerState<PointsScreen> {
  bool _claimingLogin = false;

  Future<void> _claimDailyLogin() async {
    setState(() => _claimingLogin = true);
    try {
      final result =
          await ref.read(pointsRepositoryProvider).claimDailyLogin();
      if (!mounted) return;
      ref.invalidate(pointsSummaryProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result.message),
          backgroundColor:
              result.rewarded ? const Color(0xFF4CAF50) : Colors.grey[700],
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Lỗi: ${friendlyErrorMessage(e)}'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _claimingLogin = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final summaryAsync = ref.watch(pointsSummaryProvider);
    final transactionsAsync = ref.watch(pointsTransactionsProvider());

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      body: CustomScrollView(
        slivers: [
          _buildAppBar(context),
          SliverToBoxAdapter(
            child: summaryAsync.when(
              data: (summary) => _buildBody(context, summary, transactionsAsync),
              loading: () => const SizedBox(
                  height: 300,
                  child: Center(child: CircularProgressIndicator())),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('Lỗi tải dữ liệu: ${friendlyErrorMessage(e)}'),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildAppBar(BuildContext context) {
    return SliverAppBar(
      expandedHeight: 0,
      floating: true,
      pinned: true,
      backgroundColor: const Color(0xFF1565C0),
      foregroundColor: Colors.white,
      title: const Text('Điểm thưởng',
          style: TextStyle(fontWeight: FontWeight.bold)),
      actions: [
        IconButton(
          icon: const Icon(Icons.card_giftcard_outlined),
          tooltip: 'Cửa hàng đổi quà',
          onPressed: () => context.push('/voucher-shop'),
        ),
        IconButton(
          icon: const Icon(Icons.receipt_long_outlined),
          tooltip: 'Voucher của tôi',
          onPressed: () => context.push('/my-vouchers'),
        ),
      ],
    );
  }

  Widget _buildBody(
    BuildContext context,
    PointSummaryModel summary,
    AsyncValue<List<PointTransactionModel>> transactionsAsync,
  ) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PointsBalanceCard(
            balance: summary.balance,
            streakDays: summary.streakDays,
            onClaimLogin: _claimingLogin ? null : _claimDailyLogin,
          ),
          const SizedBox(height: 16),
          _StreakCard(streakDays: summary.streakDays),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Lịch sử giao dịch',
                  style: TextStyle(
                      fontSize: 16, fontWeight: FontWeight.bold)),
              TextButton(
                onPressed: () {},
                child: const Text('Xem thêm'),
              ),
            ],
          ),
          transactionsAsync.when(
            data: (txns) => _TransactionList(transactions: txns),
            loading: () =>
                const Center(child: CircularProgressIndicator()),
            error: (e, _) => Text('Lỗi tải giao dịch: ${friendlyErrorMessage(e)}'),
          ),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}

class _PointsBalanceCard extends StatelessWidget {
  final int balance;
  final int streakDays;
  final VoidCallback? onClaimLogin;

  const _PointsBalanceCard({
    required this.balance,
    required this.streakDays,
    this.onClaimLogin,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1565C0), Color(0xFF42A5F5)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1565C0).withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Số dư điểm',
              style: TextStyle(color: Colors.white70, fontSize: 14)),
          const SizedBox(height: 4),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                NumberFormat('#,###').format(balance),
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 40,
                  fontWeight: FontWeight.bold,
                  letterSpacing: -1,
                ),
              ),
              const SizedBox(width: 6),
              const Padding(
                padding: EdgeInsets.only(bottom: 6),
                child: Text('điểm',
                    style: TextStyle(color: Colors.white70, fontSize: 16)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Icon(Icons.local_fire_department,
                  color: Colors.orange, size: 18),
              const SizedBox(width: 4),
              Text('Streak $streakDays ngày',
                  style: const TextStyle(
                      color: Colors.white, fontSize: 13)),
              const Spacer(),
              ElevatedButton.icon(
                onPressed: onClaimLogin,
                icon: const Icon(Icons.stars, size: 16),
                label: Text(onClaimLogin == null
                    ? 'Đang xử lý...'
                    : 'Điểm danh hôm nay'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: const Color(0xFF1565C0),
                  padding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 6),
                  textStyle: const TextStyle(
                      fontSize: 12, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StreakCard extends StatelessWidget {
  final int streakDays;

  const _StreakCard({required this.streakDays});

  @override
  Widget build(BuildContext context) {
    final weekDays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    final today = DateTime.now().weekday; // 1=Mon..7=Sun

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.local_fire_department,
                  color: Colors.orange, size: 20),
              const SizedBox(width: 6),
              Text('Chuỗi điểm danh: $streakDays ngày',
                  style: const TextStyle(
                      fontWeight: FontWeight.bold, fontSize: 14)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: List.generate(7, (i) {
              final dayIndex = i + 1;
              final isActive = dayIndex <= (today % 7 == 0 ? 7 : today % 7);
              final isToday = dayIndex == (today % 7 == 0 ? 7 : today % 7);
              return Column(
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: isToday
                          ? const Color(0xFF1565C0)
                          : isActive
                              ? const Color(0xFFBBDEFB)
                              : Colors.grey[100],
                      border: isToday
                          ? Border.all(
                              color: const Color(0xFF1565C0), width: 2)
                          : null,
                    ),
                    child: Center(
                      child: isActive
                          ? Icon(Icons.check,
                              size: 16,
                              color: isToday
                                  ? Colors.white
                                  : const Color(0xFF1565C0))
                          : null,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(weekDays[i],
                      style: TextStyle(
                          fontSize: 10,
                          color: isToday
                              ? const Color(0xFF1565C0)
                              : Colors.grey[600],
                          fontWeight: isToday
                              ? FontWeight.bold
                              : FontWeight.normal)),
                ],
              );
            }),
          ),
          const SizedBox(height: 8),
          Text(
            'Streak 7 ngày nhận thêm 50 điểm thưởng!',
            style: TextStyle(
                fontSize: 11,
                color: Colors.orange[700],
                fontStyle: FontStyle.italic),
          ),
        ],
      ),
    );
  }
}

class _TransactionList extends StatelessWidget {
  final List<PointTransactionModel> transactions;

  const _TransactionList({required this.transactions});

  @override
  Widget build(BuildContext context) {
    if (transactions.isEmpty) {
      return const Padding(
        padding: EdgeInsets.all(24),
        child: Center(
          child: Text('Chưa có giao dịch nào',
              style: TextStyle(color: Colors.grey)),
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: transactions.length,
      itemBuilder: (context, i) {
        final tx = transactions[i];
        final isEarn = tx.type == 'earn';
        return Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 4,
                offset: const Offset(0, 1),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: isEarn
                      ? Colors.green.withOpacity(0.1)
                      : Colors.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  isEarn ? Icons.add_circle_outline : Icons.redeem,
                  color: isEarn ? Colors.green : Colors.orange,
                  size: 18,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(tx.description,
                        style: const TextStyle(
                            fontSize: 13, fontWeight: FontWeight.w500)),
                    const SizedBox(height: 2),
                    Text(
                      _formatDate(tx.createdAt),
                      style: TextStyle(
                          fontSize: 11, color: Colors.grey[500]),
                    ),
                  ],
                ),
              ),
              Text(
                '${isEarn ? '+' : '-'}${tx.amount}',
                style: TextStyle(
                  color: isEarn ? Colors.green : Colors.orange,
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _formatDate(String dateStr) {
    try {
      final dt = DateTime.parse(dateStr).toLocal();
      return DateFormat('dd/MM/yyyy HH:mm').format(dt);
    } catch (_) {
      return dateStr;
    }
  }
}
