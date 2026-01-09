import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import '../data/shops_repository.dart';
import '../data/models/shop_model.dart';
import '../data/models/waste_collection_model.dart';
import '../data/models/waste_item_model.dart';

/// Provider for ShopsRepository
final shopsRepositoryProvider = Provider<ShopsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return ShopsRepository(apiClient);
});

/// Provider for fetching expected waste for a specific shop
/// Takes shopId as a family parameter
final expectedWasteProvider =
    FutureProvider.family<WasteCollectionModel, String>((ref, shopId) async {
  final repository = ref.watch(shopsRepositoryProvider);
  return repository.getExpectedWaste(shopId);
});

/// State notifier for logging waste collection
class WasteCollectionNotifier extends StateNotifier<AsyncValue<WasteCollectionModel?>> {
  final ShopsRepository _repository;

  WasteCollectionNotifier(this._repository)
      : super(const AsyncValue.data(null));

  /// Log waste collection at a shop
  Future<void> logWaste(
    String tripId,
    String shopId,
    List<WasteItemModel> wasteItems, {
    String? driverNotes,
  }) async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.logWasteCollection(
        tripId,
        shopId,
        wasteItems,
        driverNotes: driverNotes,
      ),
    );
  }

  /// Clear the waste collection state
  void clear() {
    state = const AsyncValue.data(null);
  }
}

/// Main waste collection provider for managing waste logging state
final wasteCollectionProvider = StateNotifierProvider<WasteCollectionNotifier,
    AsyncValue<WasteCollectionModel?>>((ref) {
  final repository = ref.watch(shopsRepositoryProvider);
  return WasteCollectionNotifier(repository);
});

/// Provider for managing waste item list during collection (before submit)
/// This allows building/editing the waste items before submitting
final wasteItemsEditingProvider =
    StateNotifierProvider<WasteItemsNotifier, List<WasteItemModel>>((ref) {
  return WasteItemsNotifier();
});

/// State notifier for managing waste items in progress (not yet submitted)
class WasteItemsNotifier extends StateNotifier<List<WasteItemModel>> {
  WasteItemsNotifier() : super([]);

  /// Initialize with items from expected waste
  void initialize(List<WasteItemModel> items) {
    state = items.map((item) => item.copyWith()).toList();
  }

  /// Update waste quantity for a specific item
  void updateWasteQuantity(String itemId, int quantity) {
    state = [
      for (final item in state)
        if (item.id == itemId)
          item.copyWith(piecesWaste: quantity)
        else
          item,
      ];
  }

  /// Update notes for a specific item
  void updateNotes(String itemId, String notes) {
    state = [
      for (final item in state)
        if (item.id == itemId)
          item.copyWith(notes: notes)
        else
          item,
    ];
  }

  /// Reset all items (clear waste quantities)
  void reset() {
    state = [
      for (final item in state)
        item.copyWith(piecesWaste: 0, notes: null),
    ];
  }

  /// Clear all items
  void clear() {
    state = [];
  }

  /// Get total waste pieces
  int get totalWastePieces =>
      state.fold(0, (sum, item) => sum + item.piecesWaste);

  /// Get total sold pieces
  int get totalSoldPieces =>
      state.fold(0, (sum, item) => sum + item.piecesSold);

  /// Check if all items have valid waste quantities
  bool get allItemsValid => state.every((item) => item.isValidWasteQuantity);
}

/// Derived provider to get the total waste percentage
final wastePercentageProvider = Provider<double>((ref) {
  final items = ref.watch(wasteItemsEditingProvider);
  if (items.isEmpty) return 0.0;

  final totalDelivered =
      items.fold(0, (sum, item) => sum + item.quantityDelivered);
  if (totalDelivered == 0) return 0.0;

  final totalWaste = items.fold(0, (sum, item) => sum + item.piecesWaste);
  return (totalWaste / totalDelivered) * 100;
});
