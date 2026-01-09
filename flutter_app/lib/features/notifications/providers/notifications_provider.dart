import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/models/notification_model.dart';
import '../data/notifications_repository.dart';

/// Provider for all notifications (paginated)
final allNotificationsProvider =
    FutureProvider.family<List<NotificationModel>, int>((ref, page) async {
  final repository = ref.watch(notificationsRepositoryProvider);
  return repository.getNotifications(page: page);
});

/// Provider for unread notification count
final unreadCountProvider = FutureProvider<int>((ref) async {
  final repository = ref.watch(notificationsRepositoryProvider);
  return repository.getUnreadCount();
});

/// Provider for unread notifications
final unreadNotificationsProvider =
    FutureProvider<List<NotificationModel>>((ref) async {
  final repository = ref.watch(notificationsRepositoryProvider);
  return repository.getUnreadNotifications();
});

/// State notifier for managing notification actions
class NotificationActionsNotifier extends StateNotifier<AsyncValue<void>> {
  NotificationActionsNotifier(this._repository) : super(const AsyncValue.data(null));

  final NotificationsRepository _repository;

  /// Mark a notification as read and invalidate cache
  Future<void> markAsRead(String notificationId, WidgetRef ref) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      await _repository.markAsRead(notificationId);
      // Invalidate caches
      ref.invalidate(allNotificationsProvider);
      ref.invalidate(unreadCountProvider);
      ref.invalidate(unreadNotificationsProvider);
    });
  }

  /// Mark a notification as unread and invalidate cache
  Future<void> markAsUnread(String notificationId, WidgetRef ref) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      await _repository.markAsUnread(notificationId);
      // Invalidate caches
      ref.invalidate(allNotificationsProvider);
      ref.invalidate(unreadCountProvider);
      ref.invalidate(unreadNotificationsProvider);
    });
  }

  /// Mark all notifications as read and invalidate cache
  Future<void> markAllAsRead(WidgetRef ref) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      await _repository.markAllAsRead();
      // Invalidate caches
      ref.invalidate(allNotificationsProvider);
      ref.invalidate(unreadCountProvider);
      ref.invalidate(unreadNotificationsProvider);
    });
  }

  /// Delete a notification and invalidate cache
  Future<void> deleteNotification(String notificationId, WidgetRef ref) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      await _repository.deleteNotification(notificationId);
      // Invalidate caches
      ref.invalidate(allNotificationsProvider);
      ref.invalidate(unreadCountProvider);
      ref.invalidate(unreadNotificationsProvider);
    });
  }

  /// Register FCM token
  Future<void> registerFcmToken(String token) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() async {
      await _repository.registerFcmToken(token);
    });
  }
}

/// Provider for notification actions
final notificationActionsProvider =
    StateNotifierProvider<NotificationActionsNotifier, AsyncValue<void>>((ref) {
  final repository = ref.watch(notificationsRepositoryProvider);
  return NotificationActionsNotifier(repository);
});

/// Provider to get action notifications (notifications with action data)
final actionNotificationsProvider =
    FutureProvider<List<NotificationModel>>((ref) async {
  final notifications = await ref.watch(allNotificationsProvider(1).future);
  return notifications
      .where((n) => n.action != null && n.isUnread)
      .toList();
});

/// Provider to get regular notifications (without action data)
final regularNotificationsProvider =
    FutureProvider<List<NotificationModel>>((ref) async {
  final notifications = await ref.watch(allNotificationsProvider(1).future);
  return notifications.where((n) => n.action == null).toList();
});

// Extension to make invalidation easier
extension NotificationInvalidation on WidgetRef {
  void invalidateNotifications() {
    invalidate(allNotificationsProvider);
    invalidate(unreadCountProvider);
    invalidate(unreadNotificationsProvider);
    invalidate(actionNotificationsProvider);
    invalidate(regularNotificationsProvider);
  }
}
