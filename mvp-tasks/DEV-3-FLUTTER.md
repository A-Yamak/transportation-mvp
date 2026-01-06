# DEV-3: Flutter App - API Integration & Real Data

> **Role**: Flutter Developer
> **Branch**: `feature/dev-3-flutter`
> **Dependency**: WAIT for DEV-2 to complete Driver API endpoints first

---

## SCRUM MASTER AVAILABLE

A scrum master is **ALWAYS available** to help you:
- Resolve blockers or unclear requirements
- Answer architecture questions
- Coordinate with DEV-2 on API contracts
- Review your approach before implementation

**Don't hesitate to ask for help!** It's better to clarify than to build the wrong thing.

---

## WATERFALL DEPENDENCY

**IMPORTANT**: You depend on DEV-2's API work. While waiting:

1. **Study the existing Flutter code**:
   ```bash
   ls flutter_app/lib/
   ls flutter_app/lib/features/
   ```

2. **Understand the current mock data**:
   ```bash
   cat flutter_app/lib/features/trips/data/mock_trips.dart
   ```

3. **Review the API client structure**:
   ```bash
   cat flutter_app/lib/core/api/api_client.dart
   ```

4. **Prepare repository interfaces**

When DEV-2 completes, you wire up real API calls.

---

## CONTEXT: Current State

The Flutter app has:
- **UI screens**: 85% complete (trips list, trip detail, navigation)
- **Mock data**: Hardcoded trips for development
- **No API integration**: All data is fake

**Your job**: Replace mock data with real API calls.

---

## YOUR RESPONSIBILITIES (What You DO)

### Task 1: API Client Setup

**File to create/modify**: `flutter_app/lib/core/api/api_client.dart`

```dart
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class ApiClient {
  static const String baseUrl = 'http://localhost:8001/api/v1';

  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: 'access_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (error, handler) async {
        if (error.response?.statusCode == 401) {
          // TODO: Refresh token or redirect to login
        }
        return handler.next(error);
      },
    ));
  }

  Future<Response> get(String path, {Map<String, dynamic>? queryParameters}) {
    return _dio.get(path, queryParameters: queryParameters);
  }

  Future<Response> post(String path, {dynamic data}) {
    return _dio.post(path, data: data);
  }

  Future<void> setToken(String token) async {
    await _storage.write(key: 'access_token', value: token);
  }

  Future<void> clearToken() async {
    await _storage.delete(key: 'access_token');
  }
}
```

---

### Task 2: Trip Repository

**File to create**: `flutter_app/lib/features/trips/data/trips_repository.dart`

```dart
import '../../../core/api/api_client.dart';
import '../domain/trip.dart';
import '../domain/destination.dart';

class TripsRepository {
  final ApiClient _apiClient;

  TripsRepository(this._apiClient);

  /// Fetch today's trips for the authenticated driver.
  Future<List<Trip>> getTodaysTrips() async {
    try {
      final response = await _apiClient.get('/driver/trips/today');
      final List<dynamic> data = response.data['data'];
      return data.map((json) => Trip.fromJson(json)).toList();
    } catch (e) {
      throw TripException('Failed to fetch trips: $e');
    }
  }

  /// Fetch single trip with full details.
  Future<Trip> getTrip(int tripId) async {
    try {
      final response = await _apiClient.get('/driver/trips/$tripId');
      return Trip.fromJson(response.data['data']);
    } catch (e) {
      throw TripException('Failed to fetch trip: $e');
    }
  }

  /// Start a trip.
  Future<Trip> startTrip(int tripId, {double? lat, double? lng}) async {
    try {
      final response = await _apiClient.post(
        '/driver/trips/$tripId/start',
        data: {'lat': lat, 'lng': lng},
      );
      return Trip.fromJson(response.data['data']);
    } catch (e) {
      throw TripException('Failed to start trip: $e');
    }
  }

  /// Mark arrival at destination.
  Future<Destination> arriveAtDestination(
    int tripId,
    int destinationId, {
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        '/driver/trips/$tripId/destinations/$destinationId/arrive',
        data: {'lat': lat, 'lng': lng},
      );
      return Destination.fromJson(response.data['data']);
    } catch (e) {
      throw TripException('Failed to mark arrival: $e');
    }
  }

  /// Complete delivery at destination.
  Future<Destination> completeDestination(
    int tripId,
    int destinationId, {
    String? notes,
    String? signatureBase64,
    String? photoBase64,
  }) async {
    try {
      final response = await _apiClient.post(
        '/driver/trips/$tripId/destinations/$destinationId/complete',
        data: {
          'notes': notes,
          'signature': signatureBase64,
          'photo': photoBase64,
        },
      );
      return Destination.fromJson(response.data['data']);
    } catch (e) {
      throw TripException('Failed to complete destination: $e');
    }
  }

  /// Complete entire trip.
  Future<Trip> completeTrip(
    int tripId, {
    required double totalKm,
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        '/driver/trips/$tripId/complete',
        data: {
          'total_km': totalKm,
          'lat': lat,
          'lng': lng,
        },
      );
      return Trip.fromJson(response.data['data']);
    } catch (e) {
      throw TripException('Failed to complete trip: $e');
    }
  }

  /// Get navigation URL for destination.
  Future<String> getNavigationUrl(int tripId, int destinationId) async {
    try {
      final response = await _apiClient.get(
        '/driver/trips/$tripId/destinations/$destinationId/navigate',
      );
      return response.data['data']['url'];
    } catch (e) {
      throw TripException('Failed to get navigation URL: $e');
    }
  }
}

class TripException implements Exception {
  final String message;
  TripException(this.message);

  @override
  String toString() => message;
}
```

