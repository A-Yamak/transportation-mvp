import 'package:flutter/material.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';

/// Stats Card for displaying period statistics
class StatsCard extends StatelessWidget {
  final String title;
  final int tripsCount;
  final int destinationsCompleted;
  final double kmDriven;
  final Color color;
  final bool expanded;

  const StatsCard({
    super.key,
    required this.title,
    required this.tripsCount,
    required this.destinationsCompleted,
    required this.kmDriven,
    required this.color,
    this.expanded = false,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Card(
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          gradient: LinearGradient(
            colors: [
              color.withOpacity(0.1),
              color.withOpacity(0.05),
            ],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: expanded
            ? Row(
                children: [
                  _buildHeader(context),
                  const SizedBox(width: 24),
                  Expanded(child: _buildStats(l10n)),
                ],
              )
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildHeader(context),
                  const SizedBox(height: 12),
                  _buildStats(l10n),
                ],
              ),
      ),
    );
  }

  Widget _buildHeader(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: color.withOpacity(0.2),
            borderRadius: BorderRadius.circular(4),
          ),
          child: Text(
            title,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStats(AppLocalizations l10n) {
    return Wrap(
      spacing: 16,
      runSpacing: 8,
      children: [
        _StatItem(
          icon: Icons.local_shipping,
          value: tripsCount.toString(),
          label: l10n.trips,
          color: color,
        ),
        _StatItem(
          icon: Icons.location_on,
          value: destinationsCompleted.toString(),
          label: l10n.deliveries,
          color: color,
        ),
        _StatItem(
          icon: Icons.speed,
          value: '${kmDriven.toStringAsFixed(1)} ${l10n.km}',
          label: l10n.distance,
          color: color,
        ),
      ],
    );
  }
}

class _StatItem extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  final Color color;

  const _StatItem({
    required this.icon,
    required this.value,
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 16, color: color),
        const SizedBox(width: 4),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              value,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: color,
              ),
            ),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ],
    );
  }
}
