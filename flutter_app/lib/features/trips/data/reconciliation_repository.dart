import '../../../core/api/api_client.dart';
import '../../../core/api/api_config.dart';
import '../../../core/api/api_exceptions.dart';
import 'models/daily_reconciliation_model.dart';

/// Repository for daily reconciliation management via API
class ReconciliationRepository {
  final ApiClient _apiClient;

  ReconciliationRepository(this._apiClient);

  /// Generate daily reconciliation for today (end of day)
  /// Aggregates all trips, payments, and tupperware data
  Future<DailyReconciliationModel> generateDailyReconciliation() async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.endDay,
      );

      final data = response.data;
      final Map<String, dynamic> reconciliationJson;
      if (data is Map && data.containsKey('data')) {
        reconciliationJson = data['data'] as Map<String, dynamic>;
      } else {
        reconciliationJson = data as Map<String, dynamic>;
      }

      return DailyReconciliationModel.fromJson(reconciliationJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ReconciliationException(
        'Failed to generate daily reconciliation: $e',
      );
    }
  }

  /// Get today's reconciliation if it exists
  /// Returns null if no reconciliation has been generated yet
  Future<DailyReconciliationModel?> getTodaysReconciliation() async {
    try {
      final response = await _apiClient.get(
        ApiEndpoints.getTodaysReconciliation,
      );

      final data = response.data;
      if (data == null) {
        return null;
      }

      final Map<String, dynamic> reconciliationJson;
      if (data is Map && data.containsKey('data')) {
        final dataContent = data['data'];
        if (dataContent == null) {
          return null;
        }
        reconciliationJson = dataContent as Map<String, dynamic>;
      } else {
        reconciliationJson = data as Map<String, dynamic>;
      }

      return DailyReconciliationModel.fromJson(reconciliationJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ReconciliationException('Failed to fetch reconciliation: $e');
    }
  }

  /// Submit daily reconciliation to Melo ERP
  /// Changes status from pending → submitted
  Future<DailyReconciliationModel> submitReconciliation(
    String reconciliationId, {
    String? notes,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiEndpoints.submitReconciliation,
        data: {
          'reconciliation_id': reconciliationId,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      );

      final data = response.data;
      final Map<String, dynamic> reconciliationJson;
      if (data is Map && data.containsKey('data')) {
        reconciliationJson = data['data'] as Map<String, dynamic>;
      } else {
        reconciliationJson = data as Map<String, dynamic>;
      }

      return DailyReconciliationModel.fromJson(reconciliationJson);
    } on ApiException {
      rethrow;
    } catch (e) {
      throw ReconciliationException('Failed to submit reconciliation: $e');
    }
  }
}

/// Exception for reconciliation-related errors
class ReconciliationException implements Exception {
  final String message;

  ReconciliationException(this.message);

  @override
  String toString() => 'ReconciliationException: $message';
}
