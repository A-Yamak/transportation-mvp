import 'waste_collection_model.dart';

/// Represents a persistent shop location
class ShopModel {
  final String id;
  final String externalShopId;
  final String name;
  final String address;
  final double lat;
  final double lng;
  final String? contactName;
  final String? contactPhone;
  final bool trackWaste;
  final bool isActive;
  final DateTime? lastSyncedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  // Contextual data (from waste/delivery endpoints)
  final WasteCollectionModel? expectedWaste;
  final int? sequenceInTrip;

  // Summary data (from list endpoint - used when expectedWaste.items is empty)
  final int? wasteSummaryItemsCount;
  final int? wasteSummaryTotalDelivered;
  final int? wasteSummaryTotalWaste;
  final int? wasteSummaryTotalSold;
  final int? wasteSummaryExpiredCount;
  final bool? hasPendingWaste;
  final bool? isWasteCollected;

  ShopModel({
    required this.id,
    required this.externalShopId,
    required this.name,
    required this.address,
    required this.lat,
    required this.lng,
    this.contactName,
    this.contactPhone,
    this.trackWaste = false,
    this.isActive = true,
    this.lastSyncedAt,
    required this.createdAt,
    required this.updatedAt,
    this.expectedWaste,
    this.sequenceInTrip,
    this.wasteSummaryItemsCount,
    this.wasteSummaryTotalDelivered,
    this.wasteSummaryTotalWaste,
    this.wasteSummaryTotalSold,
    this.wasteSummaryExpiredCount,
    this.hasPendingWaste,
    this.isWasteCollected,
  });

  /// Check if shop has waste to collect
  bool get hasWaste =>
      hasPendingWaste == true ||
      (expectedWaste != null && expectedWaste!.items.isNotEmpty);

  /// Check if waste has been collected
  bool get wasteCollected => expectedWaste?.isCollected ?? false;

  /// Get Google Maps navigation URL
  String get navigationUrl =>
      'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving';

  /// Get display label with sequence if available
  String get displayLabel {
    if (sequenceInTrip != null) {
      return '[$sequenceInTrip] $name';
    }
    return name;
  }

  /// Get shop distance (stub for future integration with location service)
  String get distanceDisplay => '---';