---

### Task 3: Domain Models

**File to create**: `flutter_app/lib/features/trips/domain/trip.dart`

```dart
import 'destination.dart';

enum TripStatus { pending, inProgress, completed, cancelled }

class Trip {
  final int id;
  final TripStatus status;
  final DateTime? scheduledDate;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final double? actualKm;
  final DeliveryRequest deliveryRequest;
  final List<Destination> destinations;
  final Vehicle? vehicle;
  final TripProgress progress;

  Trip({
    required this.id,
    required this.status,
    this.scheduledDate,
    this.startedAt,
    this.completedAt,
    this.actualKm,
    required this.deliveryRequest,
    required this.destinations,
    this.vehicle,
    required this.progress,
  });

  factory Trip.fromJson(Map<String, dynamic> json) {
    return Trip(
      id: json['id'],
      status: _parseStatus(json['status']),
      scheduledDate: json['scheduled_date'] != null
          ? DateTime.parse(json['scheduled_date'])
          : null,
      startedAt: json['started_at'] != null
          ? DateTime.parse(json['started_at'])
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'])
          : null,
      actualKm: json['actual_km']?.toDouble(),
      deliveryRequest: DeliveryRequest.fromJson(json['delivery_request']),
      destinations: (json['destinations'] as List?)
              ?.map((d) => Destination.fromJson(d))
              .toList() ??
          [],
      vehicle: json['vehicle'] != null ? Vehicle.fromJson(json['vehicle']) : null,
      progress: TripProgress.fromJson(json['progress']),
    );
  }

  static TripStatus _parseStatus(String status) {
    switch (status) {
      case 'pending':
        return TripStatus.pending;
      case 'in_progress':
        return TripStatus.inProgress;
      case 'completed':
        return TripStatus.completed;
      case 'cancelled':
        return TripStatus.cancelled;
      default:
        return TripStatus.pending;
    }
  }

  bool get canStart => status == TripStatus.pending;
  bool get canComplete =>
      status == TripStatus.inProgress &&
      progress.pending == 0;
}

class DeliveryRequest {
  final int id;
  final double totalKm;
  final double estimatedCost;
  final String? polyline;

  DeliveryRequest({
    required this.id,
    required this.totalKm,
    required this.estimatedCost,
    this.polyline,
  });

  factory DeliveryRequest.fromJson(Map<String, dynamic> json) {
    return DeliveryRequest(
      id: json['id'],
      totalKm: json['total_km']?.toDouble() ?? 0.0,
      estimatedCost: json['estimated_cost']?.toDouble() ?? 0.0,
      polyline: json['polyline'],
    );
  }
}

class Vehicle {
  final int id;
  final String make;
  final String model;
  final String plateNumber;

  Vehicle({
    required this.id,
    required this.make,
    required this.model,
    required this.plateNumber,
  });

  factory Vehicle.fromJson(Map<String, dynamic> json) {
    return Vehicle(
      id: json['id'],
      make: json['make'],
      model: json['model'],
      plateNumber: json['plate_number'],
    );
  }
}

class TripProgress {
  final int totalDestinations;
  final int completed;
  final int pending;

  TripProgress({
    required this.totalDestinations,
    required this.completed,
    required this.pending,
  });

  factory TripProgress.fromJson(Map<String, dynamic> json) {
    return TripProgress(
      totalDestinations: json['total_destinations'] ?? 0,
      completed: json['completed'] ?? 0,
      pending: json['pending'] ?? 0,
    );
  }

  double get percentComplete =>
      totalDestinations > 0 ? completed / totalDestinations : 0;
}
```

