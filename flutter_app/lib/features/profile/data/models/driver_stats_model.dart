/// Driver Stats Model
class DriverStatsModel {
  final StatsPeriod today;
  final StatsPeriod thisMonth;
  final StatsPeriod allTime;
  final VehicleStats? vehicle;

  const DriverStatsModel({
    required this.today,
    required this.thisMonth,
    required this.allTime,
    this.vehicle,
  });

  factory DriverStatsModel.fromJson(Map<String, dynamic> json) {
    return DriverStatsModel(
      today: StatsPeriod.fromJson(json['today'] as Map<String, dynamic>),
      thisMonth: StatsPeriod.fromJson(json['this_month'] as Map<String, dynamic>),
      allTime: StatsPeriod.fromJson(json['all_time'] as Map<String, dynamic>),
      vehicle: json['vehicle'] != null
          ? VehicleStats.fromJson(json['vehicle'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// Stats for a specific period
class StatsPeriod {
  final int tripsCount;
  final int destinationsCompleted;
  final double kmDriven;

  const StatsPeriod({
    required this.tripsCount,
    required this.destinationsCompleted,
    required this.kmDriven,
  });

  factory StatsPeriod.fromJson(Map<String, dynamic> json) {
    return StatsPeriod(
      tripsCount: json['trips_count'] as int,
      destinationsCompleted: json['destinations_completed'] as int,
      kmDriven: (json['km_driven'] as num).toDouble(),
    );
  }
}

/// Vehicle odometer stats
class VehicleStats {
  final double acquisitionKm;
  final double totalKmDriven;
  final double appTrackedKm;

  const VehicleStats({
    required this.acquisitionKm,
    required this.totalKmDriven,
    required this.appTrackedKm,
  });

  factory VehicleStats.fromJson(Map<String, dynamic> json) {
    return VehicleStats(
      acquisitionKm: (json['acquisition_km'] as num).toDouble(),
      totalKmDriven: (json['total_km_driven'] as num).toDouble(),
      appTrackedKm: (json['app_tracked_km'] as num).toDouble(),
    );
  }
}
