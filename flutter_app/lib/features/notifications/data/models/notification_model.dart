import 'package:freezed_annotation/freezed_annotation.dart';

part 'notification_model.freezed.dart';
part 'notification_model.g.dart';

@freezed
class NotificationModel with _$NotificationModel {
  const factory NotificationModel({
    required String id,
    required String type, // 'trip_assigned', 'trip_reassigned', 'payment_received', 'action_required'
    required String title,
    required String body,
    required Map<String, dynamic> data,
    required String status, // 'pending', 'sent', 'failed'
    required DateTime createdAt,
    DateTime? sentAt,
    DateTime? readAt,
  }) = _NotificationModel;

  factory NotificationModel.fromJson(Map<String, dynamic> json) =>
      _$NotificationModelFromJson(json);
}

extension NotificationModelX on NotificationModel {
  /// Check if notification is read
  bool get isRead => readAt != null;

  /// Check if notification is unread
  bool get isUnread => readAt == null;

  /// Check if notification is sent
  bool get isSent => status == 'sent';

  /// Check if notification is pending
  bool get isPending => status == 'pending';

  /// Check if notification failed
  bool get isFailed => status == 'failed';

  /// Get readable type label
  String get typeLabel {
    switch (type) {
      case 'trip_assigned':
        return 'Trip Assigned';
      case 'trip_reassigned':
        return 'Trip Reassigned';
      case 'payment_received':
        return 'Payment Received';
      case 'action_required':
        return 'Action Required';
      default:
        return 'Notification';
    }
  }

  /// Get icon for notification type
  String get typeIcon {
    switch (type) {
      case 'trip_assigned':
      case 'trip_reassigned':
        return 'heroicon-o-truck';
      case 'payment_received':
        return 'heroicon-o-currency-dollar';
      case 'action_required':
        return 'heroicon-o-exclamation-circle';
      default:
        return 'heroicon-o-bell';
    }
  }

  /// Get trip ID from data if available
  String? get tripId => data['trip_id'] as String?;

  /// Get action type from data if available
  String? get action => data['action'] as String?;

  /// Get amount from data if payment notification
  double? get amount {
    final val = data['amount'];
    if (val is double) return val;
    if (val is int) return val.toDouble();
    if (val is String) return double.tryParse(val);
    return null;
  }

  /// Time elapsed since creation
  String get timeAgo {
    final now = DateTime.now();
    final diff = now.difference(createdAt);

    if (diff.inMinutes < 1) {
      return 'Just now';
    } else if (diff.inMinutes < 60) {
      return '${diff.inMinutes}m ago';
    } else if (diff.inHours < 24) {
      return '${diff.inHours}h ago';
    } else if (diff.inDays < 7) {
      return '${diff.inDays}d ago';
    } else {
      return createdAt.toString().split(' ')[0];
    }
  }
}
