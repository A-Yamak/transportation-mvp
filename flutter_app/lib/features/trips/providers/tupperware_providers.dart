import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import '../data/tupperware_repository.dart';
import '../data/models/tupperware_balance_model.dart';
import '../data/models/tupperware_movement_model.dart';

/// Provider for TupperwareRepository
final tupperwareRepositoryProvider = Provider<TupperwareRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TupperwareRepository(apiClient);
});

/// Provider for fetching tupperware balance for a specific shop
/// Takes shopId as a family parameter
final tupperwareBalanceProvider = FutureProvider.family<
    List<TupperwareBalanceModel>,
    String>((ref, shopId) async {
  final repository = ref.watch(tupperwareRepositoryProvider);
  return repository.getShopBalance(shopId);
});

/// State for managing tupperware pickup form before submission
class TupperwarePickupFormState {
  final Map<String, int> pickupQuantities; // productType -> quantity
  final String? notes;

  const TupperwarePickupFormState({
    this.pickupQuantities = const {},
    this.notes,
  });

  const TupperwarePickupFormState.initial()
      : pickupQuantities = const {},
        notes = null;

  /// Get quantity to pickup for a product type
  int getQuantity(String productType) =>
      pickupQuantities[productType] ?? 0;

  /// Check if any pickups are selected
  bool get hasPickups =>
      pickupQuantities.values.any((qty) => qty > 0);

  /// Get total items being picked up
  int get totalQuantity =>
      pickupQuantities.values.fold(0, (sum, qty) => sum + qty);

  /// Convert to API format
  List<Map<String, dynamic>> toApiFormat() {
    return pickupQuantities.entries
        .where((entry) => entry.value > 0)
        .map((entry) => {
              'product_type': entry.key,
              'quantity': entry.value,
            })
        .toList();
  }

  TupperwarePickupFormState copyWith({
    Map<String, int>? pickupQuantities,
    String? notes,
  }) {
    return TupperwarePickupFormState(
      pickupQuantities: pickupQuantities ?? this.pickupQuantities,
      notes: notes ?? this.notes,
    );
  }
}

/// State notifier for managing tupperware pickup form
class TupperwarePickupFormNotifier
    extends StateNotifier<TupperwarePickupFormState> {
  TupperwarePickupFormNotifier()
      : super(const TupperwarePickupFormState.initial());

  /// Initialize with product types from balance
  void initializeFromBalance(List<TupperwareBalanceModel> balances) {
    final quantities = {
      for (final balance in balances) balance.productType: 0
    };
    state = state.copyWith(pickupQuantities: quantities);
  }

  /// Set quantity for a product type
  void setQuantity(String productType, int quantity) {
    if (quantity < 0) return;

    final updated = Map<String, int>.from(state.pickupQuantities);
    updated[productType] = quantity;
    state = state.copyWith(pickupQuantities: updated);
  }

  /// Increment quantity for a product type
  void incrementQuantity(String productType, {int amount = 1}) {
    final current = state.getQuantity(productType);
    setQuantity(productType, current + amount);
  }

  /// Decrement quantity for a product type
  void decrementQuantity(String productType, {int amount = 1}) {
    final current = state.getQuantity(productType);
    if (current > 0) {
      setQuantity(productType, current - amount);
    }
  }

  /// Set notes
  void setNotes(String? notes) {
    state = state.copyWith(notes: notes);
  }

  /// Reset all quantities to zero
  void resetQuantities() {
    final updated = {
      for (final key in state.pickupQuantities.keys) key: 0
    };
    state = state.copyWith(pickupQuantities: updated);
  }

  /// Clear form completely
  void clear() {
    state = const TupperwarePickupFormState.initial();
  }
}

/// State notifier for submitting tupperware collection
class TupperwareCollectionNotifier
    extends StateNotifier<AsyncValue<List<TupperwareMovementModel>?>> {
  final TupperwareRepository _repository;

  TupperwareCollectionNotifier(this._repository)
      : super(const AsyncValue.data(null));

  /// Submit tupperware collection to API
  Future<void> submitPickup(
    String tripId,
    String destinationId,
    TupperwarePickupFormState formState,
  ) async {
    // Validate that at least one item is being picked up
    if (!formState.hasPickups) {
      state = AsyncValue.error(
        'Please select at least one item to pick up',
        StackTrace.current,
      );
      return;
    }

    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.collectTupperware(
        tripId,
        destinationId,
        tupperware: formState.toApiFormat(),
        notes: formState.notes,
      ),
    );
  }

  /// Clear state
  void clear() {
    state = const AsyncValue.data(null);
  }
}

/// Provider for tupperware pickup form state
final tupperwarePickupFormProvider = StateNotifierProvider<
    TupperwarePickupFormNotifier,
    TupperwarePickupFormState>((ref) {
  return TupperwarePickupFormNotifier();
});

/// Provider for submitting tupperware collection
final tupperwareCollectionProvider = StateNotifierProvider<
    TupperwareCollectionNotifier,
    AsyncValue<List<TupperwareMovementModel>?>>((ref) {
  final repository = ref.watch(tupperwareRepositoryProvider);
  return TupperwareCollectionNotifier(repository);
});

/// Derived provider: check if any pickups are selected
final tupperwareHasPickupsProvider = Provider<bool>((ref) {
  final formState = ref.watch(tupperwarePickupFormProvider);
  return formState.hasPickups;
});

/// Derived provider: get total quantity being picked up
final tuppwareTotalPickupProvider = Provider<int>((ref) {
  final formState = ref.watch(tupperwarePickupFormProvider);
  return formState.totalQuantity;
});

/// Derived provider: check if form is valid
final tupperwareFormValidProvider = Provider<bool>((ref) {
  final formState = ref.watch(tupperwarePickupFormProvider);
  return formState.hasPickups;
});
