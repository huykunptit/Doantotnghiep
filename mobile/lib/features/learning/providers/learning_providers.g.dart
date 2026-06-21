// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'learning_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$lessonDetailHash() => r'e943987bccee5227e7fb4951ed8f24cb1522f5ad';

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

/// See also [lessonDetail].
@ProviderFor(lessonDetail)
const lessonDetailProvider = LessonDetailFamily();

/// See also [lessonDetail].
class LessonDetailFamily extends Family<AsyncValue<LessonDetailModel>> {
  /// See also [lessonDetail].
  const LessonDetailFamily();

  /// See also [lessonDetail].
  LessonDetailProvider call(int courseId, int lessonId) {
    return LessonDetailProvider(courseId, lessonId);
  }

  @override
  LessonDetailProvider getProviderOverride(
    covariant LessonDetailProvider provider,
  ) {
    return call(provider.courseId, provider.lessonId);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'lessonDetailProvider';
}

/// See also [lessonDetail].
class LessonDetailProvider
    extends AutoDisposeFutureProvider<LessonDetailModel> {
  /// See also [lessonDetail].
  LessonDetailProvider(int courseId, int lessonId)
    : this._internal(
        (ref) => lessonDetail(ref as LessonDetailRef, courseId, lessonId),
        from: lessonDetailProvider,
        name: r'lessonDetailProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$lessonDetailHash,
        dependencies: LessonDetailFamily._dependencies,
        allTransitiveDependencies:
            LessonDetailFamily._allTransitiveDependencies,
        courseId: courseId,
        lessonId: lessonId,
      );

  LessonDetailProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.courseId,
    required this.lessonId,
  }) : super.internal();

  final int courseId;
  final int lessonId;

  @override
  Override overrideWith(
    FutureOr<LessonDetailModel> Function(LessonDetailRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: LessonDetailProvider._internal(
        (ref) => create(ref as LessonDetailRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        courseId: courseId,
        lessonId: lessonId,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<LessonDetailModel> createElement() {
    return _LessonDetailProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is LessonDetailProvider &&
        other.courseId == courseId &&
        other.lessonId == lessonId;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, courseId.hashCode);
    hash = _SystemHash.combine(hash, lessonId.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin LessonDetailRef on AutoDisposeFutureProviderRef<LessonDetailModel> {
  /// The parameter `courseId` of this provider.
  int get courseId;

  /// The parameter `lessonId` of this provider.
  int get lessonId;
}

class _LessonDetailProviderElement
    extends AutoDisposeFutureProviderElement<LessonDetailModel>
    with LessonDetailRef {
  _LessonDetailProviderElement(super.provider);

  @override
  int get courseId => (origin as LessonDetailProvider).courseId;
  @override
  int get lessonId => (origin as LessonDetailProvider).lessonId;
}

String _$lessonAttachmentsHash() => r'3533cc6cb210398afceba8290f2e4c201611299e';

/// See also [lessonAttachments].
@ProviderFor(lessonAttachments)
const lessonAttachmentsProvider = LessonAttachmentsFamily();

/// See also [lessonAttachments].
class LessonAttachmentsFamily
    extends Family<AsyncValue<List<AttachmentModel>>> {
  /// See also [lessonAttachments].
  const LessonAttachmentsFamily();

  /// See also [lessonAttachments].
  LessonAttachmentsProvider call(int courseId, int lessonId) {
    return LessonAttachmentsProvider(courseId, lessonId);
  }

  @override
  LessonAttachmentsProvider getProviderOverride(
    covariant LessonAttachmentsProvider provider,
  ) {
    return call(provider.courseId, provider.lessonId);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'lessonAttachmentsProvider';
}

/// See also [lessonAttachments].
class LessonAttachmentsProvider
    extends AutoDisposeFutureProvider<List<AttachmentModel>> {
  /// See also [lessonAttachments].
  LessonAttachmentsProvider(int courseId, int lessonId)
    : this._internal(
        (ref) =>
            lessonAttachments(ref as LessonAttachmentsRef, courseId, lessonId),
        from: lessonAttachmentsProvider,
        name: r'lessonAttachmentsProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$lessonAttachmentsHash,
        dependencies: LessonAttachmentsFamily._dependencies,
        allTransitiveDependencies:
            LessonAttachmentsFamily._allTransitiveDependencies,
        courseId: courseId,
        lessonId: lessonId,
      );

  LessonAttachmentsProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.courseId,
    required this.lessonId,
  }) : super.internal();

  final int courseId;
  final int lessonId;

  @override
  Override overrideWith(
    FutureOr<List<AttachmentModel>> Function(LessonAttachmentsRef provider)
    create,
  ) {
    return ProviderOverride(
      origin: this,
      override: LessonAttachmentsProvider._internal(
        (ref) => create(ref as LessonAttachmentsRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        courseId: courseId,
        lessonId: lessonId,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<AttachmentModel>> createElement() {
    return _LessonAttachmentsProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is LessonAttachmentsProvider &&
        other.courseId == courseId &&
        other.lessonId == lessonId;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, courseId.hashCode);
    hash = _SystemHash.combine(hash, lessonId.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin LessonAttachmentsRef
    on AutoDisposeFutureProviderRef<List<AttachmentModel>> {
  /// The parameter `courseId` of this provider.
  int get courseId;

  /// The parameter `lessonId` of this provider.
  int get lessonId;
}

class _LessonAttachmentsProviderElement
    extends AutoDisposeFutureProviderElement<List<AttachmentModel>>
    with LessonAttachmentsRef {
  _LessonAttachmentsProviderElement(super.provider);

  @override
  int get courseId => (origin as LessonAttachmentsProvider).courseId;
  @override
  int get lessonId => (origin as LessonAttachmentsProvider).lessonId;
}

String _$lessonNotesHash() => r'0f4131c337a81f9c0773a6fd2075569eef28051f';

abstract class _$LessonNotes
    extends BuildlessAutoDisposeAsyncNotifier<List<NoteModel>> {
  late final int courseId;
  late final int lessonId;

  FutureOr<List<NoteModel>> build(int courseId, int lessonId);
}

/// See also [LessonNotes].
@ProviderFor(LessonNotes)
const lessonNotesProvider = LessonNotesFamily();

/// See also [LessonNotes].
class LessonNotesFamily extends Family<AsyncValue<List<NoteModel>>> {
  /// See also [LessonNotes].
  const LessonNotesFamily();

  /// See also [LessonNotes].
  LessonNotesProvider call(int courseId, int lessonId) {
    return LessonNotesProvider(courseId, lessonId);
  }

  @override
  LessonNotesProvider getProviderOverride(
    covariant LessonNotesProvider provider,
  ) {
    return call(provider.courseId, provider.lessonId);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'lessonNotesProvider';
}

/// See also [LessonNotes].
class LessonNotesProvider
    extends AutoDisposeAsyncNotifierProviderImpl<LessonNotes, List<NoteModel>> {
  /// See also [LessonNotes].
  LessonNotesProvider(int courseId, int lessonId)
    : this._internal(
        () => LessonNotes()
          ..courseId = courseId
          ..lessonId = lessonId,
        from: lessonNotesProvider,
        name: r'lessonNotesProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$lessonNotesHash,
        dependencies: LessonNotesFamily._dependencies,
        allTransitiveDependencies: LessonNotesFamily._allTransitiveDependencies,
        courseId: courseId,
        lessonId: lessonId,
      );

  LessonNotesProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.courseId,
    required this.lessonId,
  }) : super.internal();

  final int courseId;
  final int lessonId;

  @override
  FutureOr<List<NoteModel>> runNotifierBuild(covariant LessonNotes notifier) {
    return notifier.build(courseId, lessonId);
  }

  @override
  Override overrideWith(LessonNotes Function() create) {
    return ProviderOverride(
      origin: this,
      override: LessonNotesProvider._internal(
        () => create()
          ..courseId = courseId
          ..lessonId = lessonId,
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        courseId: courseId,
        lessonId: lessonId,
      ),
    );
  }

  @override
  AutoDisposeAsyncNotifierProviderElement<LessonNotes, List<NoteModel>>
  createElement() {
    return _LessonNotesProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is LessonNotesProvider &&
        other.courseId == courseId &&
        other.lessonId == lessonId;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, courseId.hashCode);
    hash = _SystemHash.combine(hash, lessonId.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin LessonNotesRef on AutoDisposeAsyncNotifierProviderRef<List<NoteModel>> {
  /// The parameter `courseId` of this provider.
  int get courseId;

  /// The parameter `lessonId` of this provider.
  int get lessonId;
}

class _LessonNotesProviderElement
    extends
        AutoDisposeAsyncNotifierProviderElement<LessonNotes, List<NoteModel>>
    with LessonNotesRef {
  _LessonNotesProviderElement(super.provider);

  @override
  int get courseId => (origin as LessonNotesProvider).courseId;
  @override
  int get lessonId => (origin as LessonNotesProvider).lessonId;
}

// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
