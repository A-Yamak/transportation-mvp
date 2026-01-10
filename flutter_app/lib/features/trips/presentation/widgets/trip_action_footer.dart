import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/trip_model.dart';
import '../../data/models/trip_status.dart';

/// Persistent footer for trip actions
/// Shows start/end trip buttons and KM counter
class TripActionFooter extends ConsumerWidget {
  final TripModel trip;
  final String tripId;
  final VoidCallback? onTripStarted;
  final VoidCallback? onTripCompleted;

  const TripActionFooter({
    Key? key,
    required this.trip,
    required this.tripId,
    this.onTripStarted,
    this.onTripCompleted,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isNotStarted = trip.status == TripStatus.notStarted;
    final isInProgress = trip.status == TripStatus.inProgress;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(
          top: BorderSide(color: Colors.grey.shade300),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.1),
            blurRadius: 4,
            offset: Offset(0, -2),
          ),
        ],
      ),
      padding: EdgeInsets.all(16),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // KM display (always visible)
            Container(
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(Icons.place, size: 20, color: Colors.blue),
                      SizedBox(width: 8),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'KM Driven',
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                          Text(
                            '${(trip.actualKmDriven ?? trip.estimatedKm)?.toStringAsFixed(1) ?? '0.0'} km',
                            style:
                                Theme.of(context).textTheme.titleSmall?.copyWith(
                                      fontWeight: FontWeight.bold,
                                    ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  Container(
                    padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: isInProgress
                          ? Colors.orange.withOpacity(0.2)
                          : Colors.grey.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      trip.status.label,
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 12,
                        color: isInProgress ? Colors.orange : Colors.grey,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            SizedBox(height: 12),

            // Start trip button
            if (isNotStarted)
              ElevatedButton.icon(
                onPressed: () {
                  // Call startTrip from provider
                  // This would be handled by the parent widget
                  onTripStarted?.call();
                },
                icon: Icon(Icons.play_arrow),
                label: Text('Start Trip'),
              ),

            // In-progress actions
            if (isInProgress) ...[
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        // Complete trip
                        onTripCompleted?.call();
                      },
                      icon: Icon(Icons.check_circle),
                      label: Text('End Trip'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
              SizedBox(height: 8),
            ],

            // End day button (always visible)
            ElevatedButton.icon(
              onPressed: () {
                // Navigate to reconciliation screen
                context.go('/reconciliation');
              },
              icon: Icon(Icons.summarize),
              label: Text('End Day'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.blue,
                foregroundColor: Colors.white,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Compact footer version for use in lists or narrow spaces
class TripActionFooterCompact extends ConsumerWidget {
  final TripModel trip;
  final String tripId;
  final VoidCallback? onTripStarted;
  final VoidCallback? onTripCompleted;

  const TripActionFooterCompact({
    Key? key,
    required this.trip,
    required this.tripId,
    this.onTripStarted,
    this.onTripCompleted,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isNotStarted = trip.status == TripStatus.notStarted;
    final isInProgress = trip.status == TripStatus.inProgress;

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.grey.withOpacity(0.05),
        border: Border(
          top: BorderSide(color: Colors.grey.shade300),
        ),
      ),
      child: Row(
        children: [
          // KM display
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'KM: ${(trip.actualKmDriven ?? trip.estimatedKm)?.toStringAsFixed(1) ?? '0.0'}',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ],
            ),
          ),

          // Status and actions
          if (isNotStarted)
            TextButton.icon(
              onPressed: onTripStarted,
              icon: Icon(Icons.play_arrow, size: 18),
              label: Text('Start', style: TextStyle(fontSize: 12)),
            ),
          if (isInProgress)
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                TextButton.icon(
                  onPressed: onTripCompleted,
                  icon: Icon(Icons.check, size: 18),
                  label: Text('End', style: TextStyle(fontSize: 12)),
                ),
                SizedBox(width: 4),
                TextButton.icon(
                  onPressed: () {
                    context.go('/reconciliation');
                  },
                  icon: Icon(Icons.summarize, size: 18),
                  label: Text('Day', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
          if (!isNotStarted && !isInProgress)
            TextButton.icon(
              onPressed: () {
                context.go('/reconciliation');
              },
              icon: Icon(Icons.summarize, size: 18),
              label: Text('Day', style: TextStyle(fontSize: 12)),
            ),
        ],
      ),
    );
  }
}
