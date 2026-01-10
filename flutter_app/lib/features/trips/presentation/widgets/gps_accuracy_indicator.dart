import 'package:flutter/material.dart';

/// Widget to display GPS accuracy status
/// Shows a color-coded indicator with accuracy information
class GPSAccuracyIndicator extends StatelessWidget {
  final double? accuracy;
  final bool isTracking;

  const GPSAccuracyIndicator({
    Key? key,
    this.accuracy,
    this.isTracking = false,
  }) : super(key: key);

  /// Get accuracy status (green/yellow/red)
  _AccuracyStatus get _status {
    if (!isTracking) return _AccuracyStatus.inactive;
    if (accuracy == null) return _AccuracyStatus.unknown;
    if (accuracy! < 10) return _AccuracyStatus.excellent; // < 10m = excellent
    if (accuracy! < 30) return _AccuracyStatus.good; // 10-30m = good
    if (accuracy! < 100) return _AccuracyStatus.fair; // 30-100m = fair
    return _AccuracyStatus.poor; // > 100m = poor
  }

  @override
  Widget build(BuildContext context) {
    final status = _status;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: status.backgroundColor,
        border: Border.all(color: status.color),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            status.icon,
            color: status.color,
            size: 16,
          ),
          const SizedBox(width: 8),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                status.label,
                style: TextStyle(
                  color: status.color,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
              if (accuracy != null)
                Text(
                  '${accuracy!.toStringAsFixed(1)}m',
                  style: TextStyle(
                    color: status.color,
                    fontSize: 10,
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

enum _AccuracyStatus {
  excellent, // < 10m - green
  good,      // 10-30m - blue
  fair,      // 30-100m - orange
  poor,      // > 100m - red
  unknown,   // No data - gray
  inactive;  // Not tracking - gray

  Color get color {
    return switch (this) {
      _AccuracyStatus.excellent => Colors.green,
      _AccuracyStatus.good => Colors.blue,
      _AccuracyStatus.fair => Colors.orange,
      _AccuracyStatus.poor => Colors.red,
      _AccuracyStatus.unknown => Colors.grey,
      _AccuracyStatus.inactive => Colors.grey.shade400,
    };
  }

  Color get backgroundColor {
    return switch (this) {
      _AccuracyStatus.excellent => Colors.green.shade50,
      _AccuracyStatus.good => Colors.blue.shade50,
      _AccuracyStatus.fair => Colors.orange.shade50,
      _AccuracyStatus.poor => Colors.red.shade50,
      _AccuracyStatus.unknown => Colors.grey.shade100,
      _AccuracyStatus.inactive => Colors.grey.shade100,
    };
  }

  IconData get icon {
    return switch (this) {
      _AccuracyStatus.excellent => Icons.location_on,
      _AccuracyStatus.good => Icons.location_on,
      _AccuracyStatus.fair => Icons.location_on,
      _AccuracyStatus.poor => Icons.location_off,
      _AccuracyStatus.unknown => Icons.location_off,
      _AccuracyStatus.inactive => Icons.gps_off,
    };
  }

  String get label {
    return switch (this) {
      _AccuracyStatus.excellent => 'GPS Excellent',
      _AccuracyStatus.good => 'GPS Good',
      _AccuracyStatus.fair => 'GPS Fair',
      _AccuracyStatus.poor => 'GPS Poor',
      _AccuracyStatus.unknown => 'GPS Unknown',
      _AccuracyStatus.inactive => 'GPS Not Active',
    };
  }

  String get description {
    return switch (this) {
      _AccuracyStatus.excellent => 'Highly accurate location',
      _AccuracyStatus.good => 'Good location accuracy',
      _AccuracyStatus.fair => 'Moderate accuracy - may be slightly off',
      _AccuracyStatus.poor => 'Poor accuracy - distance may be inaccurate',
      _AccuracyStatus.unknown => 'Location accuracy unknown',
      _AccuracyStatus.inactive => 'GPS tracking not active',
    };
  }
}
