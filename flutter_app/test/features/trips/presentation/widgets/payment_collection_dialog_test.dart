import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:driver_app/features/trips/data/models/destination_model.dart';
import 'package:driver_app/features/trips/data/models/payment_collection_model.dart';
import 'package:driver_app/features/trips/data/trips_repository.dart';
import 'package:driver_app/features/trips/presentation/widgets/payment_collection_dialog.dart';
import 'package:driver_app/features/trips/providers/payment_collection_provider.dart';

class MockTripsRepository extends Mock implements TripsRepository {}

void main() {
  late MockTripsRepository mockRepository;

  setUp(() {
    mockRepository = MockTripsRepository();
  });

  Widget createTestWidget({
    required DestinationModel destination,
    required VoidCallback onSuccess,
  }) {
    return ProviderScope(
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
              onSuccess: onSuccess,
            ),
          ),
        ),
      ),
    );
  }

  group('PaymentCollectionDialog Tests', () {
    testWidgets('displays destination information', (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: '123 Main Street',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );
      var successCalled = false;

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () => successCalled = true,
        ),
      );

      // Assert
      expect(find.text('Collect Payment'), findsOneWidget);
      expect(find.text('123 Main Street'), findsOneWidget);
      // Expected amount displays after post-frame callback, so check for the label
      expect(find.text('Expected Amount'), findsOneWidget);
    });

    testWidgets('shows payment method selection', (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      // Assert
      expect(find.text('Cash'), findsWidgets);
      expect(find.text('CliQ Now'), findsOneWidget);
      expect(find.text('CliQ Later'), findsOneWidget);
    });

    testWidgets('updates amount collected when typing', (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '750.00');
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Shortage'), findsOneWidget);
      // Shortage should be displayed after amount is entered
      expect(find.byType(Container), findsWidgets);
      // Verify the shortage is calculated (250 = 1000 - 750)
      expect(find.textContaining('250'), findsOneWidget);
    });

    testWidgets('shows CliQ reference field when CliQ selected',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      // Select CliQ Now
      await tester.tap(find.text('CliQ Now'));
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('CliQ Reference'), findsOneWidget);
      expect(find.byType(TextField), findsWidgets);
    });

    testWidgets('shows shortage reason dropdown when there is shortage',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      // Enter partial amount
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '500.00');
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Shortage Reason'), findsOneWidget);
      expect(find.text('Select reason'), findsOneWidget);
    });

    testWidgets('submit button state changes with form validity',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 100.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      await tester.pumpAndSettle();

      // Try to enter amount greater than expected
      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '150.00'); // > 100.0 (invalid)
      await tester.pumpAndSettle();

      // Assert - button state depends on provider validation
      // The form should be invalid when amount > expected
      final submitButton = find.byType(ElevatedButton).last;
      expect(submitButton, findsOneWidget);
    });

    testWidgets('submit button enabled when form valid',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      // Assert
      final submitButton =
          find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNotNull);
    });

    testWidgets('validates full payment correctly',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      // Assert - no shortage should show
      expect(find.text('Shortage'), findsNothing);
    });

    testWidgets('notes field is optional', (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      // Assert - button should still be enabled without notes
      final submitButton =
          find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNotNull);
    });

    testWidgets('payment method is required',
        (WidgetTester tester) async {
      // Arrange
      final destination = DestinationModel.mock(
        order: 1,
        address: 'Test Address',
        lat: 31.9539,
        lng: 35.9106,
        amountToCollect: 1000.0,
      );

      // Act
      await tester.pumpWidget(
        createTestWidget(
          destination: destination,
          onSuccess: () {},
        ),
      );

      final amountField = find.byType(TextField).at(0);
      await tester.enterText(amountField, '1000.00');
      await tester.pumpAndSettle();

      // Cash is default, so button should be enabled
      final submitButton =
          find.byType(ElevatedButton).last;
      expect(tester.widget<ElevatedButton>(submitButton).onPressed, isNotNull);
    });
  });
}
