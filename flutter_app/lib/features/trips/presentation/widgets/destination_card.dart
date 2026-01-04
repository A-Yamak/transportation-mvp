import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/destination_model.dart';
import '../../data/models/destination_status.dart';
import '../../providers/trip_actions_provider.dart';

class DestinationCard extends ConsumerWidget {
  final DestinationModel destination;
  final String tripId;

  const DestinationCard({
    Key? key,
    required this.destination,
    required this.tripId,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  child: Text('${destination.sequenceOrder}'),
                  backgroundColor: destination.status.color,
                  foregroundColor: Colors.white,
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        destination.address,
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                      SizedBox(height: 4),
                      Text(
                        destination.status.label,
                        style: TextStyle(
                          color: destination.status.color,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            SizedBox(height: 12),
            _buildActions(context, ref),
          ],
        ),
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref) {
    if (destination.status == DestinationStatus.completed) {
      return Row(
        children: [
          Icon(Icons.check_circle, color: Colors.green, size: 16),
          SizedBox(width: 4),
          Text('Completed', style: TextStyle(color: Colors.green)),
        ],
      );
    }

    if (destination.status == DestinationStatus.failed) {
      return Row(
        children: [
          Icon(Icons.error, color: Colors.red, size: 16),
          SizedBox(width: 4),
          Text('Failed', style: TextStyle(color: Colors.red)),
        ],
      );
    }

    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        // Navigate button - always available for pending and arrived
        ElevatedButton.icon(
          onPressed: () {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Navigate to: ${destination.address}'),
                action: SnackBarAction(
                  label: 'URL',
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text(destination.navigationUrl)),
                    );
                  },
                ),
              ),
            );
          },
          icon: Icon(Icons.navigation, size: 16),
          label: Text('Navigate'),
          style: ElevatedButton.styleFrom(
            backgroundColor: Colors.blue,
            foregroundColor: Colors.white,
          ),
        ),

        // Mark Arrived - only if pending
        if (destination.status == DestinationStatus.pending)
          OutlinedButton.icon(
            onPressed: () async {
              await ref
                  .read(tripActionsProvider)
                  .markArrived(tripId, destination.id);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Marked as arrived')),
              );
            },
            icon: Icon(Icons.location_on, size: 16),
            label: Text('Arrived'),
          ),

        // Mark Complete - only if arrived
        if (destination.status == DestinationStatus.arrived)
          ElevatedButton.icon(
            onPressed: () async {
              await ref
                  .read(tripActionsProvider)
                  .markCompleted(tripId, destination.id);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Delivery completed!')),
              );
            },
            icon: Icon(Icons.check, size: 16),
            label: Text('Complete'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
          ),
      ],
    );
  }
}
