// Firebase configuration options
// Generated placeholder - replace with actual values from Firebase Console
//
// To generate this file properly:
// 1. Go to Firebase Console: https://console.firebase.google.com
// 2. Create a new project or select existing
// 3. Add Android app with package name: com.alsabiqoon.driver
// 4. Add iOS app with bundle ID: com.alsabiqoon.driver
// 5. Download google-services.json to android/app/
// 6. Download GoogleService-Info.plist to ios/Runner/
// 7. Run: flutterfire configure
//
// Or manually fill in the values below from Firebase Console -> Project Settings

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart'
    show defaultTargetPlatform, kIsWeb, TargetPlatform;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      throw UnsupportedError('Web platform is not supported');
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        throw UnsupportedError('Unsupported platform');
    }
  }

  // Android Firebase options
  // Get these from Firebase Console -> Project Settings -> Your apps -> Android
  static const FirebaseOptions android = FirebaseOptions(
    apiKey: '', // TODO: Add your Android API key
    appId: '', // TODO: Add your Android App ID (e.g., 1:123456789:android:abc123)
    messagingSenderId: '', // TODO: Add your Sender ID
    projectId: '', // TODO: Add your Project ID (e.g., transportation-mvp)
    storageBucket: '', // TODO: Add your Storage Bucket (e.g., transportation-mvp.appspot.com)
  );

  // iOS Firebase options
  // Get these from Firebase Console -> Project Settings -> Your apps -> iOS
  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: '', // TODO: Add your iOS API key
    appId: '', // TODO: Add your iOS App ID (e.g., 1:123456789:ios:abc123)
    messagingSenderId: '', // TODO: Add your Sender ID
    projectId: '', // TODO: Add your Project ID
    storageBucket: '', // TODO: Add your Storage Bucket
    iosBundleId: 'com.alsabiqoon.driver',
  );

  /// Check if Firebase is configured
  static bool get isConfigured {
    try {
      final options = currentPlatform;
      return options.apiKey.isNotEmpty && options.appId.isNotEmpty;
    } catch (_) {
      return false;
    }
  }
}
