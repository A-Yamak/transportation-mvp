/// Discrepancy reasons matching backend enum
enum ItemDiscrepancyReason {
  damagedInTransit('damaged_in_transit', 'Damaged in Transit', 'تلف أثناء النقل'),
  customerRefused('customer_refused', 'Customer Refused', 'رفض العميل'),
  qualityIssue('quality_issue', 'Quality Issue', 'مشكلة جودة'),
  wrongProduct('wrong_product', 'Wrong Product', 'منتج خاطئ'),
  shortage('shortage', 'Shortage', 'نقص'),
  other('other', 'Other', 'أخرى');

  final String apiValue;
  final String labelEn;
  final String labelAr;

  const ItemDiscrepancyReason(this.apiValue, this.labelEn, this.labelAr);

  String get label => labelEn;

  static ItemDiscrepancyReason? fromString(String? value) {
    if (value == null) return null;
    return ItemDiscrepancyReason.values.firstWhere(
      (e) => e.apiValue == value,
      orElse: () => ItemDiscrepancyReason.other,
    );
  }
}

/// Model for individual delivery items within a destination
class DeliveryItemModel {
  final String id;
  final String orderItemId;
  final String? name;
  final String? sku;
  final double? unitPrice;
  final int quantityOrdered;
  int quantityDelivered;
  ItemDiscrepancyReason? discrepancyReason;
  String? notes;

  DeliveryItemModel({
    required this.id,
    required this.orderItemId,
    this.name,
    this.sku,
    this.unitPrice,
    required this.quantityOrdered,
    this.quantityDelivered = 0,
    this.discrepancyReason,
    this.notes,
  });

  /// Get the line total (unit_price * quantity_ordered)
  double? get lineTotal =>
      unitPrice != null ? unitPrice! * quantityOrdered : null;

  /// Get the delivered total (unit_price * quantity_delivered)
  double? get deliveredTotal =>
      unitPrice != null ? unitPrice! * quantityDelivered : null;

  /// Factory to create from API JSON response
  factory DeliveryItemModel.fromJson(Map<String, dynamic> json) {
    return DeliveryItemModel(
      id: json['id']?.toString() ?? json['order_item_id']?.toString() ?? '',
      orderItemId: json['order_item_id']?.toString() ?? '',
      name: json['name']?.toString(),
      sku: json['sku']?.toString(),
      unitPrice: json['unit_price'] != null
          ? (json['unit_price'] as num).toDouble()
          : null,
      quantityOrdered: (json['quantity_ordered'] ?? 0) as int,
      quantityDelivered: (json['quantity_delivered'] ?? 0) as int,
      discrepancyReason: ItemDiscrepancyReason.fromString(
        json['delivery_reason']?.toString(),
      ),
      notes: json['notes']?.toString(),
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'order_item_id': orderItemId,
      'quantity_ordered': quantityOrdered,
      'quantity_delivered': quantityDelivered,
      if (discrepancyReason != null) 'reason': discrepancyReason!.apiValue,
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }

  /// Check if item is fully delivered
  bool get isFullyDelivered => quantityDelivered >= quantityOrdered;

  /// Check if item has discrepancy
  bool get hasDiscrepancy => quantityDelivered < quantityOrdered;

  /// Get shortage amount
  int get shortage =>
      quantityOrdered > quantityDelivered ? quantityOrdered - quantityDelivered : 0;

  /// Create a copy with updated values
  DeliveryItemModel copyWith({
    String? id,
    String? orderItemId,
    String? name,
    String? sku,
    double? unitPrice,
    int? quantityOrdered,
    int? quantityDelivered,
    ItemDiscrepancyReason? discrepancyReason,
    String? notes,
  }) {
    return DeliveryItemModel(
      id: id ?? this.id,
      orderItemId: orderItemId ?? this.orderItemId,
      name: name ?? this.name,
      sku: sku ?? this.sku,
      unitPrice: unitPrice ?? this.unitPrice,
      quantityOrdered: quantityOrdered ?? this.quantityOrdered,
      quantityDelivered: quantityDelivered ?? this.quantityDelivered,
      discrepancyReason: discrepancyReason ?? this.discrepancyReason,
      notes: notes ?? this.notes,
    );
  }

  /// Factory for mock data
  factory DeliveryItemModel.mock({
    required String orderItemId,
    required String name,
    required int quantity,
  }) {
    return DeliveryItemModel(
      id: 'ITEM-$orderItemId',
      orderItemId: orderItemId,
      name: name,
      quantityOrdered: quantity,
      quantityDelivered: quantity, // Default to full delivery
    );
  }
}
