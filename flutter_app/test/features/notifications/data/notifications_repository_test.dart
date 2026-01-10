import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/annotations.dart';
import 'package:mockito/mockito.dart';

import 'package:driver_app/core/api/api_client.dart';
import 'package:driver_app/features/notifications/data/notifications_repository.dart';

import 'notifications_repository_test.mocks.dart';

@GenerateMocks([ApiClient])
void main() {
  late NotificationsRepository repository;
  late MockApiClient mockApiClient;

  setUp(() {
    mockApiClient = MockApiClient();
    repository = NotificationsRepository(mockApiClient);
  });

  group('NotificationsRepository', () {
    group('getNotifications', () {
      test('returns list of notifications on success', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': [
              {
                'id': 'notif-1',
                'type': 'trip_assigned',
                'title': 'Trip Assigned',
                'body': 'New trip',
                'data': {'trip_id': 'trip-1'},
                'status': 'sent',
                'created_at': '2026-01-09T10:00:00Z',
              },
            ],
          },
        );

        when(mockApiClient.get(
          '/driver/notifications',
          queryParameters: any,
        )).thenAnswer((_) async => mockResponse);

        final notifications = await repository.getNotifications();

        expect(notifications, isNotEmpty);
        expect(notifications.first.id, 'notif-1');
        expect(notifications.first.type, 'trip_assigned');
      });

      test('passes correct query parameters', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {'data': []},
        );

        when(mockApiClient.get(
          '/driver/notifications',
          queryParameters: any,
        )).thenAnswer((_) async => mockResponse);

        await repository.getNotifications(page: 2, perPage: 10);

        verify(mockApiClient.get(
          '/driver/notifications',
          queryParameters: {'page': 2, 'per_page': 10},
        )).called(1);
      });

      test('throws error on API failure', () async {
        final dioException = DioException(
          requestOptions: RequestOptions(path: ''),
          error: 'Network error',
        );

        when(mockApiClient.get(
          '/driver/notifications',
          queryParameters: any,
        )).thenThrow(dioException);

        expect(
          () => repository.getNotifications(),
          throwsException,
        );
      });
    });

    group('getUnreadCount', () {
      test('returns unread count on success', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': {'unread_count': 5},
          },
        );

        when(mockApiClient.get('/driver/notifications/unread-count'))
            .thenAnswer((_) async => mockResponse);

        final count = await repository.getUnreadCount();

        expect(count, 5);
      });

      test('returns 0 when no unread notifications', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': {'unread_count': 0},
          },
        );

        when(mockApiClient.get('/driver/notifications/unread-count'))
            .thenAnswer((_) async => mockResponse);

        final count = await repository.getUnreadCount();

        expect(count, 0);
      });
    });

    group('getUnreadNotifications', () {
      test('returns only unread notifications', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': [
              {
                'id': 'notif-1',
                'type': 'trip_assigned',
                'title': 'Trip',
                'body': 'New trip',
                'data': {},
                'status': 'sent',
                'created_at': '2026-01-09T10:00:00Z',
                'read_at': null,
              },
            ],
          },
        );

        when(mockApiClient.get('/driver/notifications/unread'))
            .thenAnswer((_) async => mockResponse);

        final notifications = await repository.getUnreadNotifications();

        expect(notifications, isNotEmpty);
        expect(notifications.first.isUnread, true);
      });
    });

    group('markAsRead', () {
      test('marks notification as read and returns updated notification', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': {
              'id': 'notif-1',
              'type': 'trip_assigned',
              'title': 'Trip',
              'body': 'New trip',
              'data': {},
              'status': 'sent',
              'created_at': '2026-01-09T10:00:00Z',
              'read_at': '2026-01-09T10:05:00Z',
            },
          },
        );

        when(mockApiClient.patch('/driver/notifications/notif-1/read'))
            .thenAnswer((_) async => mockResponse);

        final notification = await repository.markAsRead('notif-1');

        expect(notification.isRead, true);
        verify(mockApiClient.patch('/driver/notifications/notif-1/read'))
            .called(1);
      });
    });

    group('markAsUnread', () {
      test('marks notification as unread', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {
            'data': {
              'id': 'notif-1',
              'type': 'trip_assigned',
              'title': 'Trip',
              'body': 'New trip',
              'data': {},
              'status': 'sent',
              'created_at': '2026-01-09T10:00:00Z',
              'read_at': null,
            },
          },
        );

        when(mockApiClient.patch('/driver/notifications/notif-1/unread'))
            .thenAnswer((_) async => mockResponse);

        final notification = await repository.markAsUnread('notif-1');

        expect(notification.isUnread, true);
      });
    });

    group('markAllAsRead', () {
      test('calls correct endpoint', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {'message': 'All marked as read'},
        );

        when(mockApiClient.patch('/driver/notifications/mark-all-read'))
            .thenAnswer((_) async => mockResponse);

        await repository.markAllAsRead();

        verify(mockApiClient.patch('/driver/notifications/mark-all-read'))
            .called(1);
      });
    });

    group('deleteNotification', () {
      test('deletes notification', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {'message': 'Deleted'},
        );

        when(mockApiClient.delete('/driver/notifications/notif-1'))
            .thenAnswer((_) async => mockResponse);

        await repository.deleteNotification('notif-1');

        verify(mockApiClient.delete('/driver/notifications/notif-1')).called(1);
      });
    });

    group('registerFcmToken', () {
      test('sends FCM token to backend', () async {
        final mockResponse = Response(
          requestOptions: RequestOptions(path: ''),
          data: {'message': 'Registered'},
        );

        when(mockApiClient.post(
          '/driver/notifications/register-token',
          data: any,
        )).thenAnswer((_) async => mockResponse);

        await repository.registerFcmToken('test-token-123');

        verify(mockApiClient.post(
          '/driver/notifications/register-token',
          data: {'fcm_token': 'test-token-123'},
        )).called(1);
      });

      test('throws error on 401 authorization failure', () async {
        final dioException = DioException(
          requestOptions: RequestOptions(path: ''),
          response: Response(
            requestOptions: RequestOptions(path: ''),
            statusCode: 401,
          ),
        );

        when(mockApiClient.post(
          '/driver/notifications/register-token',
          data: any,
        )).thenThrow(dioException);

        expect(
          () => repository.registerFcmToken('token'),
          throwsException,
        );
      });
    });

    group('error handling', () {
      test('returns appropriate error message on network failure', () async {
        final dioException = DioException(
          requestOptions: RequestOptions(path: ''),
          message: 'Connection timeout',
        );

        when(mockApiClient.get('/driver/notifications'))
            .thenThrow(dioException);

        expect(
          () => repository.getNotifications(),
          throwsException,
        );
      });
    });
  });
}
