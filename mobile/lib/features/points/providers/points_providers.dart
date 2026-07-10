import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/points_model.dart';
import '../data/repositories/points_repository.dart';

part 'points_providers.g.dart';

@riverpod
Future<PointSummaryModel> pointsSummary(PointsSummaryRef ref) {
  return ref.watch(pointsRepositoryProvider).getSummary();
}

@riverpod
Future<List<PointTransactionModel>> pointsTransactions(
    PointsTransactionsRef ref, {int page = 1}) {
  return ref.watch(pointsRepositoryProvider).getTransactions(page: page);
}

@riverpod
Future<List<VoucherModel>> voucherShop(VoucherShopRef ref) {
  return ref.watch(pointsRepositoryProvider).getShop();
}

@riverpod
Future<List<UserVoucherModel>> myVouchers(
    MyVouchersRef ref, {String? status}) {
  return ref.watch(pointsRepositoryProvider).getMyVouchers(status: status);
}
