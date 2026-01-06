import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/trips_repository.dart';
import '../services/location_service.dart';
import 'trips_provider.dart';

/// Notifier for handling trip actions (arrive, complete, fail destinations)
class TripActionsNotifier {
  final Ref _ref;
  final TripsRepository _repository;
  final LocationService _locationService;

  TripActionsNotifier(this._ref, this._repository, this._locationService);

  /// Mark arrival at a destination
  Future<void> markArrived(String tripId, String destId) async {
    try {
      // Get current GPS position
      final position = await _locationService.getCurrentPosition();

      // Call API to mark arrival
      await _repository.arriveAtDestination(
        tripId,
        destId,
        lat: position.latitude,
        lng: position.longitude,
      );

      // Invalidate providers to trigger refresh
      _ref.invalidate(tripDetailsProvider(tripId));
      _ref.invalidate(tripsProvider);
    } catch (e) {
      rethrow;
    }
  }

  /// Complete delivery at a destination
  Future<void> markCompleted(
    String tripId,
    String destId, {
    String? notes,
    String? signatureBase64,
    String? photoBase64,
  }) async {
    try {
      // Get current GPS position
      final position = await _locationService.getCurrentPosition();

      // Call API to complete destination
      await _repository.completeDestination(
        tripId,
        destId,
        notes: notes,
        signatureBase64: signatureBase64,
        photoBase64: photoBase64,
        lat: position.latitude,
        lng: position.longitude,
      );

      // Invalidate providers to trigger refresh
      _ref.invalidate(tripDetailsProvider(tripId));
      _ref.invalidate(tripsProvider);
    } catch (e) {
      rethrow;
    }
  }

  /// Mark a destination as failed
  /// Valid reasons: not_home, refused, wrong_address, inaccessible, other
  Future<void> markFailed(
    String tripId,
    String destId, {
    required String reason,
    String? notes,
  }) async {
    try {
      // Get current GPS position
      final position = await _locationService.getCurrentPosition();

      // Call API to fail destination
      await _repository.failDestination(
        tripId,
        destId,
        reason: reason,
        notes: notes,
        lat: position.latitude,
        lng: position.longitude,
      );

      // Invalidate providers to trigger refresh
      _ref.invalidate(tripDetailsProvider(tripId));
      _ref.invalidate(tripsProvider);
    } catch (e) {
      rethrow;
    }
  }

  /// Start a trip
  Future<void> startTrip(String tripId) async {
    await _ref.read(tripsProvider.notifier).startTrip(tripId);
  }

  /// Complete a trip
  Future<void> completeTrip(String tripId) async {
    await _ref.read(tripsProvider.notifier).completeTrip(tripId);
  }

  /// Get navigation URL for a destination (can be generated locally)
  String getNavigationUrl(double lat, double lng) {
    return 'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving';
  }
}

/// Provider for trip actions
final tripActionsProvider = Provider<TripActionsNotifier>((ref) {
  final repository = ref.watch(tripsRepositoryProvider);
  final locationService = ref.watch(locationServiceProvider);
  return TripActionsNotifier(ref, repository, locationService);
});
