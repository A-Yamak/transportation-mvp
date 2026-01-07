import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/destination_model.dart';
import '../../data/models/destination_status.dart';
import '../../providers/trip_actions_provider.dart';
import 'delivery_completion_dialog.dart';

/// Failure reasons matching backend enum
enum FailureReason {
  notHome('not_home', 'Customer Not Home', 'العميل غير موجود'),
  refused('refused', 'Refused Delivery', 'رفض الاستلام'),
  wrongAddress('wrong_address', 'Wrong Address', 'عنوان خاطئ'),
  inaccessible('inaccessible', 'Location Inaccessible', 'موقع غير قابل للوصول'),
  other('other', 'Other', 'سبب آخر');

  final String apiValue;
  final String labelEn;
  final String labelAr;

  const FailureReason(this.apiValue, this.labelEn, this.labelAr);
}

class DestinationCard extends ConsumerWidget {
  final DestinationModel destination;
  final String tripId;

  const DestinationCard({
    super.key,
    required this.destination,
    required this.tripId,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: destination.status.color,
                  foregroundColor: Colors.white,
                  child: Text('${destination.sequenceOrder}'),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        destination.address,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 4),
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
            const SizedBox(height: 12),
            _buildActions(context, ref),
          ],
        ),
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref) {
    if (destination.status == DestinationStatus.completed) {
      return const Row(
        children: [
          Icon(Icons.check_circle, color: Colors.green, size: 16),
          SizedBox(width: 4),
          Text('Completed', style: TextStyle(color: Colors.green)),
        ],
      );
    }

    if (destination.status == DestinationStatus.failed) {
      return const Row(
        children: [
          Icon(Icons.error, color: Colors.red, size: 16),
          SizedBox(width: 4),
          Text('Skipped', style: TextStyle(color: Colors.red)),
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
          icon: const Icon(Icons.navigation, size: 16),
          label: const Text('Navigate'),
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
              if (context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Marked as arrived')),
                );
              }
            },
            icon: const Icon(Icons.location_on, size: 16),
            label: const Text('Arrived'),
          ),

        // Mark Complete - only if arrived
        if (destination.status == DestinationStatus.arrived)
          ElevatedButton.icon(
            onPressed: () => _showCompletionDialog(context, ref),
            icon: const Icon(Icons.check, size: 16),
            label: const Text('Complete'),
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.green,
              foregroundColor: Colors.white,
            ),
          ),

        // Skip button - for pending or arrived destinations
        OutlinedButton.icon(
          onPressed: () => _showSkipDialog(context, ref),
          icon: const Icon(Icons.skip_next, size: 16),
          label: const Text('Skip'),
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.orange,
            side: const BorderSide(color: Colors.orange),
          ),
        ),
      ],
    );
  }

  /// Show completion dialog for item-level delivery
  void _showCompletionDialog(BuildContext context, WidgetRef ref) {
    DeliveryCompletionDialog.show(
      context: context,
      destination: destination,
      onComplete: (result) async {
        try {
          await ref.read(tripActionsProvider).markCompleted(
                tripId,
                destination.id,
                recipientName: result.recipientName,
                notes: result.notes,
                items: result.items.isNotEmpty ? result.items : null,
              );
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Delivery completed!')),
            );
          }
        } catch (e) {
          if (context.mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Error: $e'),
                backgroundColor: Colors.red,
              ),
            );
          }
        }
      },
    );
  }

  /// Show dialog to select skip reason
  void _showSkipDialog(BuildContext context, WidgetRef ref) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Skip Delivery'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Select reason:'),
            const SizedBox(height: 12),
            ...FailureReason.values.map((reason) => ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.arrow_right),
                  title: Text(reason.labelEn),
                  subtitle: Text(reason.labelAr, style: const TextStyle(fontSize: 12)),
                  onTap: () async {
                    Navigator.of(dialogContext).pop();
                    await _skipDestination(context, ref, reason);
                  },
                )),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('Cancel'),
          ),
        ],
      ),
    );
  }

  /// Skip destination with selected reason
  Future<void> _skipDestination(
    BuildContext context,
    WidgetRef ref,
    FailureReason reason,
  ) async {
    try {
      await ref.read(tripActionsProvider).markFailed(
            tripId,
            destination.id,
            reason: reason.apiValue,
          );
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Skipped: ${reason.labelEn}')),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }
}
