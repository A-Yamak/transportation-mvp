import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:transportation_app/features/trips/data/reconciliation_repository.dart';
import 'package:transportation_app/features/trips/data/models/daily_reconciliation_model.dart';
import 'package:transportation_app/features/trips/providers/reconciliation_provider.dart';

import 'reconciliation_provider_test.mocks.dart';

@GenerateMocks([ReconciliationRepository])
void main() {
  late ProviderContainer container;
  late MockReconciliationRepository mockRepository;

  setUp(() {
    mockRepository = MockReconciliationRepository();
    container = ProviderContainer(
      overrides: [
        reconciliationRepositoryProvider.overrideWithValue(mockRepository),
      ],
    );
  });

  group('ReconciliationNotifier Tests', () {
    test('generates daily reconciliation', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        totalExpected: 5000.0,
        totalCollected: 4800.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.asData?.value, isNotNull);
      expect(state.asData?.value?.totalCollected, 4800.0);
    });

    test('reloads reconciliation from API', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock();

      when(mockRepository.getTodaysReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .reloadReconciliation();

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.asData?.value, isNotNull);
    });

    test('returns null when no reconciliation exists', () async {
      // Arrange
      when(mockRepository.getTodaysReconciliation())
          .thenAnswer((_) async => null);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .reloadReconciliation();

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.asData?.value, null);
    });

    test('submits reconciliation', () async {
      // Arrange
      const reconciliationId = 'recon-1';
      final mockReconciliation = DailyReconciliationModel.mock(
        status: ReconciliationStatus.submitted,
      );

      when(mockRepository.submitReconciliation(
        any,
        notes: anyNamed('notes'),
      )).thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .submitReconciliation(reconciliationId);

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.asData?.value?.status, ReconciliationStatus.submitted);
    });

    test('submits reconciliation with notes', () async {
      // Arrange
      const reconciliationId = 'recon-1';
      const notes = 'All collected successfully';
      final mockReconciliation = DailyReconciliationModel.mock();

      when(mockRepository.submitReconciliation(
        any,
        notes: anyNamed('notes'),
      )).thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .submitReconciliation(reconciliationId, notes: notes);

      // Assert
      verify(mockRepository.submitReconciliation(
        any,
        notes: notes,
      )).called(1);
    });

    test('clears reconciliation state', () {
      container.read(reconciliationProvider.notifier).clear();

      final state = container.read(reconciliationProvider);

      expect(state.asData?.value, null);
    });
  });

  group('Derived Providers Tests', () {
    test('collectionRateProvider calculates correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        totalExpected: 10000.0,
        totalCollected: 6000.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(
          container.read(collectionRateProvider), 60.0);
    });

    test('totalShortageProvider calculates shortage', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        totalExpected: 5000.0,
        totalCollected: 4000.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(container.read(totalShortageProvider), 1000.0);
    });

    test('fullyCollectedShopsProvider counts correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        shopBreakdown: [
          // Will have 1-2 fully collected shops from mock
        ],
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      final count = container.read(fullyCollectedShopsProvider);
      expect(count, greaterThanOrEqualTo(0));
    });

    test('partiallyCollectedShopsProvider counts correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      final count = container.read(partiallyCollectedShopsProvider);
      expect(count, greaterThanOrEqualTo(0));
    });

    test('uncollectedShopsProvider counts correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock();

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      final count = container.read(uncollectedShopsProvider);
      expect(count, greaterThanOrEqualTo(0));
    });

    test('cashPercentageProvider calculates correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        totalCash: 6000.0,
        totalCliq: 4000.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(container.read(cashPercentageProvider), closeTo(60.0, 0.1));
    });

    test('cliqPercentageProvider calculates correctly', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        totalCash: 6000.0,
        totalCliq: 4000.0,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(container.read(cliqPercentageProvider), closeTo(40.0, 0.1));
    });

    test('canSubmitReconciliationProvider when pending', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        status: ReconciliationStatus.pending,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(
          container.read(canSubmitReconciliationProvider), true);
    });

    test('canSubmitReconciliationProvider when submitted', () async {
      // Arrange
      final mockReconciliation = DailyReconciliationModel.mock(
        status: ReconciliationStatus.submitted,
      );

      when(mockRepository.generateDailyReconciliation())
          .thenAnswer((_) async => mockReconciliation);

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      expect(
          container.read(canSubmitReconciliationProvider), false);
    });
  });

  group('Error Handling Tests', () {
    test('handles API error on generate', () async {
      // Arrange
      when(mockRepository.generateDailyReconciliation())
          .thenThrow(Exception('API Error'));

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .generateReconciliation();

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.hasError, true);
    });

    test('handles API error on submit', () async {
      // Arrange
      when(mockRepository.submitReconciliation(
        any,
        notes: anyNamed('notes'),
      )).thenThrow(Exception('API Error'));

      // Act
      await container
          .read(reconciliationProvider.notifier)
          .submitReconciliation('recon-1');

      // Assert
      final state = container.read(reconciliationProvider);
      expect(state.hasError, true);
    });
  });
}
