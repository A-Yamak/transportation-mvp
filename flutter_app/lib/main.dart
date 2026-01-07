import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

import 'app.dart';
import 'core/api/api_config.dart';
import 'core/notifications/push_notification_service.dart';
import 'firebase_options.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase if configured
  if (DefaultFirebaseOptions.isConfigured) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
    // Set up background message handler
    FirebaseMessaging.onBackgroundMessage(firebaseBackgroundMessageHandler);
  }

  // Initialize Sentry if DSN is configured
  if (ApiConfig.sentryEnabled) {
    await SentryFlutter.init(
      (options) {
        options.dsn = ApiConfig.sentryDsn;
        options.tracesSampleRate = 0.1;
        options.profilesSampleRate = 0.1;
        options.environment =
            ApiConfig.isProduction ? 'production' : 'development';
        options.attachScreenshot = true;
        options.attachViewHierarchy = true;
        options.reportPackages = true;
        options.reportSilentFlutterErrors = true;
      },
      appRunner: () => _runApp(),
    );
  } else {
    // Run without Sentry in development
    _runApp();
  }
}

void _runApp() {
  runApp(
    const ProviderScope(
      child: DriverApp(),
    ),
  );
}