---

### Task 4: Destination Model

**File to create**: `flutter_app/lib/features/trips/domain/destination.dart`

```dart
enum DestinationStatus { pending, arrived, completed, skipped }

class Destination {
  final int id;
  final int sequenceOrder;
  final String externalId;
  final DestinationStatus status;
  final String address;
  final double lat;
  final double lng;
  final String? contactName;
  final String? contactPhone;
  final String? notes;
  final DateTime? arrivedAt;
  final DateTime? completedAt;
  final String? signatureUrl;
  final String? photoUrl;
  final String navigationUrl;

  Destination({
    required this.id,
    required this.sequenceOrder,
    required this.externalId,
    required this.status,
    required this.address,
    required this.lat,
    required this.lng,
    this.contactName,
    this.contactPhone,
    this.notes,
    this.arrivedAt,
    this.completedAt,
    this.signatureUrl,
    this.photoUrl,
    required this.navigationUrl,
  });

  factory Destination.fromJson(Map<String, dynamic> json) {
    return Destination(
      id: json['id'],
      sequenceOrder: json['sequence_order'],
      externalId: json['external_id'],
      status: _parseStatus(json['status']),
      address: json['address'],
      lat: json['lat']?.toDouble() ?? 0.0,
      lng: json['lng']?.toDouble() ?? 0.0,
      contactName: json['contact_name'],
      contactPhone: json['contact_phone'],
      notes: json['notes'],
      arrivedAt: json['arrived_at'] != null
          ? DateTime.parse(json['arrived_at'])
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'])
          : null,
      signatureUrl: json['signature_url'],
      photoUrl: json['photo_url'],
      navigationUrl: json['navigation_url'],
    );
  }

  static DestinationStatus _parseStatus(String status) {
    switch (status) {
      case 'pending':
        return DestinationStatus.pending;
      case 'arrived':
        return DestinationStatus.arrived;
      case 'completed':
        return DestinationStatus.completed;
      case 'skipped':
        return DestinationStatus.skipped;
      default:
        return DestinationStatus.pending;
    }
  }

  bool get canArrive => status == DestinationStatus.pending;
  bool get canComplete => status == DestinationStatus.arrived;
  bool get isCompleted => status == DestinationStatus.completed;
}
```

---

### Task 5: Update Trip Provider/Bloc

**File to modify**: `flutter_app/lib/features/trips/presentation/trips_provider.dart` (or bloc)

Replace mock data usage with repository calls:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/trips_repository.dart';
import '../domain/trip.dart';

final tripsRepositoryProvider = Provider((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TripsRepository(apiClient);
});

final todaysTripsProvider = FutureProvider<List<Trip>>((ref) async {
  final repository = ref.watch(tripsRepositoryProvider);
  return repository.getTodaysTrips();
});

final tripDetailProvider = FutureProvider.family<Trip, int>((ref, tripId) async {
  final repository = ref.watch(tripsRepositoryProvider);
  return repository.getTrip(tripId);
});

class TripsNotifier extends StateNotifier<TripsState> {
  final TripsRepository _repository;

  TripsNotifier(this._repository) : super(TripsState.initial());

