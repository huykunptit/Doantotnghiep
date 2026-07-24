// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'path_detail_provider.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$pathDetailHash() => r'8638c38609abdc836182e001e38765cd7d808531';

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

/// See also [pathDetail].
@ProviderFor(pathDetail)
const pathDetailProvider = PathDetailFamily();

/// See also [pathDetail].
class PathDetailFamily extends Family<AsyncValue<CareerPathDetail>> {
  /// See also [pathDetail].
  const PathDetailFamily();

  /// See also [pathDetail].
  PathDetailProvider call(String slug) {
    return PathDetailProvider(slug);
  }

  @override
  PathDetailProvider getProviderOverride(
    covariant PathDetailProvider provider,
  ) {
    return call(provider.slug);
  }

  static const Iterable<ProviderOrFamily>? _dependencies = null;

  @override
  Iterable<ProviderOrFamily>? get dependencies => _dependencies;

  static const Iterable<ProviderOrFamily>? _allTransitiveDependencies = null;

  @override
  Iterable<ProviderOrFamily>? get allTransitiveDependencies =>
      _allTransitiveDependencies;

  @override
  String? get name => r'pathDetailProvider';
}

/// See also [pathDetail].
class PathDetailProvider extends AutoDisposeFutureProvider<CareerPathDetail> {
  /// See also [pathDetail].
  PathDetailProvider(String slug)
    : this._internal(
        (ref) => pathDetail(ref as PathDetailRef, slug),
        from: pathDetailProvider,
        name: r'pathDetailProvider',
        debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
            ? null
            : _$pathDetailHash,
        dependencies: PathDetailFamily._dependencies,
        allTransitiveDependencies: PathDetailFamily._allTransitiveDependencies,
        slug: slug,
      );

  PathDetailProvider._internal(
    super._createNotifier, {
    required super.name,
    required super.dependencies,
    required super.allTransitiveDependencies,
    required super.debugGetCreateSourceHash,
    required super.from,
    required this.slug,
  }) : super.internal();

  final String slug;

  @override
  Override overrideWith(
    FutureOr<CareerPathDetail> Function(PathDetailRef provider) create,
  ) {
    return ProviderOverride(
      origin: this,
      override: PathDetailProvider._internal(
        (ref) => create(ref as PathDetailRef),
        from: from,
        name: null,
        dependencies: null,
        allTransitiveDependencies: null,
        debugGetCreateSourceHash: null,
        slug: slug,
      ),
    );
  }

  @override
  AutoDisposeFutureProviderElement<CareerPathDetail> createElement() {
    return _PathDetailProviderElement(this);
  }

  @override
  bool operator ==(Object other) {
    return other is PathDetailProvider && other.slug == slug;
  }

  @override
  int get hashCode {
    var hash = _SystemHash.combine(0, runtimeType.hashCode);
    hash = _SystemHash.combine(hash, slug.hashCode);

    return _SystemHash.finish(hash);
  }
}

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
mixin PathDetailRef on AutoDisposeFutureProviderRef<CareerPathDetail> {
  /// The parameter `slug` of this provider.
  String get slug;
}

class _PathDetailProviderElement
    extends AutoDisposeFutureProviderElement<CareerPathDetail>
    with PathDetailRef {
  _PathDetailProviderElement(super.provider);

  @override
  String get slug => (origin as PathDetailProvider).slug;
}

// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
