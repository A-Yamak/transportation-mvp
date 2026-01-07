import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Provider for PushNotificationService
final pushNotificationServiceProvider = Provider<PushNotificationService>((ref) {
  return PushNotificationService();
});

/// Provider for the current FCM token
final fcmTokenProvider = StateProvider<String?>((ref) => null);

/// Service for handling Firebase Cloud Messaging push notifications
class PushNotificationService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  StreamSubscription<RemoteMessage>? _foregroundSubscription;
  StreamSubscription<String>? _tokenRefreshSubscription;

  /// Initialize push notifications
  /// Call this after Firebase.initializeApp()
  Future<void> initialize({
    required void Function(String token) onTokenRefresh,
    required void Function(RemoteMessage message) onMessageReceived,
    void Function(RemoteMessage message)? onMessageOpenedApp,
  }) async {
    // Request permission
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      debugPrint('Push notification permission denied');
      return;
    }

    // Get initial token
    final token = await _messaging.getToken();
    if (token != null) {
      debugPrint('FCM Token: $token');
      onTokenRefresh(token);
    }

    // Listen for token refresh
    _tokenRefreshSubscription = _messaging.onTokenRefresh.listen(onTokenRefresh);

    // Handle foreground messages
    _foregroundSubscription = FirebaseMessaging.onMessage.listen(onMessageReceived);

    // Handle notification tap when app is in background
    FirebaseMessaging.onMessageOpenedApp.listen(onMessageOpenedApp ?? onMessageReceived);

    // Check if app was opened from a notification
    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      (onMessageOpenedApp ?? onMessageReceived)(initialMessage);
    }
  }

  /// Get the current FCM token
  Future<String?> getToken() async {
    return await _messaging.getToken();
  }

  /// Subscribe to a topic (e.g., 'driver_updates', 'trip_123')
  Future<void> subscribeToTopic(String topic) async {
    await _messaging.subscribeToTopic(topic);
    debugPrint('Subscribed to topic: $topic');
  }

  /// Unsubscribe from a topic
  Future<void> unsubscribeFromTopic(String topic) async {
    await _messaging.unsubscribeFromTopic(topic);
    debugPrint('Unsubscribed from topic: $topic');
  }

  /// Clean up resources
  void dispose() {
    _foregroundSubscription?.cancel();
    _tokenRefreshSubscription?.cancel();
  }
}

/// Background message handler - must be a top-level function
/// Add this to main.dart: FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);
@pragma('vm:entry-point')
Future<void> firebaseBackgroundMessageHandler(RemoteMessage message) async {
  // Handle background message
  // Note: Cannot access providers here as app might not be running
  debugPrint('Background message: ${message.messageId}');
  debugPrint('Title: ${message.notification?.title}');
  debugPrint('Body: ${message.notification?.body}');
  debugPrint('Data: ${message.data}');
}
