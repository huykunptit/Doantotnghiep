import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../data/models/notification_model.dart';
import '../data/repositories/notification_repository.dart';

part 'notification_providers.g.dart';

@riverpod
class StudentNotifications extends _$StudentNotifications {
  @override
  Future<List<NotificationModel>> build() {
    return ref.read(notificationRepositoryProvider).getNotifications();
  }

  Future<void> markAsRead(int id) async {
    await ref.read(notificationRepositoryProvider).markAsRead(id);
    ref.invalidate(unreadNotificationsCountProvider);
    
    final currentList = state.valueOrNull;
    if (currentList != null) {
      state = AsyncData(currentList.map((n) {
        if (n.id == id) {
          return NotificationModel(
            id: n.id,
            title: n.title,
            message: n.message,
            type: n.type,
            link: n.link,
            readAt: DateTime.now().toIso8601String(),
            createdAt: n.createdAt,
          );
        }
        return n;
      }).toList());
    }
  }

  Future<void> markAllAsRead() async {
    await ref.read(notificationRepositoryProvider).markAllAsRead();
    ref.invalidate(unreadNotificationsCountProvider);
    
    final currentList = state.valueOrNull;
    if (currentList != null) {
      state = AsyncData(currentList.map((n) {
        return NotificationModel(
          id: n.id,
          title: n.title,
          message: n.message,
          type: n.type,
          link: n.link,
          readAt: DateTime.now().toIso8601String(),
          createdAt: n.createdAt,
        );
      }).toList());
    }
  }
}

@riverpod
Future<int> unreadNotificationsCount(UnreadNotificationsCountRef ref) {
  return ref.read(notificationRepositoryProvider).getUnreadCount();
}
