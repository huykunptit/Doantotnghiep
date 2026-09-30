import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:eript_lms/features/points/data/models/points_model.dart';
import 'package:eript_lms/core/theme/theme_context.dart';
import 'package:eript_lms/core/theme/app_brand.dart';

class PointsBalanceCard extends StatelessWidget {
  final int balance;
  final int streakDays;
  final VoidCallback? onClaimLogin;

  const PointsBalanceCard({
    super.key,
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
          colors: AppBrand.heroColors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Số dư điểm',
            style: TextStyle(color: Colors.white70, fontSize: 14),
          ),
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
                child: Text(
                  'điểm',
                  style: TextStyle(color: Colors.white70, fontSize: 16),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Icon(
                Icons.local_fire_department,
                color: context.sem.warning,
                size: 18,
              ),
              const SizedBox(width: 4),
              Text(
                'Streak $streakDays ngày',
                style: const TextStyle(color: Colors.white, fontSize: 13),
              ),
              const Spacer(),
              ElevatedButton.icon(
                onPressed: onClaimLogin,
                icon: const Icon(Icons.stars, size: 16),
                label: Text(
                  onClaimLogin == null ? 'Đang xử lý...' : 'Điểm danh hôm nay',
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: context.cs.primary,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  textStyle: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class StreakCard extends StatelessWidget {
  final int streakDays;

  const StreakCard({super.key, required this.streakDays});

  @override
  Widget build(BuildContext context) {
    final weekDays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    final today = DateTime.now().weekday; // 1=Mon..7=Sun

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.cs.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: context.cs.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                Icons.local_fire_department,
                color: context.sem.warning,
                size: 20,
              ),
              const SizedBox(width: 6),
              Text(
                'Chuỗi điểm danh: $streakDays ngày',
                style: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                ),
              ),
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
                          ? context.cs.primary
                          : isActive
                          ? context.cs.primaryContainer
                          : context.cs.surfaceContainerLow,
                      border: isToday
                          ? Border.all(color: context.cs.primary, width: 2)
                          : null,
                    ),
                    child: Center(
                      child: isActive
                          ? Icon(
                              Icons.check,
                              size: 16,
                              color: isToday
                                  ? Colors.white
                                  : context.cs.primary,
                            )
                          : null,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    weekDays[i],
                    style: TextStyle(
                      fontSize: 12,
                      color: isToday
                          ? context.cs.primary
                          : context.cs.onSurfaceVariant,
                      fontWeight: isToday ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ],
              );
            }),
          ),
          const SizedBox(height: 8),
          Text(
            'Streak 7 ngày nhận thêm 50 điểm thưởng!',
            style: TextStyle(
              fontSize: 12,
              color: context.sem.warningFg,
              fontStyle: FontStyle.italic,
            ),
          ),
        ],
      ),
    );
  }
}

class TransactionList extends StatelessWidget {
  final List<PointTransactionModel> transactions;

  const TransactionList({super.key, required this.transactions});

  @override
  Widget build(BuildContext context) {
    if (transactions.isEmpty) {
      return Padding(
        padding: EdgeInsets.all(24),
        child: Center(
          child: Text(
            'Chưa có giao dịch nào',
            style: TextStyle(color: context.cs.onSurfaceVariant),
          ),
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
            color: context.cs.surfaceContainerLowest,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: context.cs.outlineVariant),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: isEarn
                      ? context.sem.success.withValues(alpha: 0.1)
                      : context.sem.warning.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(
                  isEarn ? Icons.add_circle_outline : Icons.redeem,
                  color: isEarn ? context.sem.success : context.sem.warning,
                  size: 18,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      tx.description,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _formatDate(tx.createdAt),
                      style: TextStyle(
                        fontSize: 12,
                        color: context.cs.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '${isEarn ? '+' : '-'}${tx.amount}',
                style: TextStyle(
                  color: isEarn ? context.sem.success : context.sem.warning,
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
