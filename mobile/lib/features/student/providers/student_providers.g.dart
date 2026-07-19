// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'student_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$myTasksHash() => r'mytasks_placeholder_hash';
String _$examScheduleHash() => r'examschedule_placeholder_hash';
String _$myLibraryHash() => r'mylibrary_placeholder_hash';

@ProviderFor(myTasks)
const myTasksProvider = MyTasksFamily();

class MyTasksFamily extends Family<AsyncValue<List<TaskModel>>> {
  const MyTasksFamily();

  MyTasksProvider call({bool? done}) => MyTasksProvider(done: done);

  @override
  MyTasksProvider getProviderOverride(covariant MyTasksProvider provider) =>
      call(done: provider.done);

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

class MyTasksProvider extends AutoDisposeFutureProvider<List<TaskModel>> {
  MyTasksProvider({required this.done})
      : super.internal(
          (ref) => myTasks(ref, done: done),
          from: myTasksProvider,
          name: r'myTasksProvider',
          argument: done,
          debugGetCreateSourceHash:
              const bool.fromEnvironment('dart.vm.product')
                  ? null
                  : _$myTasksHash,
          dependencies: null,
          allTransitiveDependencies: null,
        );
  final bool? done;

  @override
  bool operator ==(Object other) =>
      other is MyTasksProvider && other.done == done;
  @override
  int get hashCode => done.hashCode;
}

@ProviderFor(examSchedule)
final examScheduleProvider =
    AutoDisposeFutureProvider<List<CalendarExamModel>>.internal(
  examSchedule,
  name: r'examScheduleProvider',
  debugGetCreateSourceHash:
      const bool.fromEnvironment('dart.vm.product') ? null : _$examScheduleHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

@Deprecated('Will be removed in 3.0. Use Ref instead')
typedef ExamScheduleRef = AutoDisposeFutureProviderRef<List<CalendarExamModel>>;

@ProviderFor(myLibrary)
final myLibraryProvider =
    AutoDisposeFutureProvider<List<LibraryAttachmentModel>>.internal(
  myLibrary,
  name: r'myLibraryProvider',
  debugGetCreateSourceHash:
      const bool.fromEnvironment('dart.vm.product') ? null : _$myLibraryHash,
  dependencies: null,
  allTransitiveDependencies: null,
);

@Deprecated('Will be removed in 3.0. Use Ref instead')
typedef MyLibraryRef = AutoDisposeFutureProviderRef<List<LibraryAttachmentModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member
