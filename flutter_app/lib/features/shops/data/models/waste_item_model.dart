/// Represents a single waste item within a waste collection
class WasteItemModel {
  final String id;
  final String wasteCollectionId;
  final String orderItemId;
  final String productName;
  final int quantityDelivered;
  final DateTime deliveredAt;
  final DateTime expiresAt;
  int piecesWaste;
  final String? notes;

  WasteItemModel({
    required this.id,
    required this.wasteCollectionId,
    required this.orderItemId,
    required this.productName,
    required this.quantityDelivered,
    required this.deliveredAt,
    required this.expiresAt,
    this.piecesWaste = 0,
    this.notes,
  });

  /// Calculate pieces sold (delivered - waste)
  int get piecesSold => quantityDelivered - piecesWaste;

  /// Check if item is expired
  bool get isExpired => expiresAt.isBefore(DateTime.now());

  /// Calculate days since expiry (0 if not expired)
  int get daysExpired {
    if (!isExpired) return 0;
    return DateTime.now().difference(expiresAt).inDays;
  }

  /// Calculate waste percentage (waste / delivered * 100)
  double get wastePercentage {
    if (quantityDelivered == 0) return 0.0;
    return (piecesWaste / quantityDelivered) * 100;
  }

  /// Validate waste quantity doesn't exceed delivered
  bool get isValidWasteQuantity => piecesWaste <= quantityDelivered;

  /// Factory to create from API JSON response
  factory WasteItemModel.fromJson(Map<String, dynamic> json) {
    return WasteItemModel(
      id: json['id']?.toString() ?? '',
      wasteCollectionId: json['waste_collection_id']?.toString() ?? '',
      orderItemId: json['order_item_id']?.toString() ?? '',
      productName: json['product_name']?.toString() ?? '',
      quantityDelivered: (json['quantity_delivered'] ?? 0) as int,
      deliveredAt: json['delivered_at'] != null
          ? DateTime.parse(json['delivered_at'].toString())
          : DateTime.now(),
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'].toString())
          : DateTime.now().add(Duration(days: 30)),
      piecesWaste: (json['pieces_waste'] ?? 0) as int,
      notes: json['notes']?.toString(),
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'waste_item_id': id,
      'pieces_waste': piecesWaste,
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }

  /// Create a copy with updated values
  WasteItemModel copyWith({
    String? id,
    String? wasteCollectionId,
    String? orderItemId,
    String? productName,
    int? quantityDelivered,
    DateTime? deliveredAt,
    DateTime? expiresAt,
    int? piecesWaste,
    String? notes,
  }) {
    return WasteItemModel(
      id: id ?? this.id,
      wasteCollectionId: wasteCollectionId ?? this.wasteCollectionId,
      orderItemId: orderItemId ?? this.orderItemId,
      productName: productName ?? this.productName,
      quantityDelivered: quantityDelivered ?? this.quantityDelivered,
      deliveredAt: deliveredAt ?? this.deliveredAt,
      expiresAt: expiresAt ?? this.expiresAt,
      piecesWaste: piecesWaste ?? this.piecesWaste,
      notes: notes ?? this.notes,
    );
  }

  /// Factory for mock data
  factory WasteItemModel.mock({
    required String orderItemId,
    required String productName,
    required int quantityDelivered,
    int daysOld = 1,
  }) {
    return WasteItemModel(
      id: 'WASTE-ITEM-$orderItemId',
      wasteCollectionId: 'WASTE-COL-123',
      orderItemId: orderItemId,
      productName: productName,
      quantityDelivered: quantityDelivered,
      deliveredAt: DateTime.now().subtract(Duration(days: daysOld)),
      expiresAt: DateTime.now().subtract(Duration(days: 1)), // Expired 1 day ago
      piecesWaste: 0,
    );
  }
}
