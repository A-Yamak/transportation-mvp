import 'destination_model.dart';
import 'destination_status.dart';
import 'trip_status.dart';

class TripModel {
  final String id;
  final String deliveryRequestId;
  final TripStatus status;
  final DateTime? scheduledDate;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final double? actualKmDriven;
  final double? estimatedKm;
  final double? estimatedCost;
  final String businessName;
  final String? polyline;
  final List<DestinationModel> destinations;

  TripModel({
    required this.id,
    required this.deliveryRequestId,
    required this.status,
    this.scheduledDate,
    this.startedAt,
    this.completedAt,
    this.actualKmDriven,
    this.estimatedKm,
    this.estimatedCost,
    required this.businessName,
    this.polyline,
    required this.destinations,
  });

  /// Factory to create from API JSON response
  factory TripModel.fromJson(Map<String, dynamic> json) {
    // Extract business name from nested delivery_request.business
    String businessName = '';
    final deliveryRequest = json['delivery_request'];
    if (deliveryRequest != null) {
      final business = deliveryRequest['business'];
      if (business != null) {
        businessName = business['name'] ?? '';
      }
    }

    // Parse destinations list
    final destinationsJson = json['destinations'] as List? ?? [];
    final destinations = destinationsJson
        .map((d) => DestinationModel.fromJson(d as Map<String, dynamic>))
        .toList();

    // Sort destinations by sequence_order
    destinations.sort((a, b) => a.sequenceOrder.compareTo(b.sequenceOrder));

    return TripModel(
      id: json['id'].toString(),
      deliveryRequestId: (deliveryRequest?['id'] ?? json['delivery_request_id'] ?? '').toString(),
      status: TripStatus.fromString(json['status'] ?? 'pending'),
      scheduledDate: json['scheduled_date'] != null
          ? DateTime.parse(json['scheduled_date'])
          : null,
      startedAt: json['started_at'] != null
          ? DateTime.parse(json['started_at'])
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'])
          : null,
      actualKmDriven: json['actual_km']?.toDouble(),
      estimatedKm: deliveryRequest?['total_km']?.toDouble(),
      estimatedCost: deliveryRequest?['estimated_cost']?.toDouble(),
      businessName: businessName,
      polyline: deliveryRequest?['polyline'],
      destinations: destinations,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'delivery_request_id': deliveryRequestId,
      'status': status.toApiString(),
      'scheduled_date': scheduledDate?.toIso8601String(),
      'started_at': startedAt?.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
      'actual_km': actualKmDriven,
      'destinations': destinations.map((d) => d.toJson()).toList(),
    };
  }

  /// Computed properties
  int get completedDestinationsCount =>
      destinations.where((d) => d.status == DestinationStatus.completed).length;

  int get totalDestinations => destinations.length;

  bool get isCompleted => completedDestinationsCount == totalDestinations;

  double get progress => totalDestinations > 0
      ? completedDestinationsCount / totalDestinations
      : 0.0;

  DestinationModel? get nextDestination {
    try {
      return destinations.firstWhere(
        (d) => d.status == DestinationStatus.pending,
      );
    } catch (e) {
      return destinations.isNotEmpty ? destinations.first : null;
    }
  }

  /// copyWith for immutability
  TripModel copyWith({
    String? id,
    String? deliveryRequestId,
    TripStatus? status,
    DateTime? scheduledDate,
    DateTime? startedAt,
    DateTime? completedAt,
    double? actualKmDriven,
    double? estimatedKm,
    double? estimatedCost,
    String? businessName,
    String? polyline,
    List<DestinationModel>? destinations,
  }) {
    return TripModel(
      id: id ?? this.id,
      deliveryRequestId: deliveryRequestId ?? this.deliveryRequestId,
      status: status ?? this.status,
      scheduledDate: scheduledDate ?? this.scheduledDate,
      startedAt: startedAt ?? this.startedAt,
      completedAt: completedAt ?? this.completedAt,
      actualKmDriven: actualKmDriven ?? this.actualKmDriven,
      estimatedKm: estimatedKm ?? this.estimatedKm,
      estimatedCost: estimatedCost ?? this.estimatedCost,
      businessName: businessName ?? this.businessName,
      polyline: polyline ?? this.polyline,
      destinations: destinations ?? this.destinations,
    );
  }

  /// Check if trip can be started
  bool get canStart => status == TripStatus.notStarted;

  /// Check if trip can be completed (all destinations done)
  bool get canComplete =>
      status == TripStatus.inProgress &&
      destinations.every((d) => d.status == DestinationStatus.completed ||
          d.status == DestinationStatus.failed);

  /// Mock factory
  factory TripModel.mock({
    required String id,
    required TripStatus status,
    required List<DestinationModel> destinations,
    String? businessName,
  }) {
    return TripModel(
      id: id,
      deliveryRequestId: 'DR-$id',
      status: status,
      startedAt: status != TripStatus.notStarted
          ? DateTime.now().subtract(Duration(hours: 2))
          : null,
      completedAt: status == TripStatus.completed ? DateTime.now() : null,
      businessName: businessName ?? 'Sweets Factory ERP',
      destinations: destinations,
    );
  }
}
