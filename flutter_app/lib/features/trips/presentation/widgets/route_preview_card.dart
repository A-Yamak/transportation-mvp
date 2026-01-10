import 'package:flutter/material.dart';
import '../../data/models/trip_model.dart';

/// Widget to display route preview with destination markers
/// Shows route summary without needing embedded maps
class RoutePreviewCard extends StatelessWidget {
  final TripModel trip;

  const RoutePreviewCard({
    Key? key,
    required this.trip,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    // If no polyline, show simplified route list
    if (trip.polyline == null || trip.polyline!.isEmpty) {
      return _buildSimpleRouteList();
    }

    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.route, color: Colors.blue, size: 20),
                const SizedBox(width: 8),
                Text(
                  'Route Overview',
                  style: Theme.of(context).textTheme.titleSmall,
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Route preview area with destination count
            Container(
              width: double.infinity,
              height: 100,
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                border: Border.all(color: Colors.grey.shade300),
                borderRadius: BorderRadius.circular(8),
              ),
              child: _buildRoutePreview(),
            ),

            const SizedBox(height: 12),

            // Destination list
            ...trip.destinations.map((dest) {
              final index = trip.destinations.indexOf(dest) + 1;
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Row(
                  children: [
                    // Sequence marker
                    Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: _getMarkerColor(index),
                      ),
                      child: Center(
                        child: Text(
                          '$index',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Address
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            dest.address,
                            style: const TextStyle(fontSize: 12),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          Text(
                            dest.status.label,
                            style: TextStyle(
                              fontSize: 10,
                              color: dest.status.color,
                            ),
                          ),
                        ],
                      ),
                    ),
                    // Status icon
                    Icon(
                      _getStatusIcon(dest.status.label),
                      color: dest.status.color,
                      size: 16,
                    ),
                  ],
                ),
              );
            }),
          ],
        ),
      ),
    );
  }

  /// Build a simple route visualization without polyline
  Widget _buildRoutePreview() {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.location_on, color: Colors.red, size: 20),
              const SizedBox(width: 4),
              Text(
                'Start',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
              ),
              const SizedBox(width: 12),
              // Connection dots
              ...List.generate(
                (trip.destinations.length - 1).clamp(0, 3),
                (i) => Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Container(
                    width: 4,
                    height: 4,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.grey.shade400,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Icon(Icons.location_on, color: Colors.green, size: 20),
              const SizedBox(width: 4),
              Text(
                'End',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            '${trip.destinations.length} stops',
            style: TextStyle(fontSize: 10, color: Colors.grey.shade600),
          ),
        ],
      ),
    );
  }

  /// Get color for marker based on index
  Color _getMarkerColor(int index) {
    const colors = [
      Colors.blue,
      Colors.purple,
      Colors.teal,
      Colors.indigo,
      Colors.cyan,
    ];
    return colors[index % colors.length];
  }

  /// Get icon for destination status
  IconData _getStatusIcon(String status) {
    return switch (status.toLowerCase()) {
      'pending' => Icons.schedule,
      'arrived' => Icons.location_on,
      'completed' => Icons.check_circle,
      'failed' => Icons.cancel,
      _ => Icons.help_outline,
    };
  }

  /// Build simplified route list when no polyline available
  Widget _buildSimpleRouteList() {
    return Card(
      margin: const EdgeInsets.all(16),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.route, color: Colors.blue, size: 20),
                const SizedBox(width: 8),
                const Text(
                  'Route',
                  style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                ),
              ],
            ),
            const SizedBox(height: 12),
            ...trip.destinations.asMap().entries.map((entry) {
              final index = entry.key + 1;
              final dest = entry.value;
              return Column(
                children: [
                  Row(
                    children: [
                      // Sequence marker
                      Container(
                        width: 28,
                        height: 28,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.blue.shade500,
                        ),
                        child: Center(
                          child: Text(
                            '$index',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      // Address
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              dest.address,
                              style: const TextStyle(
                                fontWeight: FontWeight.w500,
                                fontSize: 13,
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              dest.status.label,
                              style: TextStyle(
                                fontSize: 11,
                                color: dest.status.color,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (index < trip.destinations.length)
                    Padding(
                      padding: const EdgeInsets.only(left: 13),
                      child: SizedBox(
                        height: 20,
                        child: VerticalDivider(
                          color: Colors.grey.shade300,
                          thickness: 2,
                          width: 2,
                        ),
                      ),
                    ),
                ],
              );
            }),
          ],
        ),
      ),
    );
  }
}
