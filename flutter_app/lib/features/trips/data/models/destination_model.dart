import 'destination_status.dart';

class DestinationModel {
  final String id;
  final String address;
  final double lat;
  final double lng;
  final int sequenceOrder;
  final DestinationStatus status;
  final DateTime? arrivedAt;
  final DateTime? completedAt;
  final String? externalId;

  DestinationModel({
    required this.id,
    required this.address,
    required this.lat,
    required this.lng,
    required this.sequenceOrder,
    required this.status,
    this.arrivedAt,
    this.completedAt,
    this.externalId,
  });

  /// copyWith for immutability
  DestinationModel copyWith({
    String? id,
    String? address,
    double? lat,
    double? lng,
    int? sequenceOrder,
    DestinationStatus? status,
    DateTime? arrivedAt,
    DateTime? completedAt,
    String? externalId,
  }) {
    return DestinationModel(
      id: id ?? this.id,
      address: address ?? this.address,
      lat: lat ?? this.lat,
      lng: lng ?? this.lng,
      sequenceOrder: sequenceOrder ?? this.sequenceOrder,
      status: status ?? this.status,
      arrivedAt: arrivedAt ?? this.arrivedAt,
      completedAt: completedAt ?? this.completedAt,
      externalId: externalId ?? this.externalId,
    );
  }

  /// Navigation URL (for snackbar display)
  String get navigationUrl =>
      'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving';

  /// Factory for mock data
  factory DestinationModel.mock({
    required int order,
    required String address,
    required double lat,
    required double lng,
    DestinationStatus status = DestinationStatus.pending,
  }) {
    return DestinationModel(
      id: 'DEST-$order',
      address: address,
      lat: lat,
      lng: lng,
      sequenceOrder: order,
      status: status,
      externalId: 'ORDER-$order',
    );
  }
}
