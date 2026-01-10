import 'package:uuid/uuid.dart';

class TupperwareMovementModel {
  final String id;
  final String destinationId;
  final String shopId;
  final String productType; // e.g., 'boxes', 'trays', 'bags'
  final int quantityPickedup;
  final DateTime movedAt;
  final DateTime createdAt;

  TupperwareMovementModel({
    String? id,
    required this.destinationId,
    required this.shopId,
    required this.productType,
    required this.quantityPickedup,
    DateTime? movedAt,
    DateTime? createdAt,
  })  : id = id ?? const Uuid().v4(),
        movedAt = movedAt ?? DateTime.now(),
        createdAt = createdAt ?? DateTime.now();

  /// Factory constructor to parse from API JSON
  factory TupperwareMovementModel.fromJson(Map<String, dynamic> json) {
    return TupperwareMovementModel(
      id: json['id'].toString(),
      destinationId: json['destination_id'].toString(),
      shopId: json['shop_id'].toString(),
      productType: json['product_type'].toString(),
      quantityPickedup: (json['quantity_pickedup'] ?? 0) as int,
      movedAt: json['moved_at'] != null
          ? DateTime.parse(json['moved_at'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'].toString())
          : null,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'destination_id': destinationId,
      'shop_id': shopId,
      'product_type': productType,
      'quantity_pickedup': quantityPickedup,
    };
  }

  /// Create a copy with optional field replacements
  TupperwareMovementModel copyWith({
    String? id,
    String? destinationId,
    String? shopId,
    String? productType,
    int? quantityPickedup,
    DateTime? movedAt,
    DateTime? createdAt,
  }) {
    return TupperwareMovementModel(
      id: id ?? this.id,
      destinationId: destinationId ?? this.destinationId,
      shopId: shopId ?? this.shopId,
      productType: productType ?? this.productType,
      quantityPickedup: quantityPickedup ?? this.quantityPickedup,
      movedAt: movedAt ?? this.movedAt,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  /// Create a mock instance for testing
  factory TupperwareMovementModel.mock({
    String? id,
    String? destinationId,
    String? shopId,
    String productType = 'boxes',
    int quantityPickedup = 10,
  }) {
    return TupperwareMovementModel(
      id: id ?? const Uuid().v4(),
      destinationId: destinationId ?? const Uuid().v4(),
      shopId: shopId ?? 'SHOP-001',
      productType: productType,
      quantityPickedup: quantityPickedup,
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is TupperwareMovementModel &&
          runtimeType == other.runtimeType &&
          id == other.id &&
          destinationId == other.destinationId &&
          shopId == other.shopId &&
          productType == other.productType &&
          quantityPickedup == other.quantityPickedup;

  @override
  int get hashCode =>
      id.hashCode ^
      destinationId.hashCode ^
      shopId.hashCode ^
      productType.hashCode ^
      quantityPickedup.hashCode;

  @override
  String toString() =>
      'TupperwareMovementModel(id: $id, shop: $shopId, type: $productType, qty: $quantityPickedup)';
}
