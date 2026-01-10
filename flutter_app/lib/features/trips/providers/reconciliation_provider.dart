import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import '../data/reconciliation_repository.dart';
import '../data/models/daily_reconciliation_model.dart';

/// Provider for ReconciliationRepository
final reconciliationRepositoryProvider =
    Provider<ReconciliationRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ReconciliationRepository(apiClient);
});

/// Provider for fetching today's reconciliation (if it exists)
final todaysReconciliationProvider =
    FutureProvider<DailyReconciliationModel?>((ref) async {
  final repository = ref.watch(reconciliationRepositoryProvider);
  return repository.getTodaysReconciliation();
});

/// State notifier for managing daily reconciliation
class ReconciliationNotifier
    extends StateNotifier<AsyncValue<DailyReconciliationModel?>> {
  final ReconciliationRepository _repository;

  ReconciliationNotifier(this._repository)
      : super(const AsyncValue.data(null));

  /// Generate daily reconciliation for end of day
  /// Aggregates all trips, payments, and tupperware data
  Future<void> generateReconciliation() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.generateDailyReconciliation(),
    );
  }

  /// Reload today's reconciliation from API
  Future<void> reloadReconciliation() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.getTodaysReconciliation(),
    );
  }

  /// Submit reconciliation to Melo ERP
  Future<void> submitReconciliation(String reconciliationId,
      {String? notes}) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.submitReconciliation(
        reconciliationId,
        notes: notes,
      ),
    );
  }

  /// Clear the reconciliation state
  void clear() {
    state = const AsyncValue.data(null);
  }
}

/// Provider for managing daily reconciliation state
final reconciliationProvider = StateNotifierProvider<ReconciliationNotifier,
    AsyncValue<DailyReconciliationModel?>>((ref) {
  final repository = ref.watch(reconciliationRepositoryProvider);
  return ReconciliationNotifier(repository);
});

/// Derived provider: get collection rate
final collectionRateProvider = Provider<double?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.collectionRate,
  ).asData?.value;
});

/// Derived provider: get total shortage amount
final totalShortageProvider = Provider<double?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.totalShortage,
  ).asData?.value;
});

/// Derived provider: get number of fully collected shops
final fullyCollectedShopsProvider = Provider<int?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.shopsFullyCollected,
  ).asData?.value;
});

/// Derived provider: get number of partially collected shops
final partiallyCollectedShopsProvider = Provider<int?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.shopsPartiallyCollected,
  ).asData?.value;
});

/// Derived provider: get number of shops with no collection
final uncollectedShopsProvider = Provider<int?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.shopsNotCollected,
  ).asData?.value;
});

/// Derived provider: get cash percentage
final cashPercentageProvider = Provider<double?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.cashPercentage,
  ).asData?.value;
});

/// Derived provider: get CliQ percentage
final cliqPercentageProvider = Provider<double?>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data?.cliqPercentage,
  ).asData?.value;
});

/// Derived provider: check if reconciliation is ready to submit
final canSubmitReconciliationProvider = Provider<bool>((ref) {
  final reconciliation = ref.watch(reconciliationProvider);
  return reconciliation.whenData(
    (data) => data != null && data.status == ReconciliationStatus.pending,
  ).asData?.value ??
      false;
});
