import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

/// Provider for LocationService
final locationServiceProvider = Provider<LocationService>((ref) {
  return LocationService();
});

/// Service for handling GPS location tracking during trips.
/// Tracks distance traveled using real-time GPS updates.
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
  Future<LocationPermission> checkAndRequestPermission() async {
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

    return permission;
  }

  /// Start tracking location and calculating distance
  Future<void> startTracking() async {
    if (_isTracking) return;

    await checkAndRequestPermission();

    _totalDistanceMeters = 0;
    _lastPosition = null;
    _isTracking = true;

    // Get initial position
    _lastPosition = await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );

    // Start continuous tracking
    _locationSubscription = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // Update every 10 meters moved
      ),
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
    print('Location error: $error');
  }

  /// Stop tracking location
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
