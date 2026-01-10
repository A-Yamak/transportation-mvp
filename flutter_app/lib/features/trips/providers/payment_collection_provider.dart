import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import '../data/trips_repository.dart';
import '../data/models/payment_collection_model.dart';
import '../data/models/payment_method_enum.dart';
import '../data/models/payment_status_enum.dart';
import '../data/models/shortage_reason_enum.dart';

/// Provider for TripsRepository (used by payment collection provider)
final tripsRepositoryProvider = Provider<TripsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return TripsRepository(apiClient);
});

/// State notifier for managing payment collection form state
class PaymentCollectionFormNotifier
    extends StateNotifier<PaymentCollectionFormState> {
  PaymentCollectionFormNotifier()
      : super(const PaymentCollectionFormState.initial());

  /// Initialize form with expected amount from destination
  void initialize(double amountExpected) {
    state = state.copyWith(amountExpected: amountExpected);
  }

  /// Update collected amount
  void setAmountCollected(double amount) {
    // Validate amount is not negative
    if (amount < 0) return;

    // Calculate shortage if amount < expected
    final shortage =
        amount < state.amountExpected ? state.amountExpected - amount : null;
    final shortagePercentage = shortage != null && state.amountExpected > 0
        ? (shortage / state.amountExpected) * 100
        : null;

    state = state.copyWith(
      amountCollected: amount,
      shortageAmount: shortage,
      shortagePercentage: shortagePercentage,
    );
  }

  /// Update payment method
  void setPaymentMethod(PaymentMethod method) {
    state = state.copyWith(paymentMethod: method);
  }

  /// Update CliQ reference (required if CliQ method selected)
  void setCliqReference(String reference) {
    state = state.copyWith(cliqReference: reference);
  }

  /// Update shortage reason (required if shortage exists)
  void setShortageReason(String? reason) {
    state = state.copyWith(shortageReason: reason);
  }

  /// Update optional notes
  void setNotes(String? notes) {
    state = state.copyWith(notes: notes);
  }

  /// Reset form to initial state
  void reset() {
    state = const PaymentCollectionFormState.initial();
  }
}

/// Form state for payment collection
class PaymentCollectionFormState {
  final double amountExpected;
  final double amountCollected;
  final PaymentMethod paymentMethod;
  final String? cliqReference;
  final String? shortageReason;
  final double? shortageAmount;
  final double? shortagePercentage;
  final String? notes;

  const PaymentCollectionFormState({
    this.amountExpected = 0.0,
    this.amountCollected = 0.0,
    this.paymentMethod = PaymentMethod.cash,
    this.cliqReference,
    this.shortageReason,
    this.shortageAmount,
    this.shortagePercentage,
    this.notes,
  });

  const PaymentCollectionFormState.initial()
      : amountExpected = 0.0,
        amountCollected = 0.0,
        paymentMethod = PaymentMethod.cash,
        cliqReference = null,
        shortageReason = null,
        shortageAmount = null,
        shortagePercentage = null,
        notes = null;

  /// Check if there's a shortage
  bool get hasShortage => shortageAmount != null && shortageAmount! > 0;

  /// Check if fully collected
  bool get isFullyCollected => amountCollected >= amountExpected;

  /// Get shortage percentage as integer for display (e.g., "25" not "25.0")
  String get shortagePercentageDisplay =>
      shortagePercentage?.toStringAsFixed(0) ?? '0';

  /// Get validation error if form is invalid
  /// Returns null if form is valid
  String? getValidationError() {
    // Amount must be between 0 and expected
    if (amountCollected < 0 || amountCollected > amountExpected) {
      return 'Amount must be between 0 and $amountExpected';
    }

    // CliQ methods require reference
    if (paymentMethod.requiresReference && (cliqReference?.isEmpty ?? true)) {
      return 'CliQ reference is required for ${paymentMethod.label}';
    }

    // Shortage requires reason
    if (hasShortage && (shortageReason?.isEmpty ?? true)) {
      return 'Shortage reason is required when payment is incomplete';
    }

    return null; // Form is valid
  }

