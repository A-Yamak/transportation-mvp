import 'waste_item_model.dart';

/// Represents a waste collection event at a shop
class WasteCollectionModel {
  final String id;
  final String shopId;
  final String shopName;
  final DateTime collectionDate;
  final List<WasteItemModel> items;
  final DateTime? collectedAt;
  final String? driverNotes;

  WasteCollectionModel({
    required this.id,
    required this.shopId,
    required this.shopName,
    required this.collectionDate,
    this.items = const [],
    this.collectedAt,
    this.driverNotes,
  });

  /// Check if waste has been collected
  bool get isCollected => collectedAt != null;

  /// Get total waste pieces across all items
  int get totalWastePieces => items.fold(0, (sum, item) => sum + item.piecesWaste);

  /// Get total sold pieces across all items
  int get totalSoldPieces => items.fold(0, (sum, item) => sum + item.piecesSold);

  /// Get total delivered pieces across all items
  int get totalDeliveredPieces =>
      items.fold(0, (sum, item) => sum + item.quantityDelivered);

  /// Get count of waste items
  int get itemsCount => items.length;

  /// Get count of expired items
  int get expiredItemsCount => items.where((item) => item.isExpired).length;

  /// Get count of uncollected items
  int get uncollectedItemsCount => items.length - itemsCount;

  /// Calculate total waste percentage
  double get totalWastePercentage {
    if (totalDeliveredPieces == 0) return 0.0;
    return (totalWastePieces / totalDeliveredPieces) * 100;
  }

  /// Factory to create from API JSON response
  factory WasteCollectionModel.fromJson(Map<String, dynamic> json) {
    final List<WasteItemModel> itemsList;
    if (json['items'] != null && json['items'] is List) {
      itemsList = (json['items'] as List)
          .map((item) => WasteItemModel.fromJson(item as Map<String, dynamic>))
          .toList();
    } else if (json['waste_items'] != null && json['waste_items'] is List) {
      itemsList = (json['waste_items'] as List)
          .map((item) => WasteItemModel.fromJson(item as Map<String, dynamic>))
          .toList();
    } else {
      itemsList = [];
    }

    return WasteCollectionModel(
      id: json['id']?.toString() ?? '',
      shopId: json['shop_id']?.toString() ?? '',
      shopName: json['shop_name']?.toString() ?? 'Unknown Shop',
      collectionDate: json['collection_date'] != null
          ? DateTime.parse(json['collection_date'].toString())
          : DateTime.now(),
      items: itemsList,
      collectedAt: json['collected_at'] != null
          ? DateTime.parse(json['collected_at'].toString())
          : null,
      driverNotes: json['driver_notes']?.toString(),
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'shop_id': shopId,
      'shop_name': shopName,
      'collection_date': collectionDate.toIso8601String(),
      'items': items.map((item) => item.toJson()).toList(),
      if (collectedAt != null) 'collected_at': collectedAt!.toIso8601String(),
      if (driverNotes != null && driverNotes!.isNotEmpty)
        'driver_notes': driverNotes,
    };
  }

  /// Create a copy with updated values
  WasteCollectionModel copyWith({
    String? id,
    String? shopId,
    String? shopName,
    DateTime? collectionDate,
    List<WasteItemModel>? items,
    DateTime? collectedAt,
    String? driverNotes,
  }) {
    return WasteCollectionModel(
      id: id ?? this.id,
      shopId: shopId ?? this.shopId,
      shopName: shopName ?? this.shopName,
      collectionDate: collectionDate ?? this.collectionDate,
      items: items ?? this.items,
      collectedAt: collectedAt ?? this.collectedAt,
      driverNotes: driverNotes ?? this.driverNotes,
    );
  }

  /// Factory for mock data
  factory WasteCollectionModel.mock({
    required String shopId,
    required String shopName,
    int itemCount = 3,
  }) {
    return WasteCollectionModel(
      id: 'WASTE-COL-$shopId',
      shopId: shopId,
      shopName: shopName,
      collectionDate: DateTime.now(),
      items: List.generate(
        itemCount,
        (index) => WasteItemModel.mock(
          orderItemId: 'ITEM-${index + 1}',
          productName: 'Product ${index + 1}',
          quantityDelivered: 10 + (index * 5),
          daysOld: 2 + index,
        ),
      ),
    );
  }
}