  /// Factory to create from API JSON response
  factory ShopModel.fromJson(Map<String, dynamic> json) {
    final wasteJson = json['expected_waste'] ?? json['waste_collection'];
    final WasteCollectionModel? waste =
        wasteJson != null ? WasteCollectionModel.fromJson(wasteJson) : null;

    return ShopModel(
      id: json['id']?.toString() ?? '',
      externalShopId: json['external_shop_id']?.toString() ?? json['external_id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Unknown Shop',
      address: json['address']?.toString() ?? '',
      lat: (json['lat'] ?? json['latitude'] ?? 0.0).toDouble(),
      lng: (json['lng'] ?? json['longitude'] ?? 0.0).toDouble(),
      contactName: json['contact_name']?.toString(),
      contactPhone: json['contact_phone']?.toString(),
      trackWaste: json['track_waste'] == true || json['track_waste'] == 1,
      isActive: json['is_active'] == true || json['is_active'] == 1,
      lastSyncedAt: json['last_synced_at'] != null
          ? DateTime.parse(json['last_synced_at'].toString())
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'].toString())
          : DateTime.now(),
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'].toString())
          : DateTime.now(),
      expectedWaste: waste,
      sequenceInTrip: json['sequence_in_trip'] != null
          ? int.tryParse(json['sequence_in_trip'].toString())
          : null,
    );
  }

  /// Factory to create from list API response (simplified format)
  factory ShopModel.fromListJson(Map<String, dynamic> json) {
    // Parse waste_summary into a mock WasteCollectionModel for UI display
    final wasteSummary = json['waste_summary'] as Map<String, dynamic>?;
    final hasPendingWaste = json['has_pending_waste'] == true;
    final isCollected = json['is_collected'] == true;

    WasteCollectionModel? waste;
    if (wasteSummary != null && hasPendingWaste) {
      // Create items list with summary data for display
      final itemsCount = wasteSummary['items_count'] as int? ?? 0;
      final totalDelivered = wasteSummary['total_delivered'] as int? ?? 0;
      final totalWaste = wasteSummary['total_waste'] as int? ?? 0;

      waste = WasteCollectionModel(
        id: '',
        shopId: json['id']?.toString() ?? '',
        shopName: json['name']?.toString() ?? '',
        collectionDate: DateTime.now(),
        collectedAt: isCollected ? DateTime.now() : null,
        items: [], // Items loaded on demand via getExpectedWaste
      );
      // Note: Summary data available via waste_summary, items fetched separately
    }

    return ShopModel(
      id: json['id']?.toString() ?? '',
      externalShopId: json['external_id']?.toString() ?? '',
      name: json['name']?.toString() ?? 'Unknown Shop',
      address: json['address']?.toString() ?? '',
      lat: (json['lat'] ?? 0.0).toDouble(),
      lng: (json['lng'] ?? 0.0).toDouble(),
      contactName: json['contact_name']?.toString(),
      contactPhone: json['contact_phone']?.toString(),
      trackWaste: true, // Only tracked shops are returned
      isActive: true,
      createdAt: DateTime.now(),
      updatedAt: DateTime.now(),
      expectedWaste: waste,
      wasteSummaryItemsCount: wasteSummary?['items_count'] as int?,
      wasteSummaryTotalDelivered: wasteSummary?['total_delivered'] as int?,
      wasteSummaryTotalWaste: wasteSummary?['total_waste'] as int?,
      wasteSummaryTotalSold: wasteSummary?['total_sold'] as int?,
      wasteSummaryExpiredCount: wasteSummary?['expired_items_count'] as int?,
      hasPendingWaste: hasPendingWaste,
      isWasteCollected: isCollected,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'external_shop_id': externalShopId,
      'name': name,
      'address': address,
      'lat': lat,
      'lng': lng,
      if (contactName != null) 'contact_name': contactName,
      if (contactPhone != null) 'contact_phone': contactPhone,
      'track_waste': trackWaste,
      'is_active': isActive,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }

  /// Create a copy with updated values
  ShopModel copyWith({
    String? id,
    String? externalShopId,
    String? name,
    String? address,
    double? lat,
    double? lng,
    String? contactName,
    String? contactPhone,
    bool? trackWaste,
    bool? isActive,
    DateTime? lastSyncedAt,
    DateTime? createdAt,
    DateTime? updatedAt,
    WasteCollectionModel? expectedWaste,
    int? sequenceInTrip,
    int? wasteSummaryItemsCount,
    int? wasteSummaryTotalDelivered,
    int? wasteSummaryTotalWaste,
    int? wasteSummaryTotalSold,
    int? wasteSummaryExpiredCount,
    bool? hasPendingWaste,
    bool? isWasteCollected,
  }) {
    return ShopModel(
      id: id ?? this.id,
      externalShopId: externalShopId ?? this.externalShopId,
      name: name ?? this.name,
      address: address ?? this.address,
      lat: lat ?? this.lat,
      lng: lng ?? this.lng,
      contactName: contactName ?? this.contactName,
      contactPhone: contactPhone ?? this.contactPhone,
      trackWaste: trackWaste ?? this.trackWaste,
      isActive: isActive ?? this.isActive,
      lastSyncedAt: lastSyncedAt ?? this.lastSyncedAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      expectedWaste: expectedWaste ?? this.expectedWaste,
      sequenceInTrip: sequenceInTrip ?? this.sequenceInTrip,
      wasteSummaryItemsCount: wasteSummaryItemsCount ?? this.wasteSummaryItemsCount,
      wasteSummaryTotalDelivered: wasteSummaryTotalDelivered ?? this.wasteSummaryTotalDelivered,
      wasteSummaryTotalWaste: wasteSummaryTotalWaste ?? this.wasteSummaryTotalWaste,
      wasteSummaryTotalSold: wasteSummaryTotalSold ?? this.wasteSummaryTotalSold,
      wasteSummaryExpiredCount: wasteSummaryExpiredCount ?? this.wasteSummaryExpiredCount,
      hasPendingWaste: hasPendingWaste ?? this.hasPendingWaste,
      isWasteCollected: isWasteCollected ?? this.isWasteCollected,
    );
  }

  /// Factory for mock data
  factory ShopModel.mock({
    required int order,
    required String name,
    double? lat,
    double? lng,
    bool trackWaste = true,
    WasteCollectionModel? expectedWaste,
  }) {
    return ShopModel(
      id: 'SHOP-$order',
      externalShopId: 'EXT-SHOP-$order',
      name: name,
      address: '$order Main Street, Amman',
      lat: lat ?? 31.9450 + (order * 0.01),
      lng: lng ?? 35.9100 + (order * 0.01),
      contactName: 'Owner $order',
      contactPhone: '+96279123456$order',
      trackWaste: trackWaste,
      isActive: true,
      createdAt: DateTime.now().subtract(Duration(days: 30)),
      updatedAt: DateTime.now(),
      expectedWaste: expectedWaste,
      sequenceInTrip: order,
    );
  }
}
