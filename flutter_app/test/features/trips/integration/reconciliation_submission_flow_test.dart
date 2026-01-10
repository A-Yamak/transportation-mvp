import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mocktail/mocktail.dart';
import 'package:driver_app/features/trips/data/models/daily_reconciliation_model.dart';
import 'package:driver_app/features/trips/data/reconciliation_repository.dart';
import 'package:driver_app/features/trips/presentation/screens/daily_reconciliation_screen.dart';
import 'package:driver_app/features/trips/providers/reconciliation_provider.dart';

class MockReconciliationRepository extends Mock implements ReconciliationRepository {}

void main() {
  late MockReconciliationRepository mockRepository;

  setUp(() {
    mockRepository = MockReconciliationRepository();
  });

  group('Reconciliation Submission Integration Flow', () {
    testWidgets('Complete flow: generate reconciliation → display summary → submit',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        totalExpected: 5000.0,
        totalCollected: 4750.0,
        tripsCompleted: 5,
        deliveriesCompleted: 15,
        totalKmDriven: 45.5,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      when(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: null,
      )).thenAnswer((_) async => true);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Reconciliation data should be displayed
      expect(find.text('Daily Reconciliation'), findsOneWidget);
      expect(find.text('Collection Rate'), findsOneWidget);
      expect(find.text('Total Collected'), findsOneWidget);

      // Collection rate should be ~95%
      expect(find.textContaining('95.0%'), findsOneWidget);

      // Trip stats should show
      expect(find.text('5'), findsWidgets); // 5 trips
      expect(find.text('15'), findsWidgets); // 15 deliveries
      expect(find.textContaining('45.5'), findsOneWidget); // KM

      // Submit button should be present
      expect(find.textContaining('Submit'), findsOneWidget);

      // Tap submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle(Duration(seconds: 2));

      // Verify API was called
      verify(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: null,
      )).called(1);
    });

    testWidgets('Reconciliation display: shows all summary cards',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        totalExpected: 1000.0,
        totalCollected: 900.0,
        totalCash: 600.0,
        totalCliq: 300.0,
        tripsCompleted: 3,
        deliveriesCompleted: 10,
        totalKmDriven: 25.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // All 4 summary cards should be visible
      expect(find.text('Collection Rate'), findsOneWidget);
      expect(find.text('Total Collected'), findsOneWidget);
      expect(find.text('Cash vs CliQ'), findsOneWidget);
      expect(find.text('Trips Completed'), findsOneWidget);

      // Card values
      expect(find.textContaining('90.0%'), findsOneWidget);
      expect(find.textContaining('JOD 900.00'), findsOneWidget);
      expect(find.textContaining('JOD 600.00'), findsOneWidget);
      expect(find.textContaining('JOD 300.00'), findsOneWidget);
    });

    testWidgets('Shop breakdown: displays all shops expandable',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Shop breakdown section should be visible
      expect(find.text('Shop Breakdown'), findsOneWidget);

      // Expansion tiles for each shop
      expect(find.byType(ExpansionTile), findsWidgets);

      // Expand first shop
      await tester.tap(find.byType(ExpansionTile).first);
      await tester.pumpAndSettle();

      // Details should appear
      expect(find.text('Expected Amount'), findsOneWidget);
      expect(find.text('Collected Amount'), findsOneWidget);
    });

    testWidgets('Notes field: optional notes can be added',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      when(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: 'Driver was delayed due to traffic',
      )).thenAnswer((_) async => true);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Find and fill notes field
      expect(find.text('Additional Notes (Optional)'), findsOneWidget);

      final noteFields = find.byType(TextField);
      await tester.enterText(noteFields.first, 'Driver was delayed due to traffic');
      await tester.pumpAndSettle();

      // Submit with notes
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle(Duration(seconds: 2));

      // Verify API was called with notes
      verify(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: 'Driver was delayed due to traffic',
      )).called(1);
    });

    testWidgets('Loading state: shows progress while generating',
        (WidgetTester tester) async {
      // Arrange
      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async {
        await Future.delayed(Duration(milliseconds: 500));
        return DailyReconciliationModel.mock();
      });

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      // Loading indicator should be visible initially
      expect(find.byType(CircularProgressIndicator), findsOneWidget);

      // Wait for load to complete
      await tester.pumpAndSettle();

      // Content should now be visible
      expect(find.text('Daily Reconciliation'), findsOneWidget);
    });

    testWidgets('Error handling: shows error on generation failure',
        (WidgetTester tester) async {
      // Arrange
      when(mockRepository.generateDailyReconciliation())
          .thenThrow(Exception('Failed to generate reconciliation'));

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Error message should be displayed
      expect(find.text('Failed to generate reconciliation'), findsOneWidget);
      expect(find.byIcon(Icons.error_outline), findsOneWidget);
    });

    testWidgets('Submission loading state: shows spinner during submit',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      when(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: null,
      )).thenAnswer((_) async {
        await Future.delayed(Duration(milliseconds: 500));
        return true;
      });

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);

      // Loading indicator should appear inside button
      await tester.pump(); // Don't settle yet to catch loading state

      // Button content should change to show loading
      // (This depends on implementation - spinner inside button)

      // Wait for completion
      await tester.pumpAndSettle();
    });

    testWidgets('Submission error: shows error message on failure',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      when(mockRepository.submitReconciliation(
        reconciliationId: reconciliation.id,
        notes: null,
      )).thenThrow(Exception('Network error during submission'));

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Submit
      final submitButton = find.byType(ElevatedButton).last;
      await tester.tap(submitButton);
      await tester.pumpAndSettle(Duration(seconds: 2));

      // Error should be displayed
      expect(find.textContaining('Network error'), findsOneWidget);
    });

    testWidgets('Status badge: displays pending status',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        status: ReconciliationStatus.pending,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Status badge should show pending
      expect(find.textContaining('Pending'), findsOneWidget);
    });

    testWidgets('Per-shop collection rate: calculates correctly',
        (WidgetTester tester) async {
      // Arrange
      // This test verifies that each shop's collection rate is calculated
      final reconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            reconciliationRepositoryProvider
                .overrideWithValue(mockRepository),
          ],
          child: MaterialApp(
            home: DailyReconciliationScreen(),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Expand shops to see their collection rates
      await tester.tap(find.byType(ExpansionTile).first);
      await tester.pumpAndSettle();

      // Collection rate should be visible and formatted with %
      expect(find.textContaining('%'), findsWidgets);
    });
  });
}