  /// Check if form is valid for submission
  bool get isValid => getValidationError() == null;

  /// Convert to PaymentCollectionModel for API submission
  PaymentCollectionModel toModel(String destinationId) {
    return PaymentCollectionModel(
      destinationId: destinationId,
      amountExpected: amountExpected,
      amountCollected: amountCollected,
      paymentMethod: paymentMethod,
      paymentStatus: isFullyCollected
          ? PaymentStatus.full
          : (amountCollected > 0 ? PaymentStatus.partial : PaymentStatus.pending),
      cliqReference: cliqReference,
      shortageReason:
          shortageReason != null ? ShortageReason.fromString(shortageReason!) : null,
      shortageAmount: shortageAmount,
      shortagePercentage: shortagePercentage,
      notes: notes,
    );
  }

  PaymentCollectionFormState copyWith({
    double? amountExpected,
    double? amountCollected,
    PaymentMethod? paymentMethod,
    String? cliqReference,
    String? shortageReason,
    double? shortageAmount,
    double? shortagePercentage,
    String? notes,
  }) {
    return PaymentCollectionFormState(
      amountExpected: amountExpected ?? this.amountExpected,
      amountCollected: amountCollected ?? this.amountCollected,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      cliqReference: cliqReference ?? this.cliqReference,
      shortageReason: shortageReason ?? this.shortageReason,
      shortageAmount: shortageAmount ?? this.shortageAmount,
      shortagePercentage: shortagePercentage ?? this.shortagePercentage,
      notes: notes ?? this.notes,
    );
  }
}

/// State notifier for submitting payment collection
class PaymentCollectionNotifier
    extends StateNotifier<AsyncValue<PaymentCollectionModel?>> {
  final TripsRepository _repository;

  PaymentCollectionNotifier(this._repository)
      : super(const AsyncValue.data(null));

  /// Submit payment collection to API
  Future<void> submitPayment(
    String tripId,
    String destinationId,
    PaymentCollectionFormState formState,
  ) async {
    // Validate form before submission
    if (formState.getValidationError() != null) {
      state = AsyncValue.error(
        formState.getValidationError()!,
        StackTrace.current,
      );
      return;
    }

    state = const AsyncValue.loading();
    state = await AsyncValue.guard(
      () => _repository.collectPayment(
        tripId,
        destinationId,
        amountCollected: formState.amountCollected,
        paymentMethod: formState.paymentMethod.toApiString(),
        cliqReference: formState.cliqReference,
        shortageReason: formState.shortageReason,
        notes: formState.notes,
      ),
    );
  }

  /// Clear the payment state
  void clear() {
    state = const AsyncValue.data(null);
  }
}

/// Provider for payment collection form state
final paymentCollectionFormProvider =
    StateNotifierProvider<PaymentCollectionFormNotifier,
        PaymentCollectionFormState>((ref) {
  return PaymentCollectionFormNotifier();
});

/// Provider for submitting payment collection
final paymentCollectionProvider = StateNotifierProvider<
    PaymentCollectionNotifier,
    AsyncValue<PaymentCollectionModel?>>((ref) {
  final repository = ref.watch(tripsRepositoryProvider);
  return PaymentCollectionNotifier(repository);
});

/// Derived provider: check if form is valid
final paymentFormValidProvider = Provider<bool>((ref) {
  final formState = ref.watch(paymentCollectionFormProvider);
  return formState.getValidationError() == null;
});

/// Derived provider: get validation error message
final paymentFormErrorProvider = Provider<String?>((ref) {
  final formState = ref.watch(paymentCollectionFormProvider);
  return formState.getValidationError();
});

/// Derived provider: check if CliQ reference is required
final cliqReferenceRequiredProvider = Provider<bool>((ref) {
  final formState = ref.watch(paymentCollectionFormProvider);
  return formState.paymentMethod.requiresReference;
});

/// Derived provider: check if shortage reason is required
final shortageReasonRequiredProvider = Provider<bool>((ref) {
  final formState = ref.watch(paymentCollectionFormProvider);
  return formState.hasShortage;
});
