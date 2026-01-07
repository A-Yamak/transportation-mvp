import 'delivery_item_model.dart';
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
  final List<DeliveryItemModel> items;
  final bool hasItemTracking;

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
    this.items = const [],
    this.hasItemTracking = false,
  });

  /// Factory to create from API JSON response
  factory DestinationModel.fromJson(Map<String, dynamic> json) {
    // Parse items array if present
    final List<DeliveryItemModel> itemsList;
    if (json['items'] != null && json['items'] is List) {
      itemsList = (json['items'] as List)
          .map((item) => DeliveryItemModel.fromJson(item as Map<String, dynamic>))
          .toList();
    } else {
      itemsList = [];
    }

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
      items: itemsList,
      hasItemTracking: json['has_item_tracking'] == true || itemsList.isNotEmpty,
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
    List<DeliveryItemModel>? items,
    bool? hasItemTracking,
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
      items: items ?? this.items,
      hasItemTracking: hasItemTracking ?? this.hasItemTracking,
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
