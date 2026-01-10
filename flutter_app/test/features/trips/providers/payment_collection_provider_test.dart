import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:transportation_app/features/trips/data/trips_repository.dart';
import 'package:transportation_app/features/trips/data/models/payment_collection_model.dart';
import 'package:transportation_app/features/trips/data/models/payment_method_enum.dart';
import 'package:transportation_app/features/trips/providers/payment_collection_provider.dart';

import 'payment_collection_provider_test.mocks.dart';

@GenerateMocks([TripsRepository])
void main() {
  late ProviderContainer container;
  late MockTripsRepository mockRepository;

  setUp(() {
    mockRepository = MockTripsRepository();
    container = ProviderContainer(
      overrides: [
        paymentRepositoryProvider.overrideWithValue(mockRepository),
      ],
    );
  });

  group('PaymentCollectionFormNotifier Tests', () {
    test('initializes with default values', () {
      final state = container.read(paymentCollectionFormProvider);

      expect(state.amountExpected, 0.0);
      expect(state.amountCollected, 0.0);
      expect(state.paymentMethod, PaymentMethod.cash);
      expect(state.shortageReason, null);
    });

    test('sets amount expected', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      final state = container.read(paymentCollectionFormProvider);

      expect(state.amountExpected, 1000.0);
    });

    test('sets amount collected and calculates shortage', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(750.0);

      final state = container.read(paymentCollectionFormProvider);

      expect(state.amountCollected, 750.0);
      expect(state.shortageAmount, 250.0);
      expect(state.shortagePercentage, 25.0);
      expect(state.hasShortage, true);
    });

    test('full payment has no shortage', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(1000.0);

      final state = container.read(paymentCollectionFormProvider);

      expect(state.shortageAmount, null);
      expect(state.shortagePercentage, null);
      expect(state.hasShortage, false);
      expect(state.isFullyCollected, true);
    });

    test('sets payment method', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .setPaymentMethod(PaymentMethod.cliqNow);

      final state = container.read(paymentCollectionFormProvider);

      expect(state.paymentMethod, PaymentMethod.cliqNow);
    });

    test('sets CliQ reference', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .setPaymentMethod(PaymentMethod.cliqNow);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setCliqReference('REF-123');

      final state = container.read(paymentCollectionFormProvider);

      expect(state.cliqReference, 'REF-123');
    });

    test('sets shortage reason', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(500.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setShortageReason('customer_refused');

      final state = container.read(paymentCollectionFormProvider);

      expect(state.shortageReason, 'customer_refused');
    });

    test('validates required CliQ reference', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setPaymentMethod(PaymentMethod.cliqNow);

      final error = container
          .read(paymentCollectionFormProvider)
          .getValidationError();

      expect(error, isNotNull);
      expect(error, contains('CliQ reference is required'));
    });

    test('validates required shortage reason', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(500.0);

      final error = container
          .read(paymentCollectionFormProvider)
          .getValidationError();

      expect(error, isNotNull);
      expect(error, contains('Shortage reason is required'));
    });

    test('form is valid when all requirements met', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(1000.0);

      final error = container
          .read(paymentCollectionFormProvider)
          .getValidationError();

      expect(error, null);
      expect(
          container.read(paymentFormValidProvider), true);
    });

    test('resets form to initial state', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(500.0);

      container
          .read(paymentCollectionFormProvider.notifier)
          .reset();

      final state = container.read(paymentCollectionFormProvider);

      expect(state.amountExpected, 0.0);
      expect(state.amountCollected, 0.0);
    });
  });

  group('PaymentCollectionNotifier Tests', () {
    test('submits valid payment', () async {
      // Arrange
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(1000.0);

      final mockPayment = PaymentCollectionModel.mock(
        amountExpected: 1000.0,
        amountCollected: 1000.0,
      );

      when(mockRepository.collectPayment(
        any,
        any,
        amountCollected: anyNamed('amountCollected'),
        paymentMethod: anyNamed('paymentMethod'),
        cliqReference: anyNamed('cliqReference'),
        shortageReason: anyNamed('shortageReason'),
        notes: anyNamed('notes'),
      )).thenAnswer((_) async => mockPayment);

      // Act
      await container
          .read(paymentCollectionProvider.notifier)
          .submitPayment(
            'trip-1',
            'dest-1',
            container.read(paymentCollectionFormProvider),
          );

      // Assert
      final state = container.read(paymentCollectionProvider);
      expect(state.asData?.value, isNotNull);
      expect(state.asData?.value?.isFullyCollected, true);
    });

    test('rejects invalid form', () async {
      // Arrange - no shortage reason for partial payment
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(500.0);

      // Act
      await container
          .read(paymentCollectionProvider.notifier)
          .submitPayment(
            'trip-1',
            'dest-1',
            container.read(paymentCollectionFormProvider),
          );

      // Assert
      final state = container.read(paymentCollectionProvider);
      expect(state.hasError, true);
    });

    test('clears payment state', () {
      container
          .read(paymentCollectionProvider.notifier)
          .clear();

      final state = container.read(paymentCollectionProvider);

      expect(state.asData?.value, null);
    });
  });

  group('Derived Providers Tests', () {
    test('cliqReferenceRequiredProvider reflects payment method', () {
      // Cash doesn't require reference
      container
          .read(paymentCollectionFormProvider.notifier)
          .setPaymentMethod(PaymentMethod.cash);
      expect(container.read(cliqReferenceRequiredProvider), false);

      // CliQ requires reference
      container
          .read(paymentCollectionFormProvider.notifier)
          .setPaymentMethod(PaymentMethod.cliqNow);
      expect(container.read(cliqReferenceRequiredProvider), true);
    });

    test('shortageReasonRequiredProvider reflects shortage', () {
      container
          .read(paymentCollectionFormProvider.notifier)
          .initialize(1000.0);

      // No shortage - no reason required
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(1000.0);
      expect(container.read(shortageReasonRequiredProvider), false);

      // Shortage - reason required
      container
          .read(paymentCollectionFormProvider.notifier)
          .setAmountCollected(500.0);
      expect(container.read(shortageReasonRequiredProvider), true);
    });
  });
}
