// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'exam_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$myExamsHash() => r'031b2e15b7753dc145702948db9c3f6592b17683';

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

/// See also [myExams].
@ProviderFor(myExams)
const myExamsProvider = MyExamsFamily();

/// See also [myExams].
class MyExamsFamily extends Family<AsyncValue<List<ExamListItemModel>>> {
  /// See also [myExams].
  const MyExamsFamily();

  /// See also [myExams].
  MyExamsProvider call({String tab = ''}) {
    return MyExamsProvider(tab: tab);
  }

  @override
  MyExamsProvider getProviderOverride(covariant MyExamsProvider provider) {
    return call(tab: provider.tab);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'myExamsProvider';
}

/// See also [myExams].
class MyExamsProvider
    extends AutoDisposeFutureProvider<List<ExamListItemModel>> {
  /// See also [myExams].
  MyExamsProvider({String tab = ''})
    : this._internal(
        (ref) => myExams(ref as MyExamsRef, tab: tab),
        from: myExamsProvider,
        name: r'myExamsProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$myExamsHash,
        dependencies: MyExamsFamily._dependencies,
        allTransitiveDependencies: MyExamsFamily._allTransitiveDependencies,
        tab: tab,
      );

  MyExamsProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.tab,
  }) : super.internal();

  final String tab;

  @override
  Override overrideWith(
    FutureOr<List<ExamListItemModel>> Function(MyExamsRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: MyExamsProvider._internal(
        (ref) => create(ref as MyExamsRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        tab: tab,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<ExamListItemModel>> createElement() {
    return _MyExamsProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is MyExamsProvider && other.tab == tab;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, tab.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin MyExamsRef on AutoDisposeFutureProviderRef<List<ExamListItemModel>> {
  /// The parameter `tab` of this provider.
  String get tab;
}

class _MyExamsProviderElement
    extends AutoDisposeFutureProviderElement<List<ExamListItemModel>>
    with MyExamsRef {
  _MyExamsProviderElement(super.provider);

  @override
  String get tab => (origin as MyExamsProvider).tab;
}

String _$examAttemptResultHash() => r'e92804f8336892355a4d3a53250c08b4c8485e74';

/// See also [examAttemptResult].
@ProviderFor(examAttemptResult)
const examAttemptResultProvider = ExamAttemptResultFamily();

/// See also [examAttemptResult].
class ExamAttemptResultFamily
    extends Family<AsyncValue<ExamResultDetailModel>> {
  /// See also [examAttemptResult].
  const ExamAttemptResultFamily();

  /// See also [examAttemptResult].
  ExamAttemptResultProvider call(int attemptId) {
    return ExamAttemptResultProvider(attemptId);
  }

  @override
  ExamAttemptResultProvider getProviderOverride(
    covariant ExamAttemptResultProvider provider,
  ) {
    return call(provider.attemptId);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'examAttemptResultProvider';
}

/// See also [examAttemptResult].
class ExamAttemptResultProvider
    extends AutoDisposeFutureProvider<ExamResultDetailModel> {
  /// See also [examAttemptResult].
  ExamAttemptResultProvider(int attemptId)
    : this._internal(
        (ref) => examAttemptResult(ref as ExamAttemptResultRef, attemptId),
        from: examAttemptResultProvider,
        name: r'examAttemptResultProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$examAttemptResultHash,
        dependencies: ExamAttemptResultFamily._dependencies,
        allTransitiveDependencies:
            ExamAttemptResultFamily._allTransitiveDependencies,
        attemptId: attemptId,
      );

  ExamAttemptResultProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.attemptId,
  }) : super.internal();

  final int attemptId;

  @override
  Override overrideWith(
    FutureOr<ExamResultDetailModel> Function(ExamAttemptResultRef provider)
    create,
  ) {
    return ProviderOverride(
      origin: this,
      override: ExamAttemptResultProvider._internal(
        (ref) => create(ref as ExamAttemptResultRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        attemptId: attemptId,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<ExamResultDetailModel> createElement() {
    return _ExamAttemptResultProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is ExamAttemptResultProvider && other.attemptId == attemptId;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, attemptId.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin ExamAttemptResultRef
    on AutoDisposeFutureProviderRef<ExamResultDetailModel> {
  /// The parameter `attemptId` of this provider.
  int get attemptId;
}

class _ExamAttemptResultProviderElement
    extends AutoDisposeFutureProviderElement<ExamResultDetailModel>
    with ExamAttemptResultRef {
  _ExamAttemptResultProviderElement(super.provider);

  @override
  int get attemptId => (origin as ExamAttemptResultProvider).attemptId;
}

String _$myOrdersHash() => r'0a5de11c435c193bf3e832d05b75006be2c0cc69';

/// See also [myOrders].
@ProviderFor(myOrders)
final myOrdersProvider = AutoDisposeFutureProvider<List<OrderModel>>.internal(
  myOrders,
  name: r'myOrdersProvider',
  debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
      ? null
      : _$myOrdersHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef MyOrdersRef = AutoDisposeFutureProviderRef<List<OrderModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
