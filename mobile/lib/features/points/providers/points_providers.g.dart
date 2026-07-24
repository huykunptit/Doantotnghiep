// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'points_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$pointsSummaryHash() => r'48930a458b14d06b0bfe620fc50b4cb216db48df';

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

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef PointsSummaryRef = AutoDisposeFutureProviderRef<PointSummaryModel>;
String _$pointsTransactionsHash() =>
    r'307a50ab332b0b4a60da8c3216017eaa3da7e710';

/// Copied from Dart SDK
class _SystemHash {
  _SystemHash._();

  static int combine(int hash, int value) {
    // ignore: parameter_assignments
    hash = 0x1fffffff & (hash + value);
    // ignore: parameter_assignments
    hash = 0x1fffffff & (hash + ((0x0007ffff & hash) << 10));
    return hash ^ (hash >> 6);
  }

  static int finish(int hash) {
    // ignore: parameter_assignments
    hash = 0x1fffffff & (hash + ((0x03ffffff & hash) << 3));
    // ignore: parameter_assignments
    hash = hash ^ (hash >> 11);
    return 0x1fffffff & (hash + ((0x00003fff & hash) << 15));
  }
}

/// See also [pointsTransactions].
@ProviderFor(pointsTransactions)
const pointsTransactionsProvider = PointsTransactionsFamily();

/// See also [pointsTransactions].
class PointsTransactionsFamily
    extends Family<AsyncValue<List<PointTransactionModel>>> {
  /// See also [pointsTransactions].
  const PointsTransactionsFamily();

  /// See also [pointsTransactions].
  PointsTransactionsProvider call({int page = 1}) {
    return PointsTransactionsProvider(page: page);
  }

  @override
  PointsTransactionsProvider getProviderOverride(
    covariant PointsTransactionsProvider provider,
  ) {
    return call(page: provider.page);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'pointsTransactionsProvider';
}

/// See also [pointsTransactions].
class PointsTransactionsProvider
    extends AutoDisposeFutureProvider<List<PointTransactionModel>> {
  /// See also [pointsTransactions].
  PointsTransactionsProvider({int page = 1})
    : this._internal(
        (ref) => pointsTransactions(ref as PointsTransactionsRef, page: page),
        from: pointsTransactionsProvider,
        name: r'pointsTransactionsProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$pointsTransactionsHash,
        dependencies: PointsTransactionsFamily._dependencies,
        allTransitiveDependencies:
            PointsTransactionsFamily._allTransitiveDependencies,
        page: page,
      );

  PointsTransactionsProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.page,
  }) : super.internal();

  final int page;

  @override
  Override overrideWith(
    FutureOr<List<PointTransactionModel>> Function(
      PointsTransactionsRef provider,
    )
    create,
  ) {
    return ProviderOverride(
      origin: this,
      override: PointsTransactionsProvider._internal(
        (ref) => create(ref as PointsTransactionsRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        page: page,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<PointTransactionModel>>
  createElement() {
    return _PointsTransactionsProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is PointsTransactionsProvider && other.page == page;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, page.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin PointsTransactionsRef
    on AutoDisposeFutureProviderRef<List<PointTransactionModel>> {
  /// The parameter `page` of this provider.
  int get page;
}

class _PointsTransactionsProviderElement
    extends AutoDisposeFutureProviderElement<List<PointTransactionModel>>
    with PointsTransactionsRef {
  _PointsTransactionsProviderElement(super.provider);

  @override
  int get page => (origin as PointsTransactionsProvider).page;
}

String _$voucherShopHash() => r'c5af90124d37fb329a7e94d943d5617d0e17ff72';

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

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef VoucherShopRef = AutoDisposeFutureProviderRef<List<VoucherModel>>;
String _$myVouchersHash() => r'a0d9045c255bd8f66c8741b08fc5758dbf83cd76';

/// See also [myVouchers].
@ProviderFor(myVouchers)
const myVouchersProvider = MyVouchersFamily();

/// See also [myVouchers].
class MyVouchersFamily extends Family<AsyncValue<List<UserVoucherModel>>> {
  /// See also [myVouchers].
  const MyVouchersFamily();

  /// See also [myVouchers].
  MyVouchersProvider call({String? status}) {
    return MyVouchersProvider(status: status);
  }

  @override
  MyVouchersProvider getProviderOverride(
    covariant MyVouchersProvider provider,
  ) {
    return call(status: provider.status);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'myVouchersProvider';
}

/// See also [myVouchers].
class MyVouchersProvider
    extends AutoDisposeFutureProvider<List<UserVoucherModel>> {
  /// See also [myVouchers].
  MyVouchersProvider({String? status})
    : this._internal(
        (ref) => myVouchers(ref as MyVouchersRef, status: status),
        from: myVouchersProvider,
        name: r'myVouchersProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$myVouchersHash,
        dependencies: MyVouchersFamily._dependencies,
        allTransitiveDependencies: MyVouchersFamily._allTransitiveDependencies,
        status: status,
      );

  MyVouchersProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.status,
  }) : super.internal();

  final String? status;

  @override
  Override overrideWith(
    FutureOr<List<UserVoucherModel>> Function(MyVouchersRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: MyVouchersProvider._internal(
        (ref) => create(ref as MyVouchersRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        status: status,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<UserVoucherModel>> createElement() {
    return _MyVouchersProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is MyVouchersProvider && other.status == status;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, status.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin MyVouchersRef on AutoDisposeFutureProviderRef<List<UserVoucherModel>> {
  /// The parameter `status` of this provider.
  String? get status;
}

class _MyVouchersProviderElement
    extends AutoDisposeFutureProviderElement<List<UserVoucherModel>>
    with MyVouchersRef {
  _MyVouchersProviderElement(super.provider);

  @override
  String? get status => (origin as MyVouchersProvider).status;
}

// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
