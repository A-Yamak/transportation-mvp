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
  final String? contactName;
  final String? contactPhone;
  final String? notes;
  final String? signatureUrl;
  final String? photoUrl;

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
    this.contactName,
    this.contactPhone,
    this.notes,
    this.signatureUrl,
    this.photoUrl,
  });

  /// Factory to create from API JSON response
  factory DestinationModel.fromJson(Map<String, dynamic> json) {
    return DestinationModel(
      id: json['id'].toString(),
      address: json['address'] ?? '',
      lat: (json['lat'] ?? json['latitude'] ?? 0.0).toDouble(),
      lng: (json['lng'] ?? json['longitude'] ?? 0.0).toDouble(),
      sequenceOrder: json['sequence_order'] ?? 0,
      status: DestinationStatus.fromString(json['status'] ?? 'pending'),
      arrivedAt: json['arrived_at'] != null
          ? DateTime.parse(json['arrived_at'])
          : null,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'])
          : null,
      externalId: json['external_id']?.toString(),
      contactName: json['contact_name'],
      contactPhone: json['contact_phone'],
      notes: json['notes'],
      signatureUrl: json['signature_url'],
      photoUrl: json['photo_url'],
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'address': address,
      'lat': lat,
      'lng': lng,
      'sequence_order': sequenceOrder,
      'status': status.toApiString(),
      'arrived_at': arrivedAt?.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
      'external_id': externalId,
      'contact_name': contactName,
      'contact_phone': contactPhone,
      'notes': notes,
    };
  }

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
    String? contactName,
    String? contactPhone,
    String? notes,
    String? signatureUrl,
    String? photoUrl,
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
      contactName: contactName ?? this.contactName,
      contactPhone: contactPhone ?? this.contactPhone,
      notes: notes ?? this.notes,
      signatureUrl: signatureUrl ?? this.signatureUrl,
      photoUrl: photoUrl ?? this.photoUrl,
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
