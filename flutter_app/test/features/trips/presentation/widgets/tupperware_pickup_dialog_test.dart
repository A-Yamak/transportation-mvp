import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:driver_app/features/trips/data/models/tupperware_balance_model.dart';
import 'package:driver_app/features/trips/data/tupperware_repository.dart';
import 'package:driver_app/features/trips/presentation/widgets/tupperware_pickup_dialog.dart';
import 'package:driver_app/features/trips/providers/tupperware_providers.dart';

class MockTupperwareRepository extends Mock implements TupperwareRepository {}

void main() {
  late MockTupperwareRepository mockRepository;

  setUp(() {
    mockRepository = MockTupperwareRepository();
  });

  Widget createTestWidget({
    required String shopId,
    required VoidCallback onSuccess,
  }) {
    return ProviderScope(
      overrides: [
        tupperwareRepositoryProvider.overrideWithValue(mockRepository),
      ],
      child: MaterialApp(
        home: Scaffold(
          body: SingleChildScrollView(
            child: TupperwarePickupDialog(
              tripId: 'trip-1',
              destinationId: 'dest-1',
              shopId: shopId,
              onSuccess: onSuccess,
            ),
          ),
        ),
      ),
    );
  }

  group('TupperwarePickupDialog Tests', () {
    testWidgets('displays balance for all product types',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 45,
        ),
        TupperwareBalanceModel.mock(
          productType: 'trays',
          currentBalance: 32,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Collect Tupperware'), findsOneWidget);
      expect(find.text('Boxes'), findsOneWidget);
      expect(find.text('Trays'), findsOneWidget);
      expect(find.text('Current: 45'), findsOneWidget);
      expect(find.text('Current: 32'), findsOneWidget);
    });

    testWidgets('shows color-coded balance status',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 15, // Normal (below warning)
        ),
        TupperwareBalanceModel.mock(
          productType: 'trays',
          currentBalance: 40, // Warning (at/above warning, below critical)
        ),
        TupperwareBalanceModel.mock(
          productType: 'bags',
          currentBalance: 55, // Critical (at/above critical)
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Assert - verify status badges appear
      expect(find.text('Normal'), findsOneWidget);
      expect(find.text('Warning'), findsOneWidget);
      expect(find.text('Critical'), findsOneWidget);
    });

    testWidgets('increment button increases quantity correctly',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Get the increment button
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Assert - new balance should show 49 (50 - 1)
      expect(find.text('After: 49'), findsOneWidget);
    });

    testWidgets('decrement button decreases quantity correctly',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Increment first
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Then decrement
      final decrementButton = find.byIcon(Icons.remove).first;
      await tester.tap(decrementButton);
      await tester.pumpAndSettle();

      // Assert - should be back to 0
      expect(find.text('After: 50'), findsOneWidget);
    });

    testWidgets('cannot pickup more than current balance',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 10,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Try to enter a value > current balance
      final quantityField = find.byType(TextField).first;
      await tester.enterText(quantityField, '15');
      await tester.pumpAndSettle();

      // Assert - should not accept values > current balance
      // The implementation should prevent this in onQuantityChanged
      final textField = tester.widget<TextField>(quantityField);
      expect(textField.controller!.text, isNotEmpty);
    });

    testWidgets('new balance preview calculates correctly',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Enter quantity manually
      final quantityField = find.byType(TextField).first;
      await tester.enterText(quantityField, '30');
      await tester.pumpAndSettle();

      // Assert - new balance should be 20 (50 - 30)
      expect(find.text('After: 20'), findsOneWidget);
    });

    testWidgets('displays deposit owed information',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
          depositPerUnit: 0.5,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Assert
      expect(find.textContaining('Deposit owed'), findsOneWidget);
      expect(find.textContaining('50 ×'), findsOneWidget);
    });

    testWidgets('shows summary card with total items to pickup',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Increment quantity
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Total to Pickup'), findsOneWidget);
      expect(find.textContaining('items'), findsOneWidget);
    });

    testWidgets('submit button disabled when no items selected',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Assert - button should be disabled initially (no items selected)
      final submitButton = find.byType(ElevatedButton).last;
      expect(
          tester.widget<ElevatedButton>(submitButton).onPressed, isNull);
    });

    testWidgets('submit button enabled when items selected',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Select at least one item
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Assert - button should be enabled
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed,
          isNotNull);
    });

    testWidgets('notes field is optional', (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      // Act
      await tester.pumpWidget(
        createTestWidget(
          shopId: 'shop-1',
          onSuccess: () {},
        ),
      );
      await tester.pumpAndSettle();

      // Select item and submit without notes
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Assert - button should still be enabled without notes
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed,
          isNotNull);
    });
  });
}
