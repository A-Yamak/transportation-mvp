/// Trip History Model for paginated trip list
class TripHistoryModel {
  final String id;
  final String status;
  final String date;
  final String? startedAt;
  final String? completedAt;
  final int? durationMinutes;
  final double? actualKm;
  final int destinationsCount;
  final int destinationsCompleted;
  final String? businessName;
  final TripVehicleModel? vehicle;

  const TripHistoryModel({
    required this.id,
    required this.status,
    required this.date,
    this.startedAt,
    this.completedAt,
    this.durationMinutes,
    this.actualKm,
    required this.destinationsCount,
    required this.destinationsCompleted,
    this.businessName,
    this.vehicle,
  });

  factory TripHistoryModel.fromJson(Map<String, dynamic> json) {
    return TripHistoryModel(
      id: json['id'] as String,
      status: json['status'] as String,
      date: json['date'] as String,
      startedAt: json['started_at'] as String?,
      completedAt: json['completed_at'] as String?,
      durationMinutes: json['duration_minutes'] as int?,
      actualKm: json['actual_km'] != null
          ? (json['actual_km'] as num).toDouble()
          : null,
      destinationsCount: json['destinations_count'] as int? ?? 0,
      destinationsCompleted: json['destinations_completed'] as int? ?? 0,
      businessName: json['business_name'] as String?,
      vehicle: json['vehicle'] != null
          ? TripVehicleModel.fromJson(json['vehicle'] as Map<String, dynamic>)
          : null,
    );
  }

  /// Get formatted duration (e.g., "3h 45m")
  String get formattedDuration {
    if (durationMinutes == null) return '-';
    final hours = durationMinutes! ~/ 60;
    final minutes = durationMinutes! % 60;
    if (hours > 0) {
      return '${hours}h ${minutes}m';
    }
    return '${minutes}m';
  }

  /// Get status display text
  String get statusDisplay {
    switch (status) {
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      case 'in_progress':
        return 'In Progress';
      default:
        return status;
    }
  }

  /// Check if all destinations were completed
  bool get isFullyCompleted =>
      status == 'completed' && destinationsCount == destinationsCompleted;
}

/// Simple vehicle model for trip history
class TripVehicleModel {
  final String make;
  final String model;
  final String licensePlate;

  const TripVehicleModel({
    required this.make,
    required this.model,
    required this.licensePlate,
  });

  factory TripVehicleModel.fromJson(Map<String, dynamic> json) {
    return TripVehicleModel(
      make: json['make'] as String,
      model: json['model'] as String,
      licensePlate: json['license_plate'] as String,
    );
  }

  String get fullName => '$make $model';
}

/// Paginated response for trip history
class TripHistoryResponse {
  final List<TripHistoryModel> trips;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const TripHistoryResponse({
    required this.trips,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory TripHistoryResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as List<dynamic>;
    final meta = json['meta'] as Map<String, dynamic>;

    return TripHistoryResponse(
      trips: data
          .map((item) => TripHistoryModel.fromJson(item as Map<String, dynamic>))
          .toList(),
      currentPage: meta['current_page'] as int,
      lastPage: meta['last_page'] as int,
      perPage: meta['per_page'] as int,
      total: meta['total'] as int,
    );
  }

  bool get hasMore => currentPage < lastPage;
}
