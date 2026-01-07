import 'package:flutter/material.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../../data/models/driver_profile_model.dart';
import '../../../../shared/theme/app_theme.dart';

/// Vehicle Info Card with odometer tracking
class VehicleInfoCard extends StatelessWidget {
  final VehicleModel vehicle;

  const VehicleInfoCard({super.key, required this.vehicle});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Header with vehicle name
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withOpacity(0.1),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.directions_car,
                  color: AppTheme.primaryColor,
                  size: 28,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        vehicle.fullName,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        vehicle.licensePlate,
                        style: TextStyle(
                          color: Colors.grey[600],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Odometer Section
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l10n.odometer,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: Colors.grey,
                  ),
                ),
                const SizedBox(height: 12),

                // Current Odometer (Main Display)
                _OdometerDisplay(
                  value: vehicle.totalKmDriven,
                  label: l10n.currentOdometer,
                  isPrimary: true,
                ),

                const SizedBox(height: 16),

                // Acquisition and App Tracked
                Row(
                  children: [
                    Expanded(
                      child: _KmInfoTile(
                        icon: Icons.flag_outlined,
                        value: vehicle.acquisitionKm,
                        label: l10n.acquisitionKm,
                        subtitle: vehicle.acquisitionDate ?? '-',
                      ),
                    ),
                    Container(
                      width: 1,
                      height: 60,
                      color: Colors.grey[300],
                    ),
                    Expanded(
                      child: _KmInfoTile(
                        icon: Icons.smartphone,
                        value: vehicle.appTrackedKm,
                        label: l10n.appTrackedKm,
                        subtitle: l10n.trackedViaApp,
                        highlight: true,
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 12),
                const Divider(),
                const SizedBox(height: 12),

                // Monthly KM
                Row(
                  children: [
                    Icon(Icons.calendar_month, color: Colors.grey[600], size: 20),
                    const SizedBox(width: 8),
                    Text(
                      '${l10n.thisMonth}: ',
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                    Text(
                      '${vehicle.monthlyKmApp.toStringAsFixed(1)} ${l10n.km}',
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  ],
                ),

                // Fuel Efficiency Section (if data available)
                if (vehicle.kmPerLiter != null) ...[
                  const SizedBox(height: 12),
                  const Divider(),
                  const SizedBox(height: 12),
                  Text(
                    l10n.fuelEfficiency,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                      color: Colors.grey,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _FuelInfoTile(
                          icon: Icons.local_gas_station,
                          value: '${vehicle.tankCapacityLiters?.toStringAsFixed(1) ?? '-'} L',
                          label: l10n.tankCapacity,
                        ),
                      ),
                      Container(
                        width: 1,
                        height: 50,
                        color: Colors.grey[300],
                      ),
                      Expanded(
                        child: _FuelInfoTile(
                          icon: Icons.route,
                          value: '${vehicle.fullTankRangeKm?.toStringAsFixed(0) ?? '-'} ${l10n.km}',
                          label: l10n.fullTankRange,
                        ),
                      ),
                      Container(
                        width: 1,
                        height: 50,
                        color: Colors.grey[300],
                      ),
                      Expanded(
                        child: _FuelInfoTile(
                          icon: Icons.eco,
                          value: '${vehicle.kmPerLiter?.toStringAsFixed(2) ?? '-'}',
                          label: l10n.kmPerLiter,
                          highlight: true,
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Main odometer display
class _OdometerDisplay extends StatelessWidget {
  final double value;
  final String label;
  final bool isPrimary;

  const _OdometerDisplay({
    required this.value,
    required this.label,
    this.isPrimary = false,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isPrimary ? AppTheme.primaryColor.withOpacity(0.1) : Colors.grey[100],
        borderRadius: BorderRadius.circular(12),
        border: isPrimary
            ? Border.all(color: AppTheme.primaryColor.withOpacity(0.3))
            : null,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.speed,
            color: isPrimary ? AppTheme.primaryColor : Colors.grey[600],
            size: 32,
          ),
          const SizedBox(width: 12),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.grey[600],
                ),
              ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.baseline,
                textBaseline: TextBaseline.alphabetic,
                children: [
                  Text(
                    _formatOdometer(value),
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.bold,
                      color: isPrimary ? AppTheme.primaryColor : Colors.black87,
                    ),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    l10n.km,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[600],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _formatOdometer(double km) {
    if (km >= 1000) {
      return km.toStringAsFixed(0).replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]},');
    }
    return km.toStringAsFixed(1);
  }
}

/// KM info tile for acquisition and app-tracked
class _KmInfoTile extends StatelessWidget {
  final IconData icon;
  final double value;
  final String label;
  final String subtitle;
  final bool highlight;

  const _KmInfoTile({
    required this.icon,
    required this.value,
    required this.label,
    required this.subtitle,
    this.highlight = false,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(
        children: [
          Icon(
            icon,
            color: highlight ? AppTheme.successColor : Colors.grey[600],
            size: 24,
          ),
          const SizedBox(height: 4),
          Text(
            '${value.toStringAsFixed(1)} ${l10n.km}',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: highlight ? AppTheme.successColor : Colors.black87,
            ),
          ),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              color: Colors.grey[600],
            ),
            textAlign: TextAlign.center,
          ),
          Text(
            subtitle,
            style: TextStyle(
              fontSize: 10,
              color: Colors.grey[500],
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}

/// Fuel info tile for tank capacity, range, and efficiency
class _FuelInfoTile extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  final bool highlight;

  const _FuelInfoTile({
    required this.icon,
    required this.value,
    required this.label,
    this.highlight = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        children: [
          Icon(
            icon,
            color: highlight ? AppTheme.successColor : Colors.grey[600],
            size: 20,
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.bold,
              fontSize: 13,
              color: highlight ? AppTheme.successColor : Colors.black87,
            ),
          ),
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              color: Colors.grey[600],
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
