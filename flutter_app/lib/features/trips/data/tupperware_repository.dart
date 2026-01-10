import '../../../core/api/api_client.dart';
import '../../../core/api/api_config.dart';
import '../../../core/api/api_exceptions.dart';
import 'models/tupperware_balance_model.dart';
import 'models/tupperware_movement_model.dart';

/// Repository for tupperware/container management via API
class TupperwareRepository {
  final ApiClient _apiClient;

  TupperwareRepository(this._apiClient);

  /// Get tupperware balance for a shop by product type
  Future<List<TupperwareBalanceModel>> getShopBalance(String shopId) async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.tupperwareBalance(shopId),
      );

      final data = response.data;
      final List<dynamic> balancesJson;
      if (data is Map && data.containsKey('data')) {
        final dataContent = data['data'];
        if (dataContent is List) {
          balancesJson = dataContent;
        } else if (dataContent is Map && dataContent.containsKey('data')) {
          balancesJson = dataContent['data'] as List;
        } else {
          balancesJson = [];
        }
      } else if (data is List) {
        balancesJson = data;
      } else {
        balancesJson = [];
      }

      return balancesJson
          .map((json) =>
              TupperwareBalanceModel.fromJson(json as Map<String, dynamic>))
          .toList();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TupperwareException('Failed to fetch tupperware balance: $e');
    }
  }

  /// Collect tupperware at a destination
  Future<List<TupperwareMovementModel>> collectTupperware(
    String tripId,
    String destinationId, {
    required List<Map<String, dynamic>> tupperware,
    String? notes,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.collectTupperware(tripId, destinationId),
        data: {
          'tupperware': tupperware,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );

      final data = response.data;
      final List<dynamic> movementsJson;
      if (data is Map && data.containsKey('data')) {
        final dataContent = data['data'];
        if (dataContent is List) {
          movementsJson = dataContent;
        } else if (dataContent is Map && dataContent.containsKey('data')) {
          movementsJson = dataContent['data'] as List;
        } else {
          movementsJson = [];
        }
      } else if (data is List) {
        movementsJson = data;
      } else {
        movementsJson = [];
      }

      return movementsJson
          .map((json) =>
              TupperwareMovementModel.fromJson(json as Map<String, dynamic>))
          .toList();
    } on ApiException {
      rethrow;
    } catch (e) {
      throw TupperwareException('Failed to collect tupperware: $e');
    }
  }
}

/// Exception for tupperware-related errors
class TupperwareException implements Exception {
  final String message;

  TupperwareException(this.message);

  @override
  String toString() => 'TupperwareException: $message';
}
