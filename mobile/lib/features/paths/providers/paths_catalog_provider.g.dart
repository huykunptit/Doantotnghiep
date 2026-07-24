// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'paths_catalog_provider.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$pathsCatalogHash() => r'7ac59b8b087aceea318a33177210d2e7338dee10';

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

/// See also [pathsCatalog].
@ProviderFor(pathsCatalog)
const pathsCatalogProvider = PathsCatalogFamily();

/// See also [pathsCatalog].
class PathsCatalogFamily extends Family<AsyncValue<List<CareerPathListItem>>> {
  /// See also [pathsCatalog].
  const PathsCatalogFamily();

  /// See also [pathsCatalog].
  PathsCatalogProvider call({String? search}) {
    return PathsCatalogProvider(search: search);
  }

  @override
  PathsCatalogProvider getProviderOverride(
    covariant PathsCatalogProvider provider,
  ) {
    return call(search: provider.search);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'pathsCatalogProvider';
}

/// See also [pathsCatalog].
class PathsCatalogProvider
    extends AutoDisposeFutureProvider<List<CareerPathListItem>> {
  /// See also [pathsCatalog].
  PathsCatalogProvider({String? search})
    : this._internal(
        (ref) => pathsCatalog(ref as PathsCatalogRef, search: search),
        from: pathsCatalogProvider,
        name: r'pathsCatalogProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$pathsCatalogHash,
        dependencies: PathsCatalogFamily._dependencies,
        allTransitiveDependencies:
            PathsCatalogFamily._allTransitiveDependencies,
        search: search,
      );

  PathsCatalogProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.search,
  }) : super.internal();

  final String? search;

  @override
  Override overrideWith(
    FutureOr<List<CareerPathListItem>> Function(PathsCatalogRef provider)
    create,
  ) {
    return ProviderOverride(
      origin: this,
      override: PathsCatalogProvider._internal(
        (ref) => create(ref as PathsCatalogRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        search: search,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<List<CareerPathListItem>> createElement() {
    return _PathsCatalogProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is PathsCatalogProvider && other.search == search;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, search.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin PathsCatalogRef
    on AutoDisposeFutureProviderRef<List<CareerPathListItem>> {
  /// The parameter `search` of this provider.
  String? get search;
}

class _PathsCatalogProviderElement
    extends AutoDisposeFutureProviderElement<List<CareerPathListItem>>
    with PathsCatalogRef {
  _PathsCatalogProviderElement(super.provider);

  @override
  String? get search => (origin as PathsCatalogProvider).search;
}

String _$pathsCatalogTotalHash() => r'927c531cbafe4485b74d854b37c1776a208dd4bc';

/// See also [pathsCatalogTotal].
@ProviderFor(pathsCatalogTotal)
const pathsCatalogTotalProvider = PathsCatalogTotalFamily();

/// See also [pathsCatalogTotal].
class PathsCatalogTotalFamily extends Family<AsyncValue<int>> {
  /// See also [pathsCatalogTotal].
  const PathsCatalogTotalFamily();

  /// See also [pathsCatalogTotal].
  PathsCatalogTotalProvider call({String? search}) {
    return PathsCatalogTotalProvider(search: search);
  }

  @override
  PathsCatalogTotalProvider getProviderOverride(
    covariant PathsCatalogTotalProvider provider,
  ) {
    return call(search: provider.search);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'pathsCatalogTotalProvider';
}

/// See also [pathsCatalogTotal].
class PathsCatalogTotalProvider extends AutoDisposeFutureProvider<int> {
  /// See also [pathsCatalogTotal].
  PathsCatalogTotalProvider({String? search})
    : this._internal(
        (ref) => pathsCatalogTotal(ref as PathsCatalogTotalRef, search: search),
        from: pathsCatalogTotalProvider,
        name: r'pathsCatalogTotalProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$pathsCatalogTotalHash,
        dependencies: PathsCatalogTotalFamily._dependencies,
        allTransitiveDependencies:
            PathsCatalogTotalFamily._allTransitiveDependencies,
        search: search,
      );

  PathsCatalogTotalProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.search,
  }) : super.internal();

  final String? search;

  @override
  Override overrideWith(
    FutureOr<int> Function(PathsCatalogTotalRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: PathsCatalogTotalProvider._internal(
        (ref) => create(ref as PathsCatalogTotalRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        search: search,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<int> createElement() {
    return _PathsCatalogTotalProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is PathsCatalogTotalProvider && other.search == search;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, search.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin PathsCatalogTotalRef on AutoDisposeFutureProviderRef<int> {
  /// The parameter `search` of this provider.
  String? get search;
}

class _PathsCatalogTotalProviderElement
    extends AutoDisposeFutureProviderElement<int>
    with PathsCatalogTotalRef {
  _PathsCatalogTotalProviderElement(super.provider);

  @override
  String? get search => (origin as PathsCatalogTotalProvider).search;
}

// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
