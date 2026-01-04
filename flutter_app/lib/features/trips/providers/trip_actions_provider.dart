import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/mock_trip_service.dart';
import 'trips_provider.dart';

class TripActionsNotifier {
  final Ref _ref;
  final MockTripService _service;

  TripActionsNotifier(this._ref, this._service);

  Future<void> markArrived(String tripId, String destId) async {
    await _service.markDestinationArrived(tripId, destId);
    // Invalidate providers to trigger refresh
    _ref.invalidate(tripDetailsProvider(tripId));
    _ref.invalidate(tripsProvider);
  }

  Future<void> markCompleted(String tripId, String destId) async {
    await _service.markDestinationCompleted(tripId, destId);
    _ref.invalidate(tripDetailsProvider(tripId));
    _ref.invalidate(tripsProvider);
  }

  Future<void> markFailed(String tripId, String destId) async {
    await _service.markDestinationFailed(tripId, destId);
    _ref.invalidate(tripDetailsProvider(tripId));
    _ref.invalidate(tripsProvider);
  }

  Future<void> startTrip(String tripId) async {
    await _ref.read(tripsProvider.notifier).startTrip(tripId);
  }
}

final tripActionsProvider = Provider((ref) {
  final service = ref.watch(mockTripServiceProvider);
  return TripActionsNotifier(ref, service);
});
