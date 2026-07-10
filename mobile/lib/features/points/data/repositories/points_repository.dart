import 'package:dio/dio.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../models/points_model.dart';
import '../../../../core/network/dio_client.dart';

part 'points_repository.g.dart';

@riverpod
PointsRepository pointsRepository(PointsRepositoryRef ref) {
  return PointsRepository(ref.watch(dioClientProvider));
}

class PointsRepository {
  final Dio _dio;

  PointsRepository(this._dio);

  Future<PointSummaryModel> getSummary() async {
    final res = await _dio.get('/api/points/summary');
    return PointSummaryModel.fromJson(res.data as Map<String, dynamic>);
  }

  Future<List<PointTransactionModel>> getTransactions({int page = 1}) async {
    final res = await _dio.get('/api/points/transactions',
        queryParameters: {'page': page});
    final list = (res.data['data'] as List<dynamic>? ?? []);
    return list
        .map((e) =>
            PointTransactionModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<DailyLoginResult> claimDailyLogin() async {
    final res = await _dio.post('/api/points/daily-login');
    return DailyLoginResult.fromJson(res.data as Map<String, dynamic>);
  }

  Future<List<VoucherModel>> getShop() async {
    final res = await _dio.get('/api/points/shop');
    final list = res.data['data'] as List<dynamic>? ?? res.data as List<dynamic>? ?? [];
    return list
        .map((e) => VoucherModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<UserVoucherModel> redeemVoucher(int voucherId) async {
    final res = await _dio.post('/api/points/redeem', data: {'voucher_id': voucherId});
    return UserVoucherModel.fromJson(res.data['user_voucher'] as Map<String, dynamic>);
  }

  Future<List<UserVoucherModel>> getMyVouchers({String? status}) async {
    final res = await _dio.get('/api/points/my-vouchers',
        queryParameters: status != null ? {'status': status} : null);
    final list = res.data['data'] as List<dynamic>? ?? res.data as List<dynamic>? ?? [];
    return list
        .map((e) => UserVoucherModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }
}