  Future<void> loadTodaysTrips() async {
    state = state.copyWith(isLoading: true);
    try {
      final trips = await _repository.getTodaysTrips();
      state = state.copyWith(
        isLoading: false,
        trips: trips,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  Future<void> startTrip(int tripId) async {
    try {
      final position = await getCurrentPosition(); // Geolocator
      await _repository.startTrip(
        tripId,
        lat: position.latitude,
        lng: position.longitude,
      );
      await loadTodaysTrips(); // Refresh
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  Future<void> arriveAtDestination(int tripId, int destinationId) async {
    try {
      final position = await getCurrentPosition();
      await _repository.arriveAtDestination(
        tripId,
        destinationId,
        lat: position.latitude,
        lng: position.longitude,
      );
      // Refresh trip
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }

  Future<void> completeDestination(
    int tripId,
    int destinationId, {
    String? notes,
  }) async {
    try {
      await _repository.completeDestination(
        tripId,
        destinationId,
        notes: notes,
      );
      // Refresh trip
    } catch (e) {
      state = state.copyWith(error: e.toString());
    }
  }
}
```

---

### Task 6: GPS Tracking for KM

**File to modify**: `flutter_app/lib/features/trips/services/location_service.dart`

```dart
import 'package:geolocator/geolocator.dart';
import 'dart:async';

class LocationService {
  StreamSubscription<Position>? _locationSubscription;
  Position? _lastPosition;
  double _totalDistance = 0;

  double get totalDistanceKm => _totalDistance / 1000;

  Future<void> startTracking() async {
    _totalDistance = 0;
    _lastPosition = null;

    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      await Geolocator.requestPermission();
    }

    _locationSubscription = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10, // Update every 10 meters
      ),
    ).listen((Position position) {
      if (_lastPosition != null) {
        _totalDistance += Geolocator.distanceBetween(
          _lastPosition!.latitude,
          _lastPosition!.longitude,
          position.latitude,
          position.longitude,
        );
      }
      _lastPosition = position;
    });
  }

  Future<void> stopTracking() async {
    await _locationSubscription?.cancel();
    _locationSubscription = null;
  }

  Future<Position> getCurrentPosition() async {
    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }
}
```

---

### Task 7: Remove Mock Data

**Files to delete/modify**:
- Delete: `flutter_app/lib/features/trips/data/mock_trips.dart`
- Update: All screens that reference mock data to use repository

Search for `mock` or `Mock` in the codebase and replace with real repository calls.

---

## NOT YOUR RESPONSIBILITY (Handled by Others)

| Task | Handled By | Why Not You |
|------|------------|-------------|
| Backend API endpoints | DEV-1 & DEV-2 | Laravel developers |
| Route optimization | DEV-1 | Google Maps service |
| ERP callbacks | DEV-1 | Backend service |
| Admin panel | Backend team | Filament |

---

## TECH STACK REFERENCE

**Flutter**:
- Dart 3.x
- State management: Riverpod (or Bloc if existing)
- HTTP client: Dio
- Location: Geolocator
- Secure storage: flutter_secure_storage

**Testing**:
```bash
cd flutter_app
flutter test
flutter test --coverage
```

**Running**:
```bash
flutter run
```

---

## API ENDPOINTS TO CONSUME

From DEV-2:
```
GET    /api/v1/driver/trips/today
GET    /api/v1/driver/trips/{id}
POST   /api/v1/driver/trips/{id}/start
POST   /api/v1/driver/trips/{id}/complete
POST   /api/v1/driver/trips/{id}/destinations/{id}/arrive
POST   /api/v1/driver/trips/{id}/destinations/{id}/complete
GET    /api/v1/driver/trips/{id}/destinations/{id}/navigate
```

---

## EXPECTED STANDARDS

1. **Clean Architecture**: Repository pattern for data access
2. **Error Handling**: Catch and display user-friendly errors
3. **Loading States**: Show spinners during API calls
4. **Offline Handling**: Queue actions when offline (future)
5. **Type Safety**: Full Dart types, no `dynamic`

---

## DEFINITION OF DONE

- [ ] ApiClient with authentication interceptor
- [ ] TripsRepository with all endpoint methods
- [ ] Trip and Destination domain models
- [ ] fromJson factories for API responses
- [ ] Provider/Bloc updated to use repository
- [ ] LocationService for GPS tracking
- [ ] Mock data files removed
- [ ] All screens use real API data
- [ ] Error states handled gracefully
- [ ] Loading indicators shown
- [ ] Tests pass: `flutter test`
- [ ] Changes committed to your branch

---

## HANDOFF FROM DEV-2

DEV-2 will provide:
1. API endpoint documentation
2. Response structure examples
3. Error response format

Ask DEV-2 or scrum master for API contract!

---

## TESTING REAL API

1. Make sure backend is running:
   ```bash
   cd ../backend
   make up
   ```

2. Update `baseUrl` in ApiClient to point to running backend

3. Test with real credentials (ask scrum master for test driver account)
