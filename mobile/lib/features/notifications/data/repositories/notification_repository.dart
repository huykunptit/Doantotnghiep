import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/error/app_exception.dart';
import '../models/notification_model.dart';

part 'notification_repository.g.dart';

@riverpod
NotificationRepository notificationRepository(NotificationRepositoryRef ref) =>
    NotificationRepository(dio: ref.read(apiClientProvider));

class NotificationRepository {
  const NotificationRepository({required this.dio});

  final Dio dio;

  Future<List<NotificationModel>> getNotifications() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/notifications');
      final data = response.data!;
      final list = data['data'] as List<dynamic>? ?? [];
      return list.map((e) => NotificationModel.fromJson(e as Map<String, dynamic>)).toList();
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<int> getUnreadCount() async {
    try {
      final response = await dio.get<Map<String, dynamic>>('/notifications/unread-count');
      return response.data?['count'] as int? ?? 0;
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> markAsRead(int id) async {
    try {
      await dio.put<void>('/notifications/$id/read');
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }

  Future<void> markAllAsRead() async {
    try {
      await dio.put<void>('/notifications/read-all');
    } on DioException catch (e) {
      throw AppException.fromDioException(e);
    }
  }
}
