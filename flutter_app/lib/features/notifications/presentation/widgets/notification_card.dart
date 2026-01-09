import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../trips/presentation/screens/trip_details_screen.dart';
import '../../data/models/notification_model.dart';
import '../../providers/notifications_provider.dart';

class NotificationCard extends ConsumerWidget {
  final NotificationModel notification;
  final VoidCallback? onTap;
  final VoidCallback? onDismiss;

  const NotificationCard({
    required this.notification,
    this.onTap,
    this.onDismiss,
    Key? key,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: GestureDetector(
        onTap: _handleTap(context, ref),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(8),
            color: notification.isUnread
                ? Colors.blue.withOpacity(0.05)
                : Colors.grey.withOpacity(0.02),
            border: Border(
              left: BorderSide(
                color: _getColorForType(),
                width: 4,
              ),
            ),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header with title and time
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            // Type icon
                            _getIconForType(),
                            const SizedBox(width: 8),
                            // Title
                            Expanded(
                              child: Text(
                                notification.title,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(
                                      fontWeight: notification.isUnread
                                          ? FontWeight.bold
                                          : FontWeight.normal,
                                    ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            // Unread indicator
                            if (notification.isUnread)
                              Container(
                                width: 8,
                                height: 8,
                                decoration: const BoxDecoration(
                                  shape: BoxShape.circle,
                                  color: Colors.blue,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        // Time
                        Text(
                          notification.timeAgo,
                          style:
                              Theme.of(context).textTheme.labelSmall?.copyWith(
                                    color: Colors.grey[600],
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Close button
                  SizedBox(
                    width: 32,
                    height: 32,
                    child: IconButton(
                      padding: EdgeInsets.zero,
                      icon: const Icon(Icons.close, size: 18),
                      onPressed: () => _handleDismiss(ref),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              // Body
              Text(
                notification.body,
                style: Theme.of(context).textTheme.bodySmall,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              // Action buttons if available
              if (notification.action != null) ...[
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: _handleTap(context, ref),
                        icon: const Icon(Icons.arrow_forward, size: 16),
                        label: Text(_getActionLabel()),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 8,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Color _getColorForType() {
    switch (notification.type) {
      case 'trip_assigned':
      case 'trip_reassigned':
        return Colors.orange;
      case 'payment_received':
        return Colors.green;
      case 'action_required':
        return Colors.red;
      default:
        return Colors.blue;
    }
  }

  Widget _getIconForType() {
    final color = _getColorForType();
    const size = 20.0;

    switch (notification.type) {
      case 'trip_assigned':
      case 'trip_reassigned':
        return Icon(Icons.local_shipping, color: color, size: size);
      case 'payment_received':
        return Icon(Icons.attach_money, color: color, size: size);
      case 'action_required':
        return Icon(Icons.warning_rounded, color: color, size: size);
      default:
        return Icon(Icons.notifications, color: color, size: size);
    }
  }

  String _getActionLabel() {
    switch (notification.action) {
      case 'open_trip':
        return 'View Trip';
      case 'open_earnings':
        return 'View Earnings';
      case 'view_details':
        return 'View Details';
      default:
        return 'Open';
    }
  }

  VoidCallback? _handleTap(BuildContext context, WidgetRef ref) {
    return () async {
      // Mark as read
      await ref
          .read(notificationActionsProvider.notifier)
          .markAsRead(notification.id, ref);

      // Navigate based on action
      switch (notification.action) {
        case 'open_trip':
          if (notification.tripId != null) {
            if (context.mounted) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) =>
                      TripDetailsScreen(tripId: notification.tripId!),
                ),
              );
            }
          }
          break;
        case 'open_earnings':
          // Navigate to earnings/profile screen
          if (context.mounted) {
            Navigator.pushNamed(context, '/profile/earnings');
          }
          break;
        default:
          onTap?.call();
      }
    };
  }

  void _handleDismiss(WidgetRef ref) {
    ref
        .read(notificationActionsProvider.notifier)
        .deleteNotification(notification.id, ref);
    onDismiss?.call();
  }
}
