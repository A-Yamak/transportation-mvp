import 'package:flutter_test/flutter_test.dart';
import 'package:driver_app/core/services/location_service.dart';

void main() {
  group('LatLng Tests', () {
    test('creates LatLng with coordinates', () {
      // Arrange & Act
      final point = LatLng(latitude: 31.9454, longitude: 35.9284);

      // Assert
      expect(point.latitude, 31.9454);
      expect(point.longitude, 35.9284);
    });

    test('LatLng equality', () {
      // Arrange
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point3 = LatLng(latitude: 31.9455, longitude: 35.9284);

      // Assert
      expect(point1 == point2, true);
      expect(point1 == point3, false);
    });

    test('LatLng toString', () {
      // Arrange
      final point = LatLng(latitude: 31.9454, longitude: 35.9284);

      // Act
      final str = point.toString();

      // Assert
      expect(str.contains('LatLng'), true);
      expect(str.contains('31.9454'), true);
    });
  });

  group('LocationService Tests', () {
    late LocationService service;

    setUp(() {
      service = LocationService();
    });

    test('initializes with empty positions', () {
      // Assert
      expect(service.positions.length, 0);
      expect(service.totalKilometers, 0.0);
      expect(service.isTracking, false);
    });

    test('startTracking initializes tracking', () async {
      // Act
      await service.startTracking();

      // Assert
      expect(service.isTracking, true);
      expect(service.positions.length, 0);
    });

    test('startTracking with initial position', () async {
      // Arrange
      final initialPoint = LatLng(latitude: 31.9454, longitude: 35.9284);

      // Act
      await service.startTracking(initialPosition: initialPoint);

      // Assert
      expect(service.isTracking, true);
      expect(service.positions.length, 1);
      expect(service.lastPosition, initialPoint);
    });

    test('recordPosition adds to tracking list', () async {
      // Arrange
      await service.startTracking();
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.9455, longitude: 35.9285);

      // Act
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Assert
      expect(service.positions.length, 2);
      expect(service.lastPosition, point2);
    });

    test('recordPosition calculates distance', () async {
      // Arrange - Two points ~1 degree apart at equator ~ 111km
      await service.startTracking();
      final point1 = LatLng(latitude: 0.0, longitude: 0.0);
      final point2 = LatLng(latitude: 0.0, longitude: 1.0);

      // Act
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Assert - At equator, 1 degree = ~111km
      expect(service.totalKilometers > 100, true);
      expect(service.totalKilometers < 120, true);
    });

    test('stopTracking returns total KM', () async {
      // Arrange
      await service.startTracking();
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.9464, longitude: 35.9294);
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Act
      final totalKm = await service.stopTracking();

      // Assert
      expect(totalKm, greaterThan(0));
      expect(service.isTracking, false);
    });

    test('recordPosition when not tracking does nothing', () async {
      // Act
      service.recordPosition(LatLng(latitude: 31.9454, longitude: 35.9284));

      // Assert
      expect(service.positions.length, 0);
      expect(service.totalKilometers, 0.0);
    });

    test('reset clears all data', () async {
      // Arrange
      await service.startTracking();
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.9464, longitude: 35.9294);
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Act
      service.reset();

      // Assert
      expect(service.positions.length, 0);
      expect(service.totalKilometers, 0.0);
      expect(service.isTracking, false);
    });

    test('lastPosition returns null when no positions', () {
      // Assert
      expect(service.lastPosition, isNull);
    });

    test('lastPosition returns last added point', () async {
      // Arrange
      await service.startTracking();
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.9464, longitude: 35.9294);

      // Act
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Assert
      expect(service.lastPosition, point2);
    });

    group('Haversine Distance Calculation', () {
      test('same point returns zero distance', () {
        // Arrange
        final point = LatLng(latitude: 31.9454, longitude: 35.9284);

        // Act
        final distance = LocationService.calculateDistance(point, point);

        // Assert
        expect(distance, lessThan(1)); // essentially zero (might have rounding)
      });

      test('equator 1 degree apart', () {
        // Arrange
        final point1 = LatLng(latitude: 0.0, longitude: 0.0);
        final point2 = LatLng(latitude: 0.0, longitude: 1.0);

        // Act
        final distanceKm = LocationService.calculateDistanceKm(point1, point2);

        // Assert - At equator, 1 degree = ~111.32km
        expect(distanceKm > 111, true);
        expect(distanceKm < 112, true);
      });

      test('Amman to Dead Sea distance', () {
        // Arrange - Amman to Dead Sea (actual coordinates)
        final amman = LatLng(latitude: 31.9454, longitude: 35.9284);
        final deadSea = LatLng(latitude: 31.5, longitude: 35.5);

        // Act
        final distanceKm =
            LocationService.calculateDistanceKm(amman, deadSea);

        // Assert - Should be around 50-60 km
        expect(distanceKm > 40, true);
        expect(distanceKm < 100, true);
      });

      test('distance is symmetric', () {
        // Arrange
        final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
        final point2 = LatLng(latitude: 32.0, longitude: 36.0);

        // Act
        final dist1 = LocationService.calculateDistance(point1, point2);
        final dist2 = LocationService.calculateDistance(point2, point1);

        // Assert
        expect((dist1 - dist2).abs(), lessThan(1)); // within 1 meter
      });

      test('calculateDistanceKm converts to kilometers', () {
        // Arrange
        final point1 = LatLng(latitude: 0.0, longitude: 0.0);
        final point2 = LatLng(latitude: 0.0, longitude: 1.0);

        // Act
        final distanceMeters =
            LocationService.calculateDistance(point1, point2);
        final distanceKm = LocationService.calculateDistanceKm(point1, point2);

        // Assert
        expect(distanceKm, closeTo(distanceMeters / 1000, 0.001));
      });
    });

    group('Complex Trip Scenarios', () {
      test('tracks multi-stop trip with cumulative distance', () async {
        // Arrange
        await service.startTracking();
        final points = [
          LatLng(latitude: 31.9454, longitude: 35.9284), // Start
          LatLng(latitude: 31.9500, longitude: 35.9300), // Stop 1
          LatLng(latitude: 31.9550, longitude: 35.9350), // Stop 2
          LatLng(latitude: 31.9600, longitude: 35.9400), // Stop 3
        ];

        // Act
        for (final point in points) {
          service.recordPosition(point);
        }

        // Assert
        expect(service.positions.length, 4);
        expect(service.totalKilometers, greaterThan(0));
        expect(service.lastPosition, points.last);
      });

      test('distance accumulates correctly', () async {
        // Arrange
        await service.startTracking();

        // Two short segments
        final point1 = LatLng(latitude: 0.0, longitude: 0.0);
        final point2 = LatLng(latitude: 0.0, longitude: 0.5);
        final point3 = LatLng(latitude: 0.0, longitude: 1.0);

        // Act
        service.recordPosition(point1);
        service.recordPosition(point2);
        final km1 = service.totalKilometers;
        service.recordPosition(point3);
        final km2 = service.totalKilometers;

        // Assert
        expect(km2, greaterThan(km1));
        expect(km2, closeTo(km1 * 2, km1 * 0.1)); // roughly double
      });

      test('handles circular route', () async {
        // Arrange
        await service.startTracking();
        final square = [
          LatLng(latitude: 0.0, longitude: 0.0),
          LatLng(latitude: 0.01, longitude: 0.0),
          LatLng(latitude: 0.01, longitude: 0.01),
          LatLng(latitude: 0.0, longitude: 0.01),
          LatLng(latitude: 0.0, longitude: 0.0), // Return to start
        ];

        // Act
        for (final point in square) {
          service.recordPosition(point);
        }

        // Assert - Should have traversed approximately a square perimeter
        expect(service.totalKilometers, greaterThan(0));
        expect(service.positions.length, 5);
      });
    });

    test('positions are immutable (returns copy)', () async {
      // Arrange
      await service.startTracking();
      final point = LatLng(latitude: 31.9454, longitude: 35.9284);
      service.recordPosition(point);

      // Act
      final positions1 = service.positions;
      final positions2 = service.positions;

      // Assert - Should be different list objects (immutable)
      expect(identical(positions1, positions2), false);
      expect(positions1.length, positions2.length);
    });
  });

  group('Edge Cases', () {
    late LocationService service;

    setUp(() {
      service = LocationService();
    });

    test('multiple startTracking calls are safe', () async {
      // Act
      await service.startTracking();
      await service.startTracking(); // Should be idempotent

      // Assert
      expect(service.isTracking, true);
      expect(service.positions.length, 0);
    });

    test('stopTracking when not tracking is safe', () async {
      // Act
      final km = await service.stopTracking();

      // Assert
      expect(km, 0.0);
      expect(service.isTracking, false);
    });

    test('very small distances (meters)', () async {
      // Arrange - Two points very close (meters apart)
      await service.startTracking();
      final point1 = LatLng(latitude: 31.9454, longitude: 35.9284);
      final point2 = LatLng(latitude: 31.94541, longitude: 35.92841);

      // Act
      service.recordPosition(point1);
      service.recordPosition(point2);

      // Assert - Should be small distance in meters
      expect(service.totalKilometers, lessThan(0.01)); // Less than 10 meters
      expect(service.totalMeters, greaterThan(0));
    });

    test('extreme latitude/longitude values', () {
      // Arrange
      final north = LatLng(latitude: 89.0, longitude: 0.0);
      final south = LatLng(latitude: -89.0, longitude: 0.0);

      // Act
      final distance = LocationService.calculateDistanceKm(north, south);

      // Assert - Should be close to half earth's circumference
      expect(distance, greaterThan(19000)); // ~20k km half circumference
      expect(distance, lessThan(21000));
    });
  });
}
