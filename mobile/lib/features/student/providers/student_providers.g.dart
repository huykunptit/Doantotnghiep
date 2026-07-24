// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'student_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$myTasksHash() => r'703f1f0038758859f196770cbbe46d2f83837954';

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

/// See also [myTasks].
@ProviderFor(myTasks)
const myTasksProvider = MyTasksFamily();

/// See also [myTasks].
class MyTasksFamily extends Family<AsyncValue<List<TaskModel>>> {
  /// See also [myTasks].
  const MyTasksFamily();

  /// See also [myTasks].
  MyTasksProvider call({bool? done}) {
    return MyTasksProvider(done: done);
  }

  @override
  MyTasksProvider getProviderOverride(covariant MyTasksProvider provider) {
    return call(done: provider.done);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'myTasksProvider';
}

/// See also [myTasks].
class MyTasksProvider extends AutoDisposeFutureProvider<List<TaskModel>> {
  /// See also [myTasks].
  MyTasksProvider({bool? done})
    : this._internal(
        (ref) => myTasks(ref as MyTasksRef, done: done),
        from: myTasksProvider,
        name: r'myTasksProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$myTasksHash,
        dependencies: MyTasksFamily._dependencies,
        allTransitiveDependencies: MyTasksFamily._allTransitiveDependencies,
        done: done,
      );

  MyTasksProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.done,
  }) : super.internal();

  final bool? done;

  @override
  Override overrideWith(
    FutureOr<List<TaskModel>> Function(MyTasksRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: MyTasksProvider._internal(
        (ref) => create(ref as MyTasksRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        done: done,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<TaskModel>> createElement() {
    return _MyTasksProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is MyTasksProvider && other.done == done;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, done.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin MyTasksRef on AutoDisposeFutureProviderRef<List<TaskModel>> {
  /// The parameter `done` of this provider.
  bool? get done;
}

class _MyTasksProviderElement
    extends AutoDisposeFutureProviderElement<List<TaskModel>>
    with MyTasksRef {
  _MyTasksProviderElement(super.provider);

  @override
  bool? get done => (origin as MyTasksProvider).done;
}

String _$examScheduleHash() => r'f144da7dcf6fae6cd94c4c34d5c6c2c4d85837f8';

/// See also [examSchedule].
@ProviderFor(examSchedule)
final examScheduleProvider =
    AutoDisposeFutureProvider<List<CalendarExamModel>>.internal(
      examSchedule,
      name: r'examScheduleProvider',
      debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
          ? null
          : _$examScheduleHash,
      dependencies: null,
      allTransitiveDependencies: null,
    );

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef ExamScheduleRef = AutoDisposeFutureProviderRef<List<CalendarExamModel>>;
String _$myLibraryHash() => r'9dbbcd129ed8d943bb64fcb6cd72c35efde5f1a4';

/// See also [myLibrary].
@ProviderFor(myLibrary)
final myLibraryProvider =
    AutoDisposeFutureProvider<List<LibraryAttachmentModel>>.internal(
      myLibrary,
      name: r'myLibraryProvider',
      debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
          ? null
          : _$myLibraryHash,
      dependencies: null,
      allTransitiveDependencies: null,
    );

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef MyLibraryRef =
    AutoDisposeFutureProviderRef<List<LibraryAttachmentModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
