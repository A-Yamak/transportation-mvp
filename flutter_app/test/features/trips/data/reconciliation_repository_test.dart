import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:dio/dio.dart';
import 'package:driver_app/core/api/api_client.dart';
import 'package:driver_app/core/api/api_exceptions.dart';
import 'package:driver_app/features/trips/data/reconciliation_repository.dart';

import 'reconciliation_repository_test.mocks.dart';

@GenerateMocks([ApiClient])
void main() {
  late MockApiClient mockApiClient;
  late ReconciliationRepository repository;

  setUp(() {
    mockApiClient = MockApiClient();
    repository = ReconciliationRepository(mockApiClient);
  });

  group('ReconciliationRepository Tests', () {
    group('generateDailyReconciliation', () {
      test('generates daily reconciliation with shop breakdown', () async {
        // Arrange
        final mockResponse = Response(
          data: {
            'data': {
              'id': 'recon-1',
              'reconciliation_date': '2026-01-10',
              'total_expected': 5000.0,
              'total_collected': 4800.0,
              'total_cash': 3000.0,
              'total_cliq': 1800.0,
              'trips_completed': 5,
              'deliveries_completed': 15,
              'total_km_driven': 45.5,
              'status': 'pending',
              'shop_breakdown': [
                {
                  'shop_id': 'SHOP-001',
                  'shop_name': 'Ahmad Shop',
                  'amount_expected': 1500.0,
                  'amount_collected': 1500.0,
                  'primary_payment_method': 'cash',
                  'payment_status': 'full',
                }
              ],
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any)).thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.generateDailyReconciliation();

        // Assert
        expect(reconciliation.id, 'recon-1');
        expect(reconciliation.totalExpected, 5000.0);
        expect(reconciliation.totalCollected, 4800.0);
        expect(reconciliation.totalCash, 3000.0);
        expect(reconciliation.totalCliq, 1800.0);
        expect(reconciliation.status, ReconciliationStatus.pending);
        expect(reconciliation.shopBreakdown.length, 1);
        expect(reconciliation.shopBreakdown[0].shopName, 'Ahmad Shop');
      });

      test('calculates collection rate correctly', () async {
        // Arrange
        final mockResponse = Response(
          data: {
            'data': {
              'id': 'recon-1',
              'reconciliation_date': '2026-01-10',
              'total_expected': 10000.0,
              'total_collected': 6000.0,
              'total_cash': 6000.0,
              'total_cliq': 0.0,
              'trips_completed': 3,
              'deliveries_completed': 10,
              'total_km_driven': 30.0,
              'status': 'pending',
              'shop_breakdown': [],
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any)).thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.generateDailyReconciliation();

        // Assert
        expect(reconciliation.collectionRate, 60.0);
      });

      test('throws ReconciliationException on API error', () async {
        // Arrange
        when(mockApiClient.post(any)).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.generateDailyReconciliation(),
          throwsA(isA<ApiException>()),
        );
      });
    });

    group('getTodaysReconciliation', () {
      test('fetches todays reconciliation', () async {
        // Arrange
        final mockResponse = Response(
          data: {
            'data': {
              'id': 'recon-1',
              'reconciliation_date': '2026-01-10',
              'total_expected': 5000.0,
              'total_collected': 4800.0,
              'total_cash': 3000.0,
              'total_cliq': 1800.0,
              'trips_completed': 5,
              'deliveries_completed': 15,
              'total_km_driven': 45.5,
              'status': 'pending',
              'shop_breakdown': [],
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.getTodaysReconciliation();

        // Assert
        expect(reconciliation, isNotNull);
        expect(reconciliation!.id, 'recon-1');
        expect(reconciliation.totalExpected, 5000.0);
      });

      test('returns null when no reconciliation exists', () async {
        // Arrange
        final mockResponse = Response(
          data: {'data': null},
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.getTodaysReconciliation();

        // Assert
        expect(reconciliation, isNull);
      });

      test('handles nested data response', () async {
        // Arrange
        final mockResponse = Response(
          data: {
            'data': {
              'data': {
                'id': 'recon-1',
                'reconciliation_date': '2026-01-10',
                'total_expected': 5000.0,
                'total_collected': 4800.0,
                'total_cash': 3000.0,
                'total_cliq': 1800.0,
                'trips_completed': 5,
                'deliveries_completed': 15,
                'total_km_driven': 45.5,
                'status': 'pending',
                'shop_breakdown': [],
              }
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.getTodaysReconciliation();

        // Assert
        expect(reconciliation, isNotNull);
        expect(reconciliation!.id, 'recon-1');
      });

      test('throws ReconciliationException on API error', () async {
        // Arrange
        when(mockApiClient.get(any)).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.getTodaysReconciliation(),
          throwsA(isA<ApiException>()),
        );
      });
    });

    group('submitReconciliation', () {
      test('submits reconciliation to Melo ERP', () async {
        // Arrange
        const reconciliationId = 'recon-1';
        final mockResponse = Response(
          data: {
            'data': {
              'id': reconciliationId,
              'reconciliation_date': '2026-01-10',
              'total_expected': 5000.0,
              'total_collected': 4800.0,
              'total_cash': 3000.0,
              'total_cliq': 1800.0,
              'trips_completed': 5,
              'deliveries_completed': 15,
              'total_km_driven': 45.5,
              'status': 'submitted',
              'shop_breakdown': [],
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final reconciliation = await repository.submitReconciliation(
          reconciliationId,
        );

        // Assert
        expect(reconciliation.id, reconciliationId);
        expect(reconciliation.status, ReconciliationStatus.submitted);
      });

      test('includes notes in submission', () async {
        // Arrange
        const reconciliationId = 'recon-1';
        const notes = 'All collected successfully';
        final mockResponse = Response(
          data: {
            'data': {
              'id': reconciliationId,
              'reconciliation_date': '2026-01-10',
              'total_expected': 5000.0,
              'total_collected': 5000.0,
              'total_cash': 3000.0,
              'total_cliq': 2000.0,
              'trips_completed': 5,
              'deliveries_completed': 15,
              'total_km_driven': 45.5,
              'status': 'submitted',
              'shop_breakdown': [],
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        await repository.submitReconciliation(
          reconciliationId,
          notes: notes,
        );

        // Assert
        verify(mockApiClient.post(
          any,
          data: argThat(
            contains('notes'),
            named: 'data',
          ),
        )).called(1);
      });

      test('throws ReconciliationException on API error', () async {
        // Arrange
        when(mockApiClient.post(any, data: anyNamed('data'))).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.submitReconciliation('recon-1'),
          throwsA(isA<ApiException>()),
        );
      });
    });

    test('ReconciliationException format', () {
      // Arrange & Act
      final exception = ReconciliationException('Test error');

      // Assert
      expect(exception.toString(), 'ReconciliationException: Test error');
      expect(exception.message, 'Test error');
    });
  });
}
