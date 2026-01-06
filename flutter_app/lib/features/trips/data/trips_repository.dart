import '../../../core/api/api_client.dart';
import '../../../core/api/api_config.dart';
import '../../../core/api/api_exceptions.dart';
import 'models/trip_model.dart';
import 'models/destination_model.dart';

/// Repository for trips data access via API
class TripsRepository {
  final ApiClient _apiClient;

  TripsRepository(this._apiClient);

  /// Fetch today's trips for the authenticated driver.
  Future<List<TripModel>> getTodaysTrips() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.todaysTrips);
      final data = response.data;

      // Handle both paginated and non-paginated responses
      final List<dynamic> tripsJson;
      if (data is Map && data.containsKey('data')) {
        final dataContent = data['data'];
        if (dataContent is List) {
          tripsJson = dataContent;
        } else if (dataContent is Map && dataContent.containsKey('data')) {
          // Paginated response: { data: { data: [...], meta: {...} } }
          tripsJson = dataContent['data'] as List;
        } else {
          tripsJson = [];
        }
      } else if (data is List) {
        tripsJson = data;
      } else {
        tripsJson = [];
      }

      return tripsJson
          .map((json) => TripModel.fromJson(json as Map<String, dynamic>))
          .toList();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to fetch trips: $e');
    }
  }

  /// Fetch single trip with full details.
  Future<TripModel> getTripById(String tripId) async {
    try {
      final response = await _apiClient.get(ApiEndpoints.tripDetails(tripId));
      final data = response.data;

      final Map<String, dynamic> tripJson;
      if (data is Map && data.containsKey('data')) {
        tripJson = data['data'] as Map<String, dynamic>;
      } else if (data is Map) {
        tripJson = data as Map<String, dynamic>;
      } else {
        throw TripException('Invalid response format');
      }

      return TripModel.fromJson(tripJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to fetch trip: $e');
    }
  }

  /// Start a trip with optional current GPS location.
  Future<TripModel> startTrip(
    String tripId, {
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.startTrip(tripId),
        data: {
          if (lat != null) 'lat': lat,
          if (lng != null) 'lng': lng,
        },
      );

      final data = response.data;
      final Map<String, dynamic> tripJson;
      if (data is Map && data.containsKey('data')) {
        tripJson = data['data'] as Map<String, dynamic>;
      } else {
        tripJson = data as Map<String, dynamic>;
      }

      return TripModel.fromJson(tripJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to start trip: $e');
    }
  }

  /// Mark arrival at a destination with optional GPS location.
  Future<DestinationModel> arriveAtDestination(
    String tripId,
    String destinationId, {
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.arriveAtDestination(tripId, destinationId),
        data: {
          if (lat != null) 'lat': lat,
          if (lng != null) 'lng': lng,
        },
      );

      final data = response.data;
      final Map<String, dynamic> destJson;
      if (data is Map && data.containsKey('data')) {
        destJson = data['data'] as Map<String, dynamic>;
      } else {
        destJson = data as Map<String, dynamic>;
      }

      return DestinationModel.fromJson(destJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to mark arrival: $e');
    }
  }

  /// Complete delivery at a destination with optional notes and proof.
  Future<DestinationModel> completeDestination(
    String tripId,
    String destinationId, {
    String? notes,
    String? signatureBase64,
    String? photoBase64,
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.completeDestination(tripId, destinationId),
        data: {
          if (notes != null) 'notes': notes,
          if (signatureBase64 != null) 'signature': signatureBase64,
          if (photoBase64 != null) 'photo': photoBase64,
          if (lat != null) 'lat': lat,
          if (lng != null) 'lng': lng,
        },
      );

      final data = response.data;
      final Map<String, dynamic> destJson;
      if (data is Map && data.containsKey('data')) {
        destJson = data['data'] as Map<String, dynamic>;
      } else {
        destJson = data as Map<String, dynamic>;
      }

      return DestinationModel.fromJson(destJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to complete destination: $e');
    }
  }

  /// Mark a destination as failed with reason.
  /// Valid reasons: not_home, refused, wrong_address, inaccessible, other
  Future<DestinationModel> failDestination(
    String tripId,
    String destinationId, {
    required String reason,
    String? notes,
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.failDestination(tripId, destinationId),
        data: {
          'reason': reason,
          if (notes != null) 'notes': notes,
          if (lat != null) 'lat': lat,
          if (lng != null) 'lng': lng,
        },
      );

      final data = response.data;
      final Map<String, dynamic> destJson;
      if (data is Map && data.containsKey('data')) {
        destJson = data['data'] as Map<String, dynamic>;
      } else {
        destJson = data as Map<String, dynamic>;
      }

      return DestinationModel.fromJson(destJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to mark destination as failed: $e');
    }
  }

  /// Complete entire trip with actual KM driven.
  Future<TripModel> completeTrip(
    String tripId, {
    required double totalKm,
    double? lat,
    double? lng,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.completeTrip(tripId),
        data: {
          'total_km': totalKm,
          if (lat != null) 'lat': lat,
          if (lng != null) 'lng': lng,
        },
      );

      final data = response.data;
      final Map<String, dynamic> tripJson;
      if (data is Map && data.containsKey('data')) {
        tripJson = data['data'] as Map<String, dynamic>;
      } else {
        tripJson = data as Map<String, dynamic>;
      }

      return TripModel.fromJson(tripJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to complete trip: $e');
    }
  }

  /// Get Google Maps navigation URL for a destination.
  Future<String> getNavigationUrl(String tripId, String destinationId) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.navigationUrl(tripId, destinationId),
      );

      final data = response.data;
      if (data is Map && data.containsKey('data')) {
        final dataMap = data['data'] as Map<String, dynamic>;
        return dataMap['url'] as String? ?? '';
      }
      return data['url'] as String? ?? '';
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TripException('Failed to get navigation URL: $e');
    }
  }
}

/// Exception for trip-related errors
class TripException implements Exception {
  final String message;

  TripException(this.message);

  @override
  String toString() => 'TripException: $message';
}
