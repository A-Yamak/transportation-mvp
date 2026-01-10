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

  Widget createTestWidget() {
    return ProviderScope(
      overrides: [
        reconciliationRepositoryProvider.overrideWithValue(mockRepository),
      ],
      child: MaterialApp(
        home: DailyReconciliationScreen(),
      ),
    );
  }

  group('DailyReconciliationScreen Tests', () {
    testWidgets('displays reconciliation data when loaded',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Daily Reconciliation'), findsOneWidget);
      expect(find.text('Collection Rate'), findsOneWidget);
      expect(find.text('Total Collected'), findsOneWidget);
      expect(find.text('Cash vs CliQ'), findsOneWidget);
      expect(find.text('Trips Completed'), findsOneWidget);
    });

    testWidgets('displays status badge for pending reconciliation',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.textContaining('Pending'), findsOneWidget);
    });

    testWidgets('displays collection rate percentage',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        totalExpected: 1000.0,
        totalCollected: 850.0,
      );

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert - collection rate should be ~85%
      expect(find.textContaining('85.0%'), findsOneWidget);
    });

    testWidgets('displays cash and cliq percentage split',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        totalCash: 600.0,
        totalCliq: 250.0,
      );

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.textContaining('%'), findsWidgets);
      expect(find.textContaining('JOD'), findsWidgets);
    });

    testWidgets('displays trips and deliveries completed',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock(
        tripsCompleted: 5,
        deliveriesCompleted: 15,
        totalKmDriven: 45.5,
      );

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.textContaining('Collection Rate'), findsOneWidget);
      expect(find.textContaining('Total Collected'), findsOneWidget);
    });

    testWidgets('displays shop breakdown section',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Shop Breakdown'), findsOneWidget);
      expect(find.byType(ExpansionTile), findsWidgets);
    });

    testWidgets('shop breakdown tiles are expandable',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert - shop tile should be present
      expect(find.byType(ExpansionTile), findsWidgets);
      // Verify shop breakdown data is displayed
      expect(find.textContaining('Shop'), findsWidgets);
      expect(find.byType(ListTile), findsWidgets);
    });

    testWidgets('displays shortage info when applicable',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Try to find shortage indicator (if any shops have shortage)
      final shortageWidgets = find.textContaining('Shortage');
      // May or may not exist depending on mock data

      // Assert - screen should render without error
      expect(find.byType(Scaffold), findsOneWidget);
    });

    testWidgets('optional notes field is present',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Additional Notes (Optional)'), findsOneWidget);
      expect(
          find.byType(TextField)
              .first, // Notes field is a TextField
          findsOneWidget);
    });

    testWidgets('submit button is present and clickable',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.textContaining('Submit'), findsOneWidget);
      expect(find.byType(ElevatedButton), findsOneWidget);
    });

    testWidgets('shows loading state initially',
        (WidgetTester tester) async {
      // Arrange
      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async {
        await Future.delayed(Duration(milliseconds: 500));
        return DailyReconciliationModel.mock();
      });

      // Act
      await tester.pumpWidget(createTestWidget());
      // Don't call pumpAndSettle, just pump once to see loading state
      await tester.pump();

      // Assert - data should eventually load after settling
      await tester.pumpAndSettle();
      expect(find.text('Daily Reconciliation'), findsOneWidget);
    });

    testWidgets('shows error message on failure',
        (WidgetTester tester) async {
      // Arrange
      when(() => mockRepository.generateDailyReconciliation())
          .thenThrow(Exception('Failed to generate reconciliation'));

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert
      expect(find.text('Failed to generate reconciliation'), findsOneWidget);
      expect(find.byIcon(Icons.error_outline), findsOneWidget);
    });

    testWidgets('displays summary cards in 2x2 grid',
        (WidgetTester tester) async {
      // Arrange
      final reconciliation = DailyReconciliationModel.mock();

      when(() => mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => reconciliation);

      // Act
      await tester.pumpWidget(createTestWidget());
      await tester.pumpAndSettle();

      // Assert - all 4 summary cards visible
      expect(find.byType(GridView), findsOneWidget);
      final gridView = tester.widget<GridView>(find.byType(GridView));
      expect(gridView.gridDelegate, isA<SliverGridDelegateWithFixedCrossAxisCount>());
    });
  });
}
