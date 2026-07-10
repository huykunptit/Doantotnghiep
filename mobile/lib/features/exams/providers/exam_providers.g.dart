// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'exam_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$myExamsHash() => r'myexams_placeholder_hash';
String _$examAttemptResultHash() => r'examattemptresult_placeholder_hash';
String _$myOrdersHash() => r'myorders_placeholder_hash';

@ProviderFor(myExams)
const myExamsProvider = MyExamsFamily();

class MyExamsFamily extends Family<AsyncValue<List<ExamListItemModel>>> {
  const MyExamsFamily();

  MyExamsProvider call({String tab = ''}) => MyExamsProvider(tab: tab);

  @override
  MyExamsProvider getProviderOverride(covariant MyExamsProvider provider) =>
      call(tab: provider.tab);

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

class MyExamsProvider extends AutoDisposeFutureProvider<List<ExamListItemModel>> {
  MyExamsProvider({required this.tab})
      : super.internal(
          (ref) => myExams(ref, tab: tab),
          from: myExamsProvider,
          argument: tab,
          debugGetCreateSourceHash:
              const bool.fromEnvironment('dart.vm.product')
                  ? null
                  : _$myExamsHash,
          dependencies: null,
          allTransitiveDependencies: null,
        );

  final String tab;

  @override
  bool operator ==(Object other) =>
      other is MyExamsProvider && other.tab == tab;

  @override
  int get hashCode => tab.hashCode;
}

@ProviderFor(examAttemptResult)
const examAttemptResultProvider = ExamAttemptResultFamily();

class ExamAttemptResultFamily
    extends Family<AsyncValue<ExamResultDetailModel>> {
  const ExamAttemptResultFamily();

  ExamAttemptResultProvider call(int attemptId) =>
      ExamAttemptResultProvider(attemptId);

  @override
  ExamAttemptResultProvider getProviderOverride(
          covariant ExamAttemptResultProvider provider) =>
      call(provider.attemptId);

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

class ExamAttemptResultProvider
    extends AutoDisposeFutureProvider<ExamResultDetailModel> {
  ExamAttemptResultProvider(this.attemptId)
      : super.internal(
          (ref) => examAttemptResult(ref, attemptId),
          from: examAttemptResultProvider,
          argument: attemptId,
          debugGetCreateSourceHash:
              const bool.fromEnvironment('dart.vm.product')
                  ? null
                  : _$examAttemptResultHash,
          dependencies: null,
          allTransitiveDependencies: null,
        );

  final int attemptId;

  @override
  bool operator ==(Object other) =>
      other is ExamAttemptResultProvider && other.attemptId == attemptId;

  @override
  int get hashCode => attemptId.hashCode;
}

@ProviderFor(myOrders)
final myOrdersProvider =
    AutoDisposeFutureProvider<List<OrderModel>>.internal(
  myOrders,
  name: r'myOrdersProvider',
  debugGetCreateSourceHash:
      const bool.fromEnvironment('dart.vm.product') ? null : _$myOrdersHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

@Deprecated('Will be removed in 3.0. Use Ref instead')
typedef MyOrdersRef = AutoDisposeFutureProviderRef<List<OrderModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member
