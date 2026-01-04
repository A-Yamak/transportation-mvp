import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../shared/theme/app_theme.dart';

class TripDetailsScreen extends ConsumerWidget {
  final String tripId;

  const TripDetailsScreen({
    super.key,
    required this.tripId,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;

    // TODO: Fetch trip details from API
    final destinations = [
      _Destination(
        id: '1',
        name: 'مخبز الحلويات',
        address: 'شارع الملك عبدالله 123، عمان',
        externalId: 'ORD-5678',
        status: DestinationStatus.completed,
        sequenceOrder: 1,
        lat: 31.9539,
        lng: 35.9106,
      ),
      _Destination(
        id: '2',
        name: 'كوفي هاوس',
        address: 'شارع الرينبو 456، عمان',
        externalId: 'ORD-5679',
        status: DestinationStatus.arrived,
        sequenceOrder: 2,
        lat: 31.9600,
        lng: 35.9200,
      ),
      _Destination(
        id: '3',
        name: 'ميني ماركت',
        address: 'شارع الحدائق 789، عمان',
        externalId: 'ORD-5680',
        status: DestinationStatus.pending,
        sequenceOrder: 3,
        lat: 31.9450,
        lng: 35.9000,
      ),
    ];

    final completedCount = destinations.where((d) => d.status == DestinationStatus.completed).length;

    return Scaffold(
      appBar: AppBar(
        title: Text('${l10n.trip} #$tripId'),
      ),
      body: Column(
        children: [
          // Progress header
          Container(
            padding: const EdgeInsets.all(16),
            color: AppTheme.primaryColor.withOpacity(0.1),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'مصنع الحلويات',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Text(
                      '${destinations.length} ${l10n.destinations} • ~32.5 ${l10n.km}',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                // Progress bar
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: completedCount / destinations.length,
                    backgroundColor: Colors.grey[300],
                    minHeight: 8,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$completedCount/${destinations.length}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),

          // Destinations list
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: destinations.length,
              itemBuilder: (context, index) {
                final dest = destinations[index];
                return _DestinationCard(
                  destination: dest,
                  l10n: l10n,
                  onNavigate: () => _openNavigation(dest.lat, dest.lng),
                  onMarkArrived: () {
                    // TODO: Mark arrived
                  },
                  onMarkComplete: () {
                    // TODO: Mark complete
                  },
                  onMarkFailed: () {
                    // TODO: Show failure dialog
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openNavigation(double lat, double lng) async {
    final url = Uri.parse(
      'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving',
    );
    if (await canLaunchUrl(url)) {
      await launchUrl(url, mode: LaunchMode.externalApplication);
    }
  }
}

enum DestinationStatus { pending, arrived, completed, failed }

class _Destination {
  final String id;
  final String name;
  final String address;
  final String externalId;
  final DestinationStatus status;
  final int sequenceOrder;
  final double lat;
  final double lng;

  _Destination({
    required this.id,
    required this.name,
    required this.address,
    required this.externalId,
    required this.status,
    required this.sequenceOrder,
    required this.lat,
    required this.lng,
  });
}

class _DestinationCard extends StatelessWidget {
  final _Destination destination;
  final AppLocalizations l10n;
  final VoidCallback onNavigate;
  final VoidCallback onMarkArrived;
  final VoidCallback onMarkComplete;
  final VoidCallback onMarkFailed;

  const _DestinationCard({
    required this.destination,
    required this.l10n,
    required this.onNavigate,
    required this.onMarkArrived,
    required this.onMarkComplete,
    required this.onMarkFailed,
  });

  @override
  Widget build(BuildContext context) {
    final statusIcon = switch (destination.status) {
      DestinationStatus.completed => Icons.check_circle,
      DestinationStatus.arrived => Icons.location_on,
      DestinationStatus.failed => Icons.cancel,
      DestinationStatus.pending => Icons.radio_button_unchecked,
    };

    final statusColor = switch (destination.status) {
      DestinationStatus.completed => StatusColors.completed,
      DestinationStatus.arrived => StatusColors.arrived,
      DestinationStatus.failed => StatusColors.failed,
      DestinationStatus.pending => StatusColors.pending,
    };

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Icon(statusIcon, color: statusColor),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '${destination.sequenceOrder}. ${destination.name}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),

            // Address
            Text(
              destination.address,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 4),

            // External ID
            Text(
              '${l10n.trip} #${destination.externalId}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Colors.grey[500],
              ),
            ),

            // Actions
            if (destination.status != DestinationStatus.completed &&
                destination.status != DestinationStatus.failed) ...[
              const SizedBox(height: 16),
              const Divider(height: 1),
              const SizedBox(height: 12),
              Row(
                children: [
                  if (destination.status == DestinationStatus.pending) ...[
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: onNavigate,
                        icon: const Icon(Icons.navigation),
                        label: Text(l10n.navigate),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ElevatedButton.icon(
                        onPressed: onMarkArrived,
                        icon: const Icon(Icons.check),
                        label: Text(l10n.markArrived),
                      ),
                    ),
                  ] else if (destination.status == DestinationStatus.arrived) ...[
                    Expanded(
                      child: ElevatedButton(
                        onPressed: onMarkComplete,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: StatusColors.completed,
                        ),
                        child: Text(l10n.markComplete),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: onMarkFailed,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: StatusColors.failed,
                        ),
                        child: Text(l10n.markFailed),
                      ),
                    ),
                  ],
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
