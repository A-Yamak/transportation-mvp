import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:dio/dio.dart';
import 'package:driver_app/core/api/api_client.dart';
import 'package:driver_app/core/api/api_exceptions.dart';
import 'package:driver_app/features/trips/data/tupperware_repository.dart';

import 'tupperware_repository_test.mocks.dart';

@GenerateMocks([ApiClient])
void main() {
  late MockApiClient mockApiClient;
  late TupperwareRepository repository;

  setUp(() {
    mockApiClient = MockApiClient();
    repository = TupperwareRepository(mockApiClient);
  });

  group('TupperwareRepository Tests', () {
    group('getShopBalance', () {
      test('fetches tupperware balance for shop', () async {
        // Arrange
        const shopId = 'SHOP-001';
        final mockResponse = Response(
          data: {
            'data': [
              {
                'product_type': 'boxes',
                'current_balance': 25,
                'threshold_warning': 30,
                'threshold_critical': 50,
                'deposit_per_unit': 5.0,
              },
              {
                'product_type': 'trays',
                'current_balance': 15,
                'threshold_warning': 20,
                'threshold_critical': 40,
                'deposit_per_unit': 3.0,
              },
            ]
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final balances = await repository.getShopBalance(shopId);

        // Assert
        expect(balances.length, 2);
        expect(balances[0].productType, 'boxes');
        expect(balances[0].currentBalance, 25);
        expect(balances[1].productType, 'trays');
        expect(balances[1].currentBalance, 15);
      });

      test('handles nested paginated response', () async {
        // Arrange
        const shopId = 'SHOP-001';
        final mockResponse = Response(
          data: {
            'data': {
              'data': [
                {
                  'product_type': 'boxes',
                  'current_balance': 20,
                  'threshold_warning': 30,
                  'threshold_critical': 50,
                  'deposit_per_unit': 5.0,
                }
              ]
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final balances = await repository.getShopBalance(shopId);

        // Assert
        expect(balances.length, 1);
        expect(balances[0].productType, 'boxes');
      });

      test('returns empty list on error', () async {
        // Arrange
        const shopId = 'SHOP-001';
        final mockResponse = Response(
          data: {'data': null},
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.get(any)).thenAnswer((_) async => mockResponse);

        // Act
        final balances = await repository.getShopBalance(shopId);

        // Assert
        expect(balances.length, 0);
      });

      test('throws TupperwareException on API error', () async {
        // Arrange
        when(mockApiClient.get(any)).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.getShopBalance('SHOP-001'),
          throwsA(isA<ApiException>()),
        );
      });
    });

    group('collectTupperware', () {
      test('collects tupperware at destination', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        final tupperware = [
          {'product_type': 'boxes', 'quantity': 10},
          {'product_type': 'trays', 'quantity': 5},
        ];

        final mockResponse = Response(
          data: {
            'data': [
              {
                'id': 'move-1',
                'destination_id': destinationId,
                'shop_id': 'SHOP-001',
                'product_type': 'boxes',
                'quantity_pickedup': 10,
              },
              {
                'id': 'move-2',
                'destination_id': destinationId,
                'shop_id': 'SHOP-001',
                'product_type': 'trays',
                'quantity_pickedup': 5,
              },
            ]
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final movements = await repository.collectTupperware(
          tripId,
          destinationId,
          tupperware: tupperware,
        );

        // Assert
        expect(movements.length, 2);
        expect(movements[0].productType, 'boxes');
        expect(movements[0].quantityPickedup, 10);
        expect(movements[1].productType, 'trays');
        expect(movements[1].quantityPickedup, 5);
      });

      test('includes notes in request', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        const notes = 'Boxes damaged';
        final tupperware = [{'product_type': 'boxes', 'quantity': 10}];

        final mockResponse = Response(
          data: {'data': []},
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        await repository.collectTupperware(
          tripId,
          destinationId,
          tupperware: tupperware,
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

      test('throws TupperwareException on API error', () async {
        // Arrange
        when(mockApiClient.post(any, data: anyNamed('data'))).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.collectTupperware(
            'trip-1',
            'dest-1',
            tupperware: [],
          ),
          throwsA(isA<ApiException>()),
        );
      });
    });

    test('TupperwareException format', () {
      // Arrange & Act
      final exception = TupperwareException('Test error');

      // Assert
      expect(exception.toString(), 'TupperwareException: Test error');
      expect(exception.message, 'Test error');
    });
  });
}
