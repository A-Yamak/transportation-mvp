import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:driver_app/features/trips/data/models/destination_model.dart';
import 'package:driver_app/features/trips/data/models/payment_collection_model.dart';
import 'package:driver_app/features/trips/data/models/payment_method_enum.dart';
import 'package:driver_app/features/trips/data/models/payment_status_enum.dart';
import 'package:driver_app/features/trips/data/trips_repository.dart';
import 'package:driver_app/features/trips/presentation/widgets/payment_collection_dialog.dart';
import 'package:driver_app/features/trips/providers/payment_collection_provider.dart';

class MockTripsRepository extends Mock implements TripsRepository {}

void main() {
  late MockTripsRepository mockRepository;

  setUp(() {
    mockRepository = MockTripsRepository();
  });

  group('Payment Collection Integration Flow', () {
    testWidgets('Complete flow: open dialog → select payment method → enter amount → submit',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: '123 Main Street',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      final paymentModel = PaymentCollectionModel.mock(
        amountCollected: 1000.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.full,
      );

      when(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 1000.0,
        paymentMethod: 'cash',
      )).thenAnswer((_) async => paymentModel);

      var successCalled = false;

      // Act & Assert
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Dialog should open with expected amount
      expect(find.text('Collect Payment'), findsOneWidget);
      expect(find.text('JOD 1000.00'), findsOneWidget);

      // Cash should be default payment method
      expect(find.text('Cash'), findsWidgets);

      // Enter full amount
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      // Submit button should be enabled
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNotNull);

      // Tap submit
      await tester.tap(submitButton);
      await tester.pumpAndSettle();

      // Verify API was called
      verify(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 1000.0,
        paymentMethod: 'cash',
      )).called(1);

      // Success callback should be called
      expect(successCalled, true);
    });

    testWidgets('Partial payment flow: collect 750 JOD, select shortage reason',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 2,
        address: '456 Oak Avenue',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      final paymentModel = PaymentCollectionModel.mock(
        amountCollected: 750.0,
        amountExpected: 1000.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
        hasShortage: true,
        shortageAmount: 250.0,
      );

      when(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 750.0,
        paymentMethod: 'cash',
        shortageReason: 'customer_refused',
      )).thenAnswer((_) async => paymentModel);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Enter partial amount
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '750.00');
      await tester.pumpAndSettle();

      // Shortage should be displayed
      expect(find.text('Shortage'), findsOneWidget);
      expect(find.text('JOD 250.00'), findsOneWidget);

      // Shortage reason dropdown should appear
      expect(find.text('Shortage Reason'), findsOneWidget);

      // Select a shortage reason
      await tester.tap(find.byType(DropdownButtonFormField<dynamic>).first);
      await tester.pumpAndSettle();

      // Find and tap the first reason option
      await tester.tap(find.text('Customer Refused').first);
      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle();

      // Verify API called with shortage reason
      verify(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 750.0,
        paymentMethod: 'cash',
        shortageReason: 'customer_refused',
      )).called(1);

      expect(successCalled, true);
    });

    testWidgets('CliQ payment flow: select CliQ Now and enter reference',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 3,
        address: '789 Pine Street',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 500.0,
      );

      final paymentModel = PaymentCollectionModel.mock(
        amountCollected: 500.0,
        paymentMethod: PaymentMethod.cliqNow,
        paymentStatus: PaymentStatus.full,
        cliqReference: 'CLQ-2026-001',
      );

      when(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 500.0,
        paymentMethod: 'cliq_now',
        cliqReference: 'CLQ-2026-001',
      )).thenAnswer((_) async => paymentModel);

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Select CliQ Now
      await tester.tap(find.text('CliQ Now'));
      await tester.pumpAndSettle();

      // Amount field should be pre-filled with expected amount
      final amountField = find.byType(TextField).at(0);
      expect(tester.widget<TextField>(amountField).controller!.text,
          isNotEmpty);

      // CliQ reference field should appear
      await tester.pumpAndSettle();
      expect(find.text('CliQ Reference'), findsOneWidget);

      // Enter CliQ reference
      final cliqRefFields = find.byType(TextField);
      // The CliQ reference is the second text field (after amount)
      await tester.enterText(cliqRefFields.at(1), 'CLQ-2026-001');
      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle();

      // Verify API called with CliQ reference
      verify(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 500.0,
        paymentMethod: 'cliq_now',
        cliqReference: 'CLQ-2026-001',
      )).called(1);

      expect(successCalled, true);
    });

    testWidgets('Overpayment validation: prevent amount > expected',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 4,
        address: '321 Elm Street',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 500.0,
      );

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Try to enter amount > expected
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '600.00'); // > 500
      await tester.pumpAndSettle();

      // Submit button should be disabled because form validation should fail
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNull);

      // API should never be called
      verifyNever(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 600.0,
        paymentMethod: 'cash',
      ));
    });

    testWidgets('API error handling: show error message on failure',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 5,
        address: '654 Birch Lane',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      when(mockRepository.collectPayment(
        tripId: 'trip-1',
        destinationId: 'dest-1',
        amountCollected: 1000.0,
        paymentMethod: 'cash',
      )).thenThrow(Exception('Network error'));

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Enter amount and submit
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle(Duration(seconds: 2));

      // Error message should be displayed
      expect(find.textContaining('Network error'), findsOneWidget);

      // Success callback should NOT be called
      expect(successCalled, false);
    });

    testWidgets('Validation prevents submit without amount',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 6,
        address: '987 Maple Drive',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 750.0,
      );

      var successCalled = false;

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            tripsRepositoryProvider.overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: Scaffold(
              body: SingleChildScrollView(
                child: PaymentCollectionDialog(
                  tripId: 'trip-1',
                  destinationId: 'dest-1',
                  destination: destination,
                  onSuccess: () => successCalled = true,
                ),
              ),
            ),
          ),
        ),
      );

      // Don't enter any amount
      // Submit button should be disabled
      final submitButton = find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNull);

      // API should never be called
      verifyNever(mockRepository.collectPayment(
        tripId: any,
        destinationId: any,
        amountCollected: any,
        paymentMethod: any,
      ));
    });
  });
}
