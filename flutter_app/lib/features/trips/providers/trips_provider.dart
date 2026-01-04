import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/mock_trip_service.dart';
import '../data/models/trip_model.dart';

// Service provider
final mockTripServiceProvider = Provider((ref) => MockTripService());

// State notifier for trips list
class TripsNotifier extends StateNotifier<AsyncValue<List<TripModel>>> {
  final MockTripService _service;

  TripsNotifier(this._service) : super(const AsyncValue.loading()) {
    loadTrips();
  }

  Future<void> loadTrips() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => _service.getTodaysTrips());
  }

  Future<void> startTrip(String tripId) async {
    await _service.startTrip(tripId);
    await loadTrips(); // Refresh state
  }
}

final tripsProvider =
    StateNotifierProvider<TripsNotifier, AsyncValue<List<TripModel>>>((ref) {
  final service = ref.watch(mockTripServiceProvider);
  return TripsNotifier(service);
});

// Family provider for specific trip details
final tripDetailsProvider =
    FutureProvider.family<TripModel, String>((ref, tripId) async {
  final service = ref.watch(mockTripServiceProvider);
  return service.getTripById(tripId);
});
