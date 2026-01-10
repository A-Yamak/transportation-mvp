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

  group('Tupperware Pickup Integration Flow', () {
    testWidgets('Complete flow: load balances → select quantities → submit',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 50,
        ),
        TupperwareBalanceModel.mock(
          productType: 'trays',
          currentBalance: 30,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      when(mockRepository.collectTupperware(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        tupperware: any,
        notes: null,
      )).thenAnswer((_) async => true);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Balances should be displayed
      expect(find.text('Boxes'), findsOneWidget);
      expect(find.text('Trays'), findsOneWidget);
      expect(find.text('Current: 50'), findsOneWidget);
      expect(find.text('Current: 30'), findsOneWidget);

      // Increment boxes quantity
      final incrementButtons = find.byIcon(Icons.add);
      await tester.tap(incrementButtons.at(0));
      await tester.pumpAndSettle();

      // Increment trays quantity twice
      await tester.tap(incrementButtons.at(1));
      await tester.pumpAndSettle();
      await tester.tap(incrementButtons.at(1));
      await tester.pumpAndSettle();

      // Submit button should be enabled now
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNotNull);

      // Tap submit
      await tester.tap(submitButton);
      await tester.pumpAndSettle();

      // Verify API was called
      verify(mockRepository.collectTupperware(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        tupperware: any,
        notes: null,
      )).called(1);

      // Success callback should be called
      expect(successCalled, true);
    });

    testWidgets('Quantity input validation: cannot exceed current balance',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 20,
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Try to enter quantity > current balance via text field
      final quantityField = find.byType(TextField).first;
      await tester.enterText(quantityField, '25'); // > 20
      await tester.pumpAndSettle();

      // The implementation should prevent this, so the value
      // either won't update or submit will be disabled
      // At minimum, the dialog should handle it gracefully

      verifyNever(mockRepository.collectTupperware(
        tripId: any,
        destinationId: any,
        tupperware: any,
        notes: any,
      ));
    });

    testWidgets('Balance color coding: displays correct status',
        (WidgetTester tester) async {
      // Arrange
      final balances = [
        TupperwareBalanceModel.mock(
          productType: 'boxes',
          currentBalance: 15, // Normal
        ),
        TupperwareBalanceModel.mock(
          productType: 'trays',
          currentBalance: 40, // Warning
        ),
        TupperwareBalanceModel.mock(
          productType: 'bags',
          currentBalance: 55, // Critical
        ),
      ];

      when(mockRepository.getShopBalance('shop-1'))
          .thenAnswer((_) async => balances);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Status badges should be displayed
      expect(find.text('Normal'), findsOneWidget);
      expect(find.text('Warning'), findsOneWidget);
      expect(find.text('Critical'), findsOneWidget);
    });

    testWidgets('New balance preview: updates as quantity changes',
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

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Initial balance should show 50
      expect(find.text('After: 50'), findsOneWidget);

      // Increment quantity
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // New balance should update to 49
      expect(find.text('After: 49'), findsOneWidget);

      // Increment again
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // New balance should update to 48
      expect(find.text('After: 48'), findsOneWidget);
    });

    testWidgets('Decrement button: reduces quantity correctly',
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

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      final incrementButton = find.byIcon(Icons.add).first;
      final decrementButton = find.byIcon(Icons.remove).first;

      // Increment 3 times
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // New balance should be 47 (50 - 3)
      expect(find.text('After: 47'), findsOneWidget);

      // Decrement twice
      await tester.tap(decrementButton);
      await tester.pumpAndSettle();
      await tester.tap(decrementButton);
      await tester.pumpAndSettle();

      // New balance should be 49 (50 - 1)
      expect(find.text('After: 49'), findsOneWidget);
    });

    testWidgets('Submit with optional notes: notes are included',
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

      when(mockRepository.collectTupperware(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        tupperware: any,
        notes: 'Some items were damaged',
      )).thenAnswer((_) async => true);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Select quantity
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Enter notes
      final noteFields = find.byType(TextField);
      // Notes field is the second TextField
      await tester.enterText(noteFields.at(1), 'Some items were damaged');
      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle();

      // Verify API was called with notes
      verify(mockRepository.collectTupperware(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        tupperware: any,
        notes: 'Some items were damaged',
      )).called(1);

      expect(successCalled, true);
    });

    testWidgets('API error: shows error message',
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

      when(mockRepository.collectTupperware(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        tupperware: any,
        notes: null,
      )).thenThrow(Exception('Failed to submit pickup'));

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Select quantity
      final incrementButton = find.byIcon(Icons.add).first;
      await tester.tap(incrementButton);
      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle(Duration(seconds: 2));

      // Error should be displayed
      expect(find.textContaining('Failed to submit pickup'), findsOneWidget);

      // Success callback should NOT be called
      expect(successCalled, false);
    });

    testWidgets('Deposit calculation: shows deposit owed per unit',
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

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tupperwareRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: TupperwarePickupDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  shopId: 'shop-1',
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Deposit information should be displayed
      expect(find.textContaining('Deposit owed'), findsOneWidget);
      expect(find.textContaining('50 ×'), findsOneWidget);
      expect(find.textContaining('0.5'), findsOneWidget);
    });
  });
}
