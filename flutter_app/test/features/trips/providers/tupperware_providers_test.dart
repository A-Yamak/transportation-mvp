import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:transportation_app/features/trips/data/tupperware_repository.dart';
import 'package:transportation_app/features/trips/data/models/tupperware_balance_model.dart';
import 'package:transportation_app/features/trips/data/models/tupperware_movement_model.dart';
import 'package:transportation_app/features/trips/providers/tupperware_providers.dart';

import 'tupperware_providers_test.mocks.dart';

@GenerateMocks([TupperwareRepository])
void main() {
  late ProviderContainer container;
  late MockTupperwareRepository mockRepository;

  setUp(() {
    mockRepository = MockTupperwareRepository();
    container = ProviderContainer(
      overrides: [
        tupperwareRepositoryProvider.overrideWithValue(mockRepository),
      ],
    );
  });

  group('TupperwarePickupFormNotifier Tests', () {
    test('initializes with empty state', () {
      final state = container.read(tupperwarePickupFormProvider);

      expect(state.pickupQuantities.isEmpty, true);
      expect(state.totalQuantity, 0);
      expect(state.hasPickups, false);
    });

    test('initializes from balance', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
        TupperwareBalanceModel.mock(productType: 'trays'),
        TupperwareBalanceModel.mock(productType: 'bags'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);

      final state = container.read(tupperwarePickupFormProvider);

      expect(state.pickupQuantities.keys.length, 3);
      expect(state.pickupQuantities['boxes'], 0);
      expect(state.pickupQuantities['trays'], 0);
      expect(state.pickupQuantities['bags'], 0);
    });

    test('sets quantity for product type', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);

      final state = container.read(tupperwarePickupFormProvider);

      expect(state.getQuantity('boxes'), 10);
      expect(state.hasPickups, true);
      expect(state.totalQuantity, 10);
    });

    test('increments quantity', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes', currentBalance: 50),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .incrementQuantity('boxes', amount: 5);

      expect(container.read(tupperwarePickupFormProvider).getQuantity('boxes'),
          5);
    });

    test('decrements quantity', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .decrementQuantity('boxes', amount: 3);

      expect(container.read(tupperwarePickupFormProvider).getQuantity('boxes'),
          7);
    });

    test('doesnt decrement below zero', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 2);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .decrementQuantity('boxes', amount: 5);

      expect(container.read(tupperwarePickupFormProvider).getQuantity('boxes'),
          2); // unchanged
    });

    test('sets notes', () {
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setNotes('Some boxes damaged');

      final state = container.read(tupperwarePickupFormProvider);

      expect(state.notes, 'Some boxes damaged');
    });

    test('resets quantities to zero', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
        TupperwareBalanceModel.mock(productType: 'trays'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('trays', 5);

      container
          .read(tupperwarePickupFormProvider.notifier)
          .resetQuantities();

      final state = container.read(tupperwarePickupFormProvider);

      expect(state.getQuantity('boxes'), 0);
      expect(state.getQuantity('trays'), 0);
      expect(state.hasPickups, false);
    });

    test('converts to API format', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
        TupperwareBalanceModel.mock(productType: 'trays'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('trays', 0); // zero quantities are excluded

      final apiFormat =
          container.read(tupperwarePickupFormProvider).toApiFormat();

      expect(apiFormat.length, 1); // only boxes
      expect(apiFormat[0]['product_type'], 'boxes');
      expect(apiFormat[0]['quantity'], 10);
    });

    test('clears form', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);

      container
          .read(tupperwarePickupFormProvider.notifier)
          .clear();

      final state = container.read(tupperwarePickupFormProvider);

      expect(state.pickupQuantities.isEmpty, true);
      expect(state.totalQuantity, 0);
    });
  });

  group('TupperwareCollectionNotifier Tests', () {
    test('submits valid pickup', () async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);

      final mockMovements = [
        TupperwareMovementModel.mock(
          productType: 'boxes',
          quantityPickedup: 10,
        ),
      ];

      when(mockRepository.collectTupperware(
        any,
        any,
        tupperware: anyNamed('tupperware'),
        notes: anyNamed('notes'),
      )).thenAnswer((_) async => mockMovements);

      // Act
      await container
          .read(tupperwareCollectionProvider.notifier)
          .submitPickup(
            'trip-1',
            'dest-1',
            container.read(tupperwarePickupFormProvider),
          );

      // Assert
      final state = container.read(tupperwareCollectionProvider);
      expect(state.asData?.value, isNotNull);
      expect(state.asData?.value?.length, 1);
    });

    test('rejects pickup with no items selected', () async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);

      // Act
      await container
          .read(tupperwareCollectionProvider.notifier)
          .submitPickup(
            'trip-1',
            'dest-1',
            container.read(tupperwarePickupFormProvider),
          );

      // Assert
      final state = container.read(tupperwareCollectionProvider);
      expect(state.hasError, true);
    });

    test('clears collection state', () {
      container
          .read(tupperwareCollectionProvider.notifier)
          .clear();

      final state = container.read(tupperwareCollectionProvider);

      expect(state.asData?.value, null);
    });
  });

  group('Derived Providers Tests', () {
    test('tupperwareHasPickupsProvider reflects selections', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);

      expect(container.read(tupperwareHasPickupsProvider), false);

      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 5);

      expect(container.read(tupperwareHasPickupsProvider), true);
    });

    test('tuppwareTotalPickupProvider calculates total', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
        TupperwareBalanceModel.mock(productType: 'trays'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 10);
      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('trays', 5);

      expect(container.read(tuppwareTotalPickupProvider), 15);
    });

    test('tupperwareFormValidProvider validates', () {
      final balances = [
        TupperwareBalanceModel.mock(productType: 'boxes'),
      ];

      container
          .read(tupperwarePickupFormProvider.notifier)
          .initializeFromBalance(balances);

      expect(container.read(tupperwareFormValidProvider), false);

      container
          .read(tupperwarePickupFormProvider.notifier)
          .setQuantity('boxes', 1);

      expect(container.read(tupperwareFormValidProvider), true);
    });
  });
}
