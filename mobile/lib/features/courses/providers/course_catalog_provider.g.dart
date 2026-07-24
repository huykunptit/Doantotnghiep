// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'course_catalog_provider.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$courseCatalogHash() => r'ecc6f70d6bfa4ccb6988b332c77ea791e770b1df';

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

/// See also [courseCatalog].
@ProviderFor(courseCatalog)
const courseCatalogProvider = CourseCatalogFamily();

/// See also [courseCatalog].
class CourseCatalogFamily
    extends Family<AsyncValue<List<CourseListItemModel>>> {
  /// See also [courseCatalog].
  const CourseCatalogFamily();

  /// See also [courseCatalog].
  CourseCatalogProvider call({String? search, int? categoryId}) {
    return CourseCatalogProvider(search: search, categoryId: categoryId);
  }

  @override
  CourseCatalogProvider getProviderOverride(
    covariant CourseCatalogProvider provider,
  ) {
    return call(search: provider.search, categoryId: provider.categoryId);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'courseCatalogProvider';
}

/// See also [courseCatalog].
class CourseCatalogProvider
    extends AutoDisposeFutureProvider<List<CourseListItemModel>> {
  /// See also [courseCatalog].
  CourseCatalogProvider({String? search, int? categoryId})
    : this._internal(
        (ref) => courseCatalog(
          ref as CourseCatalogRef,
          search: search,
          categoryId: categoryId,
        ),
        from: courseCatalogProvider,
        name: r'courseCatalogProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$courseCatalogHash,
        dependencies: CourseCatalogFamily._dependencies,
        allTransitiveDependencies:
            CourseCatalogFamily._allTransitiveDependencies,
        search: search,
        categoryId: categoryId,
      );

  CourseCatalogProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.search,
    required this.categoryId,
  }) : super.internal();

  final String? search;
  final int? categoryId;

  @override
  Override overrideWith(
    FutureOr<List<CourseListItemModel>> Function(CourseCatalogRef provider)
    create,
  ) {
    return ProviderOverride(
      origin: this,
      override: CourseCatalogProvider._internal(
        (ref) => create(ref as CourseCatalogRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        search: search,
        categoryId: categoryId,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<CourseListItemModel>> createElement() {
    return _CourseCatalogProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is CourseCatalogProvider &&
        other.search == search &&
        other.categoryId == categoryId;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, search.hashCode);
    hash = _SystemHash.combine(hash, categoryId.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin CourseCatalogRef
    on AutoDisposeFutureProviderRef<List<CourseListItemModel>> {
  /// The parameter `search` of this provider.
  String? get search;

  /// The parameter `categoryId` of this provider.
  int? get categoryId;
}

class _CourseCatalogProviderElement
    extends AutoDisposeFutureProviderElement<List<CourseListItemModel>>
    with CourseCatalogRef {
  _CourseCatalogProviderElement(super.provider);

  @override
  String? get search => (origin as CourseCatalogProvider).search;
  @override
  int? get categoryId => (origin as CourseCatalogProvider).categoryId;
}

String _$courseCategoriesHash() => r'd38a21b6a04d78d739e5378a3fdff224c7376e63';

/// See also [courseCategories].
@ProviderFor(courseCategories)
final courseCategoriesProvider =
    AutoDisposeFutureProvider<List<CategoryModel>>.internal(
      courseCategories,
      name: r'courseCategoriesProvider',
      debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
          ? null
          : _$courseCategoriesHash,
      dependencies: null,
      allTransitiveDependencies: null,
    );

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef CourseCategoriesRef = AutoDisposeFutureProviderRef<List<CategoryModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
