import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import '../data/trips_repository.dart';
import '../data/models/trip_model.dart';
import '../services/location_service.dart';

/// Provider for TripsRepository
final tripsRepositoryProvider = Provider<TripsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TripsRepository(apiClient);
});

/// State notifier for trips list with loading/error states
class TripsNotifier extends StateNotifier<AsyncValue<List<TripModel>>> {
  final TripsRepository _repository;
  final LocationService _locationService;

  TripsNotifier(this._repository, this._locationService)
      : super(const AsyncValue.loading()) {
    loadTrips();
  }

  /// Load today's trips from the API
  Future<void> loadTrips() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => _repository.getTodaysTrips());
  }

  /// Start a trip with GPS location
  Future<void> startTrip(String tripId) async {
    try {
      // Get current position for tracking
      final position = await _locationService.getCurrentPosition();

      // Start the trip via API
      await _repository.startTrip(
        tripId,
        lat: position.latitude,
        lng: position.longitude,
      );

      // Start GPS tracking for distance calculation
      await _locationService.startTracking();

      // Refresh trips list
      await loadTrips();
    } catch (e) {
      // Keep current state but could show error via a separate error state
      state = AsyncValue.error(e, StackTrace.current);
    }
  }

  /// Complete a trip with actual KM driven
  Future<void> completeTrip(String tripId) async {
    try {
      // Get final position
      final position = await _locationService.getCurrentPosition();

      // Get total KM from location service
      final totalKm = _locationService.totalDistanceKm;

      // Complete the trip via API
      await _repository.completeTrip(
        tripId,
        totalKm: totalKm,
        lat: position.latitude,
        lng: position.longitude,
      );

      // Stop GPS tracking
      await _locationService.stopTracking();

      // Refresh trips list
      await loadTrips();
    } catch (e) {
      state = AsyncValue.error(e, StackTrace.current);
    }
  }
}

/// Main trips provider with state notifier
final tripsProvider =
    StateNotifierProvider<TripsNotifier, AsyncValue<List<TripModel>>>((ref) {
  final repository = ref.watch(tripsRepositoryProvider);
  final locationService = ref.watch(locationServiceProvider);
  return TripsNotifier(repository, locationService);
});

/// Provider for fetching a specific trip by ID
final tripDetailsProvider =
    FutureProvider.family<TripModel, String>((ref, tripId) async {
  final repository = ref.watch(tripsRepositoryProvider);
  return repository.getTripById(tripId);
});

/// Provider for current trip's tracked distance (in km)
final trackedDistanceProvider = Provider<double>((ref) {
  final locationService = ref.watch(locationServiceProvider);
  return locationService.totalDistanceKm;
});
