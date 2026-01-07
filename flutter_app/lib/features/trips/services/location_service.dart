import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

/// Provider for LocationService
final locationServiceProvider = Provider<LocationService>((ref) {
  return LocationService();
});

/// Service for handling GPS location tracking during trips.
/// Tracks distance traveled using real-time GPS updates.
/// Supports background tracking with foreground service on Android.
class LocationService {
  StreamSubscription<Position>? _locationSubscription;
  Position? _lastPosition;
  double _totalDistanceMeters = 0;
  bool _isTracking = false;

  /// Total distance traveled in kilometers
  double get totalDistanceKm => _totalDistanceMeters / 1000;

  /// Total distance traveled in meters
  double get totalDistanceMeters => _totalDistanceMeters;

  /// Whether tracking is currently active
  bool get isTracking => _isTracking;

  /// Current/last known position
  Position? get lastPosition => _lastPosition;

  /// Check and request location permissions
  /// For background tracking, also requests background location permission
  Future<LocationPermission> checkAndRequestPermission({
    bool requestBackground = false,
  }) async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw LocationServiceException(
        'خدمة الموقع غير مفعلة. يرجى تفعيلها من الإعدادات.',
        LocationServiceError.serviceDisabled,
      );
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw LocationServiceException(
          'تم رفض إذن الموقع. يرجى منح الإذن للتطبيق.',
          LocationServiceError.permissionDenied,
        );
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw LocationServiceException(
        'تم رفض إذن الموقع بشكل دائم. يرجى تفعيله من إعدادات التطبيق.',
        LocationServiceError.permissionDeniedForever,
      );
    }

    // On Android, request background permission if needed for background tracking
    if (requestBackground &&
        Platform.isAndroid &&
        permission == LocationPermission.whileInUse) {
      // For Android 10+, need to request background permission separately
      permission = await Geolocator.requestPermission();
    }

    return permission;
  }

  /// Start tracking location and calculating distance
  /// [enableBackground] - If true, enables background tracking with foreground service
  Future<void> startTracking({bool enableBackground = true}) async {
    if (_isTracking) return;

    await checkAndRequestPermission(requestBackground: enableBackground);

    _totalDistanceMeters = 0;
    _lastPosition = null;
    _isTracking = true;

    // Get initial position
    _lastPosition = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );

    // Configure location settings based on platform
    late LocationSettings locationSettings;

    if (Platform.isAndroid && enableBackground) {
      // Android: Use foreground service for background tracking
      locationSettings = AndroidSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // Update every 10 meters moved
        // Foreground notification keeps tracking alive when app is in background
        foregroundNotificationConfig: const ForegroundNotificationConfig(
          notificationTitle: 'تتبع الرحلة نشط',
          notificationText: 'يتم تتبع موقعك أثناء الرحلة',
          notificationIcon:
              AndroidResource(name: 'ic_launcher', defType: 'mipmap'),
          notificationChannelName: 'Location Tracking',
          enableWakeLock: true,
          enableWifiLock: true,
          setOngoing: true,
        ),
        // Keep tracking accurate even in background
        intervalDuration: const Duration(seconds: 5),
      );
    } else if (Platform.isIOS && enableBackground) {
      // iOS: Use background modes (configured in Info.plist)
      locationSettings = AppleSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
        pauseLocationUpdatesAutomatically: false,
        showBackgroundLocationIndicator: true,
        allowBackgroundLocationUpdates: true,
      );
    } else {
      // Default: Foreground only
      locationSettings = const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
      );
    }

    // Start continuous tracking
    _locationSubscription = Geolocator.getPositionStream(
      locationSettings: locationSettings,
    ).listen(
      _onPositionUpdate,
      onError: _onLocationError,
    );
  }

  /// Handle position updates
  void _onPositionUpdate(Position position) {
    if (_lastPosition != null) {
      final distance = Geolocator.distanceBetween(
        _lastPosition!.latitude,
        _lastPosition!.longitude,
        position.latitude,
        position.longitude,
      );

      // Only add distance if it's reasonable (filter GPS jumps)
      // Ignore movements > 500m in a single update (likely GPS error)
      if (distance < 500) {
        _totalDistanceMeters += distance;
      }
    }
    _lastPosition = position;
  }

  /// Handle location errors
  void _onLocationError(dynamic error) {
    // Log error but keep tracking active
    debugPrint('Location error: $error');
  }

  /// Stop tracking location
  /// This also stops the foreground service on Android
  Future<void> stopTracking() async {
    _isTracking = false;
    await _locationSubscription?.cancel();
    _locationSubscription = null;
  }

  /// Reset distance counter without stopping tracking
  void resetDistance() {
    _totalDistanceMeters = 0;
  }

  /// Get current position (one-time)
  Future<Position> getCurrentPosition() async {
    await checkAndRequestPermission();

    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
      timeLimit: const Duration(seconds: 15),
    );
  }

  /// Calculate distance between two coordinates in meters
  static double calculateDistance(
    double startLat,
    double startLng,
    double endLat,
    double endLng,
  ) {
    return Geolocator.distanceBetween(startLat, startLng, endLat, endLng);
  }

  /// Check if current location is near a destination (within threshold meters)
  Future<bool> isNearDestination(
    double destLat,
    double destLng, {
    double thresholdMeters = 100,
  }) async {
    final currentPosition = await getCurrentPosition();
    final distance = Geolocator.distanceBetween(
      currentPosition.latitude,
      currentPosition.longitude,
      destLat,
      destLng,
    );
    return distance <= thresholdMeters;
  }

  /// Open device location settings
  /// Useful when permission is denied permanently
  Future<bool> openLocationSettings() async {
    return await Geolocator.openLocationSettings();
  }

  /// Open app settings
  /// Useful when permission is denied permanently
  Future<bool> openAppSettings() async {
    return await Geolocator.openAppSettings();
  }

  /// Clean up resources
  void dispose() {
    stopTracking();
  }
}

/// Error types for location service
enum LocationServiceError {
  serviceDisabled,
  permissionDenied,
  permissionDeniedForever,
  timeout,
  unknown,
}

/// Exception for location service errors
class LocationServiceException implements Exception {
  final String message;
  final LocationServiceError errorType;

  LocationServiceException(this.message, this.errorType);

  @override
  String toString() => 'LocationServiceException: $message';
}
