import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import 'models/notification_model.dart';

final notificationsRepositoryProvider = Provider<NotificationsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return NotificationsRepository(apiClient);
});

class NotificationsRepository {
  const NotificationsRepository(this._apiClient);

  final ApiClient _apiClient;

  /// Get paginated notifications
  Future<List<NotificationModel>> getNotifications({
    int page = 1,
    int perPage = 20,
  }) async {
    try {
      final response = await _apiClient.get(
        '/driver/notifications',
        queryParameters: {
          'page': page,
          'per_page': perPage,
        },
      );

      final data = response.data['data'] as List;
      return data
          .map((json) => NotificationModel.fromJson(json as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Get unread notification count
  Future<int> getUnreadCount() async {
    try {
      final response = await _apiClient.get('/driver/notifications/unread-count');
      return response.data['data']['unread_count'] as int;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Get all unread notifications
  Future<List<NotificationModel>> getUnreadNotifications() async {
    try {
      final response = await _apiClient.get('/driver/notifications/unread');

      final data = response.data['data'] as List;
      return data
          .map((json) => NotificationModel.fromJson(json as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Mark notification as read
  Future<NotificationModel> markAsRead(String notificationId) async {
    try {
      final response = await _apiClient.patch(
        '/driver/notifications/$notificationId/read',
      );

      return NotificationModel.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Mark notification as unread
  Future<NotificationModel> markAsUnread(String notificationId) async {
    try {
      final response = await _apiClient.patch(
        '/driver/notifications/$notificationId/unread',
      );

      return NotificationModel.fromJson(
        response.data['data'] as Map<String, dynamic>,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Mark all notifications as read
  Future<void> markAllAsRead() async {
    try {
      await _apiClient.patch('/driver/notifications/mark-all-read');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Delete notification
  Future<void> deleteNotification(String notificationId) async {
    try {
      await _apiClient.delete('/driver/notifications/$notificationId');
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Register FCM token
  Future<void> registerFcmToken(String token) async {
    try {
      await _apiClient.post(
        '/driver/notifications/register-token',
        data: {'fcm_token': token},
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Handle API errors
  String _handleError(DioException e) {
    if (e.response?.statusCode == 401) {
      return 'Unauthorized: Please login again';
    }
    return e.message ?? 'Failed to process notification';
  }
}
