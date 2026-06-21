// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'notification_providers.dart';

// **************************************************************************
// RiverpodGenerator
// **************************************************************************

String _$unreadNotificationsCountHash() =>
    r'3c0c0c826ef324d55df0e55382d7737c43df7286';

/// See also [unreadNotificationsCount].
@ProviderFor(unreadNotificationsCount)
final unreadNotificationsCountProvider =
    AutoDisposeFutureProvider<int>.internal(
      unreadNotificationsCount,
      name: r'unreadNotificationsCountProvider',
      debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
          ? null
          : _$unreadNotificationsCountHash,
      dependencies: null,
      allTransitiveDependencies: null,
    );

@Deprecated('Will be removed in 3.0. Use Ref instead')
// ignore: unused_element
typedef UnreadNotificationsCountRef = AutoDisposeFutureProviderRef<int>;
String _$studentNotificationsHash() =>
    r'7a333ef3308f84f007fd71038b7c844616e8cb6e';

/// See also [StudentNotifications].
@ProviderFor(StudentNotifications)
final studentNotificationsProvider =
    AutoDisposeAsyncNotifierProvider<
      StudentNotifications,
      List<NotificationModel>
    >.internal(
      StudentNotifications.new,
      name: r'studentNotificationsProvider',
      debugGetCreateSourceHash: const bool.fromEnvironment('dart.vm.product')
          ? null
          : _$studentNotificationsHash,
      dependencies: null,
      allTransitiveDependencies: null,
    );

typedef _$StudentNotifications =
    AutoDisposeAsyncNotifier<List<NotificationModel>>;
// ignore_for_file: type=lint
// ignore_for_file: subtype_of_sealed_class, invalid_use_of_internal_member, invalid_use_of_visible_for_testing_member, deprecated_member_use_from_same_package
