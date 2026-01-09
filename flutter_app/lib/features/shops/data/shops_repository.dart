import '../../../core/api/api_client.dart';
import '../../../core/api/api_config.dart';
import '../../../core/api/api_exceptions.dart';
import 'models/shop_model.dart';
import 'models/waste_collection_model.dart';
import 'models/waste_item_model.dart';

/// Repository for shops and waste collection data access via API
class ShopsRepository {
  final ApiClient _apiClient;

  ShopsRepository(this._apiClient);

  /// Get all shops with waste tracking.
  /// Returns list of shops with their waste status.
  Future<List<ShopModel>> listShops() async {
    try {
      final response = await _apiClient.get(ApiEndpoints.shops);

      final data = response.data;
      final List<dynamic> shopsJson;

      if (data is Map && data.containsKey('data')) {
        shopsJson = data['data'] as List<dynamic>;
      } else if (data is List) {
        shopsJson = data;
      } else {
        throw ShopsException('Invalid shops list response format');
      }

      return shopsJson
          .map((json) => ShopModel.fromListJson(json as Map<String, dynamic>))
          .toList();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ShopsException('Failed to fetch shops: $e');
    }
  }

  /// Get expected waste items for a specific shop.
  /// Returns waste collection with items that need to be logged.
  Future<WasteCollectionModel> getExpectedWaste(String shopId) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.getExpectedWaste(shopId),
      );

      final data = response.data;
      final Map<String, dynamic> wasteJson;

      if (data is Map && data.containsKey('data')) {
        wasteJson = data['data'] as Map<String, dynamic>;
      } else if (data is Map<String, dynamic>) {
        wasteJson = data;
      } else {
        throw ShopsException('Invalid waste collection response format');
      }

      return WasteCollectionModel.fromJson(wasteJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ShopsException('Failed to fetch expected waste: $e');
    }
  }

  /// Log waste collection at a shop for a specific trip.
  /// Submits waste items with quantities and optional notes.
  Future<WasteCollectionModel> logWasteCollection(
    String tripId,
    String shopId,
    List<WasteItemModel> wasteItems, {
    String? driverNotes,
  }) async {
    try {
      // Validate waste quantities before submitting
      for (final item in wasteItems) {
        if (!item.isValidWasteQuantity) {
          throw ShopsException(
            'Waste quantity cannot exceed delivered quantity for ${item.productName}',
          );
        }
      }

      final response = await _apiClient.post(
        ApiEndpoints.logWasteCollection(tripId, shopId),
        data: {
          'waste_items': wasteItems
              .map((item) => item.toJson())
              .toList(),
          if (driverNotes != null && driverNotes.isNotEmpty)
            'driver_notes': driverNotes,
        },
      );

      final data = response.data;
      final Map<String, dynamic> collectionJson;

      if (data is Map && data.containsKey('data')) {
        collectionJson = data['data'] as Map<String, dynamic>;
      } else if (data is Map<String, dynamic>) {
        collectionJson = data;
      } else {
        throw ShopsException('Invalid waste collection response format');
      }

      return WasteCollectionModel.fromJson(collectionJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      if (e is ShopsException) rethrow;
      throw ShopsException('Failed to log waste collection: $e');
    }
  }
}

/// Exception for shops-related errors
class ShopsException implements Exception {
  final String message;

  ShopsException(this.message);

  @override
  String toString() => 'ShopsException: $message';
}
