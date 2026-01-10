import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';
import 'package:dio/dio.dart';
import 'package:driver_app/core/api/api_client.dart';
import 'package:driver_app/core/api/api_exceptions.dart';
import 'package:driver_app/features/trips/data/trips_repository.dart';
import 'package:driver_app/features/trips/data/models/payment_method_enum.dart';
import 'package:driver_app/features/trips/data/models/payment_status_enum.dart';

import 'trips_repository_payment_test.mocks.dart';

@GenerateMocks([ApiClient])
void main() {
  late MockApiClient mockApiClient;
  late TripsRepository repository;

  setUp(() {
    mockApiClient = MockApiClient();
    repository = TripsRepository(mockApiClient);
  });

  group('TripsRepository Payment Methods', () {
    group('collectPayment', () {
      test('collects cash payment', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        const amount = 1000.0;

        final mockResponse = Response(
          data: {
            'data': {
              'id': 'payment-1',
              'destination_id': destinationId,
              'amount_expected': 1000.0,
              'amount_collected': amount,
              'payment_method': 'cash',
              'payment_status': 'full',
              'created_at': '2026-01-10T12:00:00Z',
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final payment = await repository.collectPayment(
          tripId,
          destinationId,
          amountCollected: amount,
          paymentMethod: 'cash',
        );

        // Assert
        expect(payment.id, 'payment-1');
        expect(payment.amountCollected, amount);
        expect(payment.paymentMethod, PaymentMethod.cash);
        expect(payment.paymentStatus, PaymentStatus.full);
      });

      test('collects cliq payment with reference', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        const amount = 1000.0;
        const cliqRef = 'REF-12345';

        final mockResponse = Response(
          data: {
            'data': {
              'id': 'payment-2',
              'destination_id': destinationId,
              'amount_expected': 1000.0,
              'amount_collected': amount,
              'payment_method': 'cliq_now',
              'payment_status': 'full',
              'cliq_reference': cliqRef,
              'created_at': '2026-01-10T12:00:00Z',
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final payment = await repository.collectPayment(
          tripId,
          destinationId,
          amountCollected: amount,
          paymentMethod: 'cliq_now',
          cliqReference: cliqRef,
        );

        // Assert
        expect(payment.paymentMethod, PaymentMethod.cliqNow);
        expect(payment.cliqReference, cliqRef);
      });

      test('collects partial payment with shortage reason', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        const expected = 1000.0;
        const collected = 750.0;
        const shortageReason = 'customer_refused';

        final mockResponse = Response(
          data: {
            'data': {
              'id': 'payment-3',
              'destination_id': destinationId,
              'amount_expected': expected,
              'amount_collected': collected,
              'payment_method': 'cash',
              'payment_status': 'partial',
              'shortage_amount': 250.0,
              'shortage_percentage': 25.0,
              'shortage_reason': shortageReason,
              'created_at': '2026-01-10T12:00:00Z',
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final payment = await repository.collectPayment(
          tripId,
          destinationId,
          amountCollected: collected,
          paymentMethod: 'cash',
          shortageReason: shortageReason,
        );

        // Assert
        expect(payment.paymentStatus, PaymentStatus.partial);
        expect(payment.hasShortage, true);
        expect(payment.shortageAmount, 250.0);
      });

      test('includes notes in request', () async {
        // Arrange
        const tripId = 'trip-1';
        const destinationId = 'dest-1';
        const notes = 'Customer requested delivery on credit';

        final mockResponse = Response(
          data: {
            'data': {
              'id': 'payment-4',
              'destination_id': destinationId,
              'amount_expected': 1000.0,
              'amount_collected': 0.0,
              'payment_method': 'cash',
              'payment_status': 'pending',
              'notes': notes,
            }
          },
          statusCode: 201,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        await repository.collectPayment(
          tripId,
          destinationId,
          amountCollected: 0.0,
          paymentMethod: 'cash',
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

      test('throws TripException on API error', () async {
        // Arrange
        when(mockApiClient.post(any, data: anyNamed('data'))).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.collectPayment(
            'trip-1',
            'dest-1',
            amountCollected: 1000.0,
            paymentMethod: 'cash',
          ),
          throwsA(isA<ApiException>()),
        );
      });
    });

    group('reorderDestinations', () {
      test('reorders trip destinations', () async {
        // Arrange
        const tripId = 'trip-1';
        final newOrder = ['dest-2', 'dest-1', 'dest-3'];

        final mockResponse = Response(
          data: {
            'data': {
              'id': tripId,
              'status': 'in_progress',
              'destinations': [
                {
                  'id': 'dest-2',
                  'sequence_order': 0,
                  'address': '456 Oak Ave',
                },
                {
                  'id': 'dest-1',
                  'sequence_order': 1,
                  'address': '123 Main St',
                },
                {
                  'id': 'dest-3',
                  'sequence_order': 2,
                  'address': '789 Pine St',
                },
              ]
            }
          },
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        final trip = await repository.reorderDestinations(tripId, newOrder);

        // Assert
        expect(trip.id, tripId);
        expect(trip.destinations.length, 3);
      });

      test('sends correct destination order', () async {
        // Arrange
        const tripId = 'trip-1';
        final newOrder = ['dest-3', 'dest-1', 'dest-2'];

        final mockResponse = Response(
          data: {'data': {'id': tripId}},
          statusCode: 200,
          requestOptions: RequestOptions(path: ''),
        );

        when(mockApiClient.post(any, data: anyNamed('data')))
            .thenAnswer((_) async => mockResponse);

        // Act
        await repository.reorderDestinations(tripId, newOrder);

        // Assert
        verify(mockApiClient.post(
          any,
          data: argThat(
            contains('destination_order'),
            named: 'data',
          ),
        )).called(1);
      });

      test('throws TripException on API error', () async {
        // Arrange
        when(mockApiClient.post(any, data: anyNamed('data'))).thenThrow(
          ApiException('API Error', statusCode: 500),
        );

        // Act & Assert
        expect(
          () => repository.reorderDestinations('trip-1', ['dest-1']),
          throwsA(isA<ApiException>()),
        );
      });
    });
  });
}
