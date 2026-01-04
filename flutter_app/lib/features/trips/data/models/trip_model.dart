import 'destination_model.dart';
import 'destination_status.dart';
import 'trip_status.dart';

class TripModel {
  final String id;
  final String deliveryRequestId;
  final TripStatus status;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final double? actualKmDriven;
  final String businessName;
  final List<DestinationModel> destinations;

  TripModel({
    required this.id,
    required this.deliveryRequestId,
    required this.status,
    this.startedAt,
    this.completedAt,
    this.actualKmDriven,
    required this.businessName,
    required this.destinations,
  });

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
    DateTime? startedAt,
    DateTime? completedAt,
    double? actualKmDriven,
    String? businessName,
    List<DestinationModel>? destinations,
  }) {
    return TripModel(
      id: id ?? this.id,
      deliveryRequestId: deliveryRequestId ?? this.deliveryRequestId,
      status: status ?? this.status,
      startedAt: startedAt ?? this.startedAt,
      completedAt: completedAt ?? this.completedAt,
      actualKmDriven: actualKmDriven ?? this.actualKmDriven,
      businessName: businessName ?? this.businessName,
      destinations: destinations ?? this.destinations,
    );
  }

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
