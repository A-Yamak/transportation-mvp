import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/notifications/push_notification_service.dart';
import '../data/notifications_repository.dart';
import '../data/models/notification_model.dart';

/// Service to integrate FCM with the app's notification system
class FcmIntegrationService {
  final NotificationsRepository _repository;
  final PushNotificationService _pushService;
  final Ref _ref;

  FcmIntegrationService({
    required NotificationsRepository repository,
    required PushNotificationService pushService,
    required Ref ref,
  })  : _repository = repository,
        _pushService = pushService,
        _ref = ref;

  /// Initialize FCM and register token
  Future<void> initialize() async {
    try {
      // Initialize push notifications
      await _pushService.initialize(
        onTokenRefresh: _handleTokenRefresh,
        onMessageReceived: _handleForegroundMessage,
        onMessageOpenedApp: _handleNotificationTap,
      );

      debugPrint('FCM initialized successfully');
    } catch (e) {
      debugPrint('Failed to initialize FCM: $e');
    }
  }

  /// Handle FCM token refresh
  Future<void> _handleTokenRefresh(String token) async {
    try {
      debugPrint('FCM token refreshed: $token');
      await _repository.registerFcmToken(token);
    } catch (e) {
      debugPrint('Failed to register FCM token: $e');
    }
  }

  /// Handle foreground message
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    debugPrint('Foreground message received: ${message.notification?.title}');

    // Extract notification data
    final title = message.notification?.title ?? 'Notification';
    final body = message.notification?.body ?? '';
    final data = message.data;

    debugPrint('Message data: $data');

    // Parse as notification and display
    _displayForegroundNotification(title, body, data);
  }

  /// Handle notification tap (app opened from notification)
  Future<void> _handleNotificationTap(RemoteMessage message) async {
    debugPrint('Notification tapped: ${message.notification?.title}');

    final data = message.data;
    _handleNotificationAction(data);
  }

  /// Display foreground notification (custom handling)
  void _displayForegroundNotification(
    String title,
    String body,
    Map<String, dynamic> data,
  ) {
    // You can use local notifications here to display the notification
    // or refresh the UI directly via Riverpod
    _handleNotificationAction(data);
  }

  /// Handle notification action based on action type
  void _handleNotificationAction(Map<String, dynamic> data) {
    final action = data['action'] as String?;
    final tripId = data['trip_id'] as String?;

    debugPrint('Handling notification action: $action, tripId: $tripId');

    switch (action) {
      case 'open_trip':
        if (tripId != null) {
          // Navigate to trip details
          // This should be handled by the app's navigation
          debugPrint('Navigate to trip: $tripId');
        }
        break;
      case 'open_earnings':
        // Navigate to earnings screen
        debugPrint('Navigate to earnings');
        break;
      default:
        debugPrint('Unknown action: $action');
    }
  }

  /// Dispose resources
  void dispose() {
    _pushService.dispose();
  }
}

/// Provider for FCM integration service
final fcmIntegrationServiceProvider =
    FutureProvider<FcmIntegrationService>((ref) async {
  final repository = ref.watch(notificationsRepositoryProvider);
  final pushService = ref.watch(pushNotificationServiceProvider);

  final service = FcmIntegrationService(
    repository: repository,
    pushService: pushService,
    ref: ref,
  );

  await service.initialize();

  // Return the service for lifecycle management
  ref.onDispose(() {
    service.dispose();
  });

  return service;
});
