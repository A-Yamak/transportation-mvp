import 'dart:math';
import 'package:geolocator/geolocator.dart';

/// Represents a GPS coordinate point
class LatLng {
  final double latitude;
  final double longitude;

  LatLng({required this.latitude, required this.longitude});

  /// Convert to Geolocator Position format
  static LatLng fromPosition(Position position) {
    return LatLng(
      latitude: position.latitude,
      longitude: position.longitude,
    );
  }

  @override
  String toString() => 'LatLng($latitude, $longitude)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is LatLng &&
          runtimeType == other.runtimeType &&
          latitude == other.latitude &&
          longitude == other.longitude;

  @override
  int get hashCode => latitude.hashCode ^ longitude.hashCode;
}

/// Service for GPS tracking and distance calculation
class LocationService {
  /// List of all GPS positions tracked during a trip
  final List<LatLng> _positions = [];

  /// Current total distance in meters
  double _totalDistanceMeters = 0.0;

  /// Whether currently tracking
  bool _isTracking = false;

  /// Get all tracked positions
  List<LatLng> get positions => List.unmodifiable(_positions);

  /// Get current total distance in kilometers
  double get totalKilometers => _totalDistanceMeters / 1000;

  /// Get current total distance in meters
  double get totalMeters => _totalDistanceMeters;

  /// Get whether currently tracking
  bool get isTracking => _isTracking;

  /// Get the last recorded position, or null if none
  LatLng? get lastPosition => _positions.isNotEmpty ? _positions.last : null;

  /// Check if location permissions are granted
  static Future<bool> checkPermission() async {
    final status = await Geolocator.checkPermission();
    return status == LocationPermission.whileInUse ||
        status == LocationPermission.always;
  }

  /// Request location permissions
  static Future<bool> requestPermission() async {
    final status = await Geolocator.requestPermission();
    return status == LocationPermission.whileInUse ||
        status == LocationPermission.always;
  }

  /// Initialize tracking, optionally with an initial position
  Future<void> startTracking({LatLng? initialPosition}) async {
    if (_isTracking) {
      return;
    }

    _positions.clear();
    _totalDistanceMeters = 0.0;
    _isTracking = true;

    if (initialPosition != null) {
      _positions.add(initialPosition);
    }
  }

  /// Stop tracking and return total KM driven
  Future<double> stopTracking() async {
    _isTracking = false;
    return totalKilometers;
  }

  /// Add a GPS position to the tracking list
  /// Automatically calculates distance from last position
  void recordPosition(LatLng position) {
    if (!_isTracking) {
      return;
    }

    if (_positions.isNotEmpty) {
      final distance = _calculateDistance(_positions.last, position);
      _totalDistanceMeters += distance;
    }

    _positions.add(position);
  }

  /// Record current device position
  /// Returns the recorded position, or null if unable to get location
  Future<LatLng?> recordCurrentPosition() async {
    if (!_isTracking) {
      return null;
    }

    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      final latLng = LatLng(
        latitude: position.latitude,
        longitude: position.longitude,
      );

      recordPosition(latLng);
      return latLng;
    } catch (e) {
      return null;
    }
  }

  /// Reset tracking (clear all positions and distances)
  void reset() {
    _positions.clear();
    _totalDistanceMeters = 0.0;
    _isTracking = false;
  }

  /// Calculate distance between two points using Haversine formula
  /// Returns distance in meters
  double _calculateDistance(LatLng point1, LatLng point2) {
    const earthRadiusMeters = 6371000; // Earth's radius in meters

    final lat1Rad = _degreesToRadians(point1.latitude);
    final lat2Rad = _degreesToRadians(point2.latitude);
    final deltaLatRad = _degreesToRadians(point2.latitude - point1.latitude);
    final deltaLngRad = _degreesToRadians(point2.longitude - point1.longitude);

    final a = sin(deltaLatRad / 2) * sin(deltaLatRad / 2) +
        cos(lat1Rad) *
            cos(lat2Rad) *
            sin(deltaLngRad / 2) *
            sin(deltaLngRad / 2);

    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    final distance = earthRadiusMeters * c;

    return distance;
  }

  /// Convert degrees to radians
  static double _degreesToRadians(double degrees) {
    return degrees * pi / 180;
  }

  /// Calculate distance between two points (static method)
  /// Useful for testing or one-off calculations
  static double calculateDistance(LatLng point1, LatLng point2) {
    const earthRadiusMeters = 6371000;

    final lat1Rad = point1.latitude * pi / 180;
    final lat2Rad = point2.latitude * pi / 180;
    final deltaLatRad = (point2.latitude - point1.latitude) * pi / 180;
    final deltaLngRad = (point2.longitude - point1.longitude) * pi / 180;

    final a = sin(deltaLatRad / 2) * sin(deltaLatRad / 2) +
        cos(lat1Rad) *
            cos(lat2Rad) *
            sin(deltaLngRad / 2) *
            sin(deltaLngRad / 2);

    final c = 2 * atan2(sqrt(a), sqrt(1 - a));
    final distance = earthRadiusMeters * c;

    return distance;
  }

  /// Get distance between two points in kilometers
  static double calculateDistanceKm(LatLng point1, LatLng point2) {
    return calculateDistance(point1, point2) / 1000;
  }
}
