import 'package:flutter_test/flutter_test.dart';

import 'package:transportation_app/features/notifications/data/models/notification_model.dart';

void main() {
  group('NotificationModel', () {
    test('can be created from JSON', () {
      final json = {
        'id': 'notif-123',
        'type': 'trip_assigned',
        'title': 'Trip Assigned',
        'body': 'New trip with 5 deliveries',
        'data': {
          'trip_id': 'trip-456',
          'destinations_count': 5,
          'total_km': 25.5,
        },
        'status': 'sent',
        'created_at': '2026-01-09T10:00:00Z',
        'sent_at': '2026-01-09T10:01:00Z',
        'read_at': null,
      };

      final notification = NotificationModel.fromJson(json);

      expect(notification.id, 'notif-123');
      expect(notification.type, 'trip_assigned');
      expect(notification.title, 'Trip Assigned');
      expect(notification.isUnread, true);
      expect(notification.isSent, true);
    });

    test('isRead returns true when read_at is not null', () {
      final notification = NotificationModel(
        id: 'notif-123',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {},
        status: 'sent',
        createdAt: DateTime.now(),
        readAt: DateTime.now(),
      );

      expect(notification.isRead, true);
      expect(notification.isUnread, false);
    });

    test('isSent returns true only when status is sent', () {
      final sentNotification = NotificationModel(
        id: 'notif-123',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {},
        status: 'sent',
        createdAt: DateTime.now(),
      );

      final pendingNotification = NotificationModel(
        id: 'notif-456',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {},
        status: 'pending',
        createdAt: DateTime.now(),
      );

      expect(sentNotification.isSent, true);
      expect(sentNotification.isPending, false);
      expect(pendingNotification.isPending, true);
      expect(pendingNotification.isSent, false);
    });

    test('typeLabel returns correct label for each type', () {
      expect(
        NotificationModel(
          id: '1',
          type: 'trip_assigned',
          title: 'Title',
          body: 'Body',
          data: {},
          status: 'sent',
          createdAt: DateTime.now(),
        ).typeLabel,
        'Trip Assigned',
      );

      expect(
        NotificationModel(
          id: '2',
          type: 'payment_received',
          title: 'Title',
          body: 'Body',
          data: {},
          status: 'sent',
          createdAt: DateTime.now(),
        ).typeLabel,
        'Payment Received',
      );

      expect(
        NotificationModel(
          id: '3',
          type: 'action_required',
          title: 'Title',
          body: 'Body',
          data: {},
          status: 'sent',
          createdAt: DateTime.now(),
        ).typeLabel,
        'Action Required',
      );
    });

    test('tripId extracts trip ID from data', () {
      final notification = NotificationModel(
        id: 'notif-123',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {'trip_id': 'trip-456', 'other': 'data'},
        status: 'sent',
        createdAt: DateTime.now(),
      );

      expect(notification.tripId, 'trip-456');
    });

    test('amount extracts and converts amount to double', () {
      final notification = NotificationModel(
        id: 'notif-123',
        type: 'payment_received',
        title: 'Payment',
        body: 'Received',
        data: {'amount': 150.75},
        status: 'sent',
        createdAt: DateTime.now(),
      );

      expect(notification.amount, 150.75);
    });

    test('amount handles integer conversion', () {
      final notification = NotificationModel(
        id: 'notif-123',
        type: 'payment_received',
        title: 'Payment',
        body: 'Received',
        data: {'amount': 100},
        status: 'sent',
        createdAt: DateTime.now(),
      );

      expect(notification.amount, 100.0);
    });

    test('timeAgo calculates correct time elapsed', () {
      final now = DateTime.now();
      final pastMinute = now.subtract(Duration(minutes: 5));
      final pastHour = now.subtract(Duration(hours: 2));
      final pastDay = now.subtract(Duration(days: 1));

      final notif1 = NotificationModel(
        id: '1',
        type: 'trip_assigned',
        title: 'Title',
        body: 'Body',
        data: {},
        status: 'sent',
        createdAt: pastMinute,
      );

      final notif2 = NotificationModel(
        id: '2',
        type: 'trip_assigned',
        title: 'Title',
        body: 'Body',
        data: {},
        status: 'sent',
        createdAt: pastHour,
      );

      final notif3 = NotificationModel(
        id: '3',
        type: 'trip_assigned',
        title: 'Title',
        body: 'Body',
        data: {},
        status: 'sent',
        createdAt: pastDay,
      );

      expect(notif1.timeAgo, '5m ago');
      expect(notif2.timeAgo, '2h ago');
      expect(notif3.timeAgo, '1d ago');
    });

    test('isFailed returns true only when status is failed', () {
      final failedNotification = NotificationModel(
        id: 'notif-123',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {},
        status: 'failed',
        createdAt: DateTime.now(),
      );

      expect(failedNotification.isFailed, true);
      expect(failedNotification.isSent, false);
    });

    test('can be converted to JSON', () {
      final notification = NotificationModel(
        id: 'notif-123',
        type: 'trip_assigned',
        title: 'Trip Assigned',
        body: 'New trip',
        data: {'trip_id': 'trip-456'},
        status: 'sent',
        createdAt: DateTime(2026, 1, 9, 10, 0, 0),
      );

      final json = notification.toJson();

      expect(json['id'], 'notif-123');
      expect(json['type'], 'trip_assigned');
      expect(json['data']['trip_id'], 'trip-456');
    });
  });
}
