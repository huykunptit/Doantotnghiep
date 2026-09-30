import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../providers/points_providers.dart';
import '../../data/models/points_model.dart';
import '../../data/repositories/points_repository.dart';
import '../../../../core/error/friendly_error.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';
import 'package:eript_lms/features/points/presentation/widgets/points_balance_card.dart';

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
      final result = await ref.read(pointsRepositoryProvider).claimDailyLogin();
      if (!mounted) return;
      ref.invalidate(pointsSummaryProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(result.message),
          backgroundColor: result.rewarded
              ? context.sem.success
              : context.cs.onSurfaceVariant,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Lỗi: ${friendlyErrorMessage(e)}'),
          backgroundColor: context.sem.danger,
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
      backgroundColor: context.cs.surface,
      body: CustomScrollView(
        slivers: [
          _buildAppBar(context),
          SliverToBoxAdapter(
            child: summaryAsync.when(
              data: (summary) =>
                  _buildBody(context, summary, transactionsAsync),
              loading: () => const SizedBox(
                height: 300,
                child: Center(child: CircularProgressIndicator()),
              ),
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
      backgroundColor: AppBrand.heroStart,
      foregroundColor: Colors.white,
      title: const Text(
        'Điểm thưởng',
        style: TextStyle(fontWeight: FontWeight.bold),
      ),
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
          PointsBalanceCard(
            balance: summary.balance,
            streakDays: summary.streakDays,
            onClaimLogin: _claimingLogin ? null : _claimDailyLogin,
          ),
          const SizedBox(height: 16),
          StreakCard(streakDays: summary.streakDays),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Lịch sử giao dịch',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              TextButton(onPressed: () {}, child: const Text('Xem thêm')),
            ],
          ),
          transactionsAsync.when(
            data: (txns) => TransactionList(transactions: txns),
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) =>
                Text('Lỗi tải giao dịch: ${friendlyErrorMessage(e)}'),
          ),
          const SizedBox(height: 80),
        ],
      ),
    );
  }
}
