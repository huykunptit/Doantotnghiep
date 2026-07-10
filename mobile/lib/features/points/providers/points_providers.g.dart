// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'points_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$pointsSummaryHash() => r'points_summary_hash_stub';

/// See also [pointsSummary].
@ProviderFor(pointsSummary)
final pointsSummaryProvider =
    AutoDisposeFutureProvider<PointSummaryModel>.internal(
  pointsSummary,
  name: r'pointsSummaryProvider',
  debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
      ? null
      : _$pointsSummaryHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

typedef PointsSummaryRef = AutoDisposeFutureProviderRef<PointSummaryModel>;

// --- pointsTransactions (family) ---

String _$pointsTransactionsHash() => r'points_transactions_hash_stub';

typedef PointsTransactionsRef
    = AutoDisposeFutureProviderRef<List<PointTransactionModel>>;

class PointsTransactionsProvider
    extends AutoDisposeFutureProvider<List<PointTransactionModel>> {
  PointsTransactionsProvider(this.page)
      : super.internal(
          (ref) => pointsTransactions(ref, page: page),
          from: pointsTransactionsProvider,
          name: r'pointsTransactionsProvider',
          debugGetCreateSourceHash:
              const bool.fromEnvironment('dart.vm.product')
                  ? null
                  : _$pointsTransactionsHash,
          dependencies: null,
          allTransitiveDependencies: null,
        );

  final int page;

  @override
  bool operator ==(Object other) {
    return other is PointsTransactionsProvider && other.page == page;
  }

  @override
  int get hashCode => page.hashCode;
}

class PointsTransactionsFamily
    extends Family<AsyncValue<List<PointTransactionModel>>> {
  const PointsTransactionsFamily();

  PointsTransactionsProvider call({int page = 1}) =>
      PointsTransactionsProvider(page);

  @override
  AutoDisposeFutureProvider<List<PointTransactionModel>> getProviderOverride(
    covariant PointsTransactionsProvider provider,
  ) {
    return call(page: provider.page);
  }

  @override
  List<ProviderOrFamily>? get dependencies => null;

  @override
  List<ProviderOrFamily>? get allTransitiveDependencies => null;

  @override
  String? get name => r'pointsTransactionsProvider';
}

const pointsTransactionsProvider = PointsTransactionsFamily();

// --- voucherShop ---

String _$voucherShopHash() => r'voucher_shop_hash_stub';

/// See also [voucherShop].
@ProviderFor(voucherShop)
final voucherShopProvider =
    AutoDisposeFutureProvider<List<VoucherModel>>.internal(
  voucherShop,
  name: r'voucherShopProvider',
  debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
      ? null
      : _$voucherShopHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

typedef VoucherShopRef = AutoDisposeFutureProviderRef<List<VoucherModel>>;

// --- myVouchers (family) ---

String _$myVouchersHash() => r'my_vouchers_hash_stub';

typedef MyVouchersRef = AutoDisposeFutureProviderRef<List<UserVoucherModel>>;

class MyVouchersProvider
    extends AutoDisposeFutureProvider<List<UserVoucherModel>> {
  MyVouchersProvider(this.status)
      : super.internal(
          (ref) => myVouchers(ref, status: status),
          from: myVouchersProvider,
          name: r'myVouchersProvider',
          debugGetCreateSourceHash:
              const bool.fromEnvironment('dart.vm.product')
                  ? null
                  : _$myVouchersHash,
          dependencies: null,
          allTransitiveDependencies: null,
        );

  final String? status;

  @override
  bool operator ==(Object other) {
    return other is MyVouchersProvider && other.status == status;
  }

  @override
  int get hashCode => status.hashCode;
}

class MyVouchersFamily extends Family<AsyncValue<List<UserVoucherModel>>> {
  const MyVouchersFamily();

  MyVouchersProvider call({String? status}) => MyVouchersProvider(status);

  @override
  AutoDisposeFutureProvider<List<UserVoucherModel>> getProviderOverride(
    covariant MyVouchersProvider provider,
  ) {
    return call(status: provider.status);
  }

  @override
  List<ProviderOrFamily>? get dependencies => null;

  @override
  List<ProviderOrFamily>? get allTransitiveDependencies => null;

  @override
  String? get name => r'myVouchersProvider';
}

const myVouchersProvider = MyVouchersFamily();

// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member
