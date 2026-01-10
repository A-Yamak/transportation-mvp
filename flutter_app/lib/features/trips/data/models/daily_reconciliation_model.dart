import 'package:uuid/uuid.dart';
import 'shop_breakdown_model.dart';

enum ReconciliationStatus {
  pending,
  submitted,
  acknowledged;

  String get label {
    switch (this) {
      case ReconciliationStatus.pending:
        return 'Pending';
      case ReconciliationStatus.submitted:
        return 'Submitted';
      case ReconciliationStatus.acknowledged:
        return 'Acknowledged';
    }
  }

  String get labelAr {
    switch (this) {
      case ReconciliationStatus.pending:
        return 'قيد الانتظار';
      case ReconciliationStatus.submitted:
        return 'تم الإرسال';
      case ReconciliationStatus.acknowledged:
        return 'تم الاستقبال';
    }
  }

  static ReconciliationStatus fromString(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return ReconciliationStatus.pending;
      case 'submitted':
        return ReconciliationStatus.submitted;
      case 'acknowledged':
        return ReconciliationStatus.acknowledged;
      default:
        return ReconciliationStatus.pending;
    }
  }

  String toApiString() {
    switch (this) {
      case ReconciliationStatus.pending:
        return 'pending';
      case ReconciliationStatus.submitted:
        return 'submitted';
      case ReconciliationStatus.acknowledged:
        return 'acknowledged';
    }
  }
}

class DailyReconciliationModel {
  final String id;
  final DateTime reconciliationDate;
  final double totalExpected;
  final double totalCollected;
  final double totalCash;
  final double totalCliq;
  final int tripsCompleted;
  final int deliveriesCompleted;
  final double totalKmDriven;
  final List<ShopBreakdownModel> shopBreakdown;
  final ReconciliationStatus status;
  final String? notes;
  final DateTime createdAt;
  final DateTime? submittedAt;
  final DateTime? acknowledgedAt;

  DailyReconciliationModel({
    String? id,
    required this.reconciliationDate,
    required this.totalExpected,
    required this.totalCollected,
    required this.totalCash,
    required this.totalCliq,
    required this.tripsCompleted,
    required this.deliveriesCompleted,
    required this.totalKmDriven,
    required this.shopBreakdown,
    ReconciliationStatus status = ReconciliationStatus.pending,
    this.notes,
    DateTime? createdAt,
    this.submittedAt,
    this.acknowledgedAt,
  })  : id = id ?? const Uuid().v4(),
        status = status,
        createdAt = createdAt ?? DateTime.now();

  /// Computed: collection rate percentage (total collected / total expected)
  double get collectionRate {
    if (totalExpected == 0) return 0.0;
    return (totalCollected / totalExpected) * 100;
  }

  /// Computed: cash percentage of total collected
  double get cashPercentage {
    if (totalCollected == 0) return 0.0;
    return (totalCash / totalCollected) * 100;
  }

  /// Computed: CliQ percentage of total collected
  double get cliqPercentage {
    if (totalCollected == 0) return 0.0;
    return (totalCliq / totalCollected) * 100;
  }

  /// Computed: total shortage
  double get totalShortage => totalExpected - totalCollected;

  /// Computed: shortage percentage
  double get shortagePercentage {
    if (totalExpected == 0) return 0.0;
    return (totalShortage / totalExpected) * 100;
  }

  /// Computed: whether fully collected
  bool get isFullyCollected => totalCollected >= totalExpected;

  /// Computed: number of shops with full collection
  int get shopsFullyCollected =>
      shopBreakdown.where((s) => s.isFullyCollected).length;

  /// Computed: number of shops with partial collection
  int get shopsPartiallyCollected =>
      shopBreakdown.where((s) => s.isPartiallyCollected).length;

  /// Computed: number of shops with no collection
  int get shopsNotCollected =>
      shopBreakdown.where((s) => s.amountCollected == 0).length;

  /// Factory constructor to parse from API JSON
  factory DailyReconciliationModel.fromJson(Map<String, dynamic> json) {
    final shopBreakdowns = (json['shop_breakdown'] as List<dynamic>?)
            ?.map((e) => ShopBreakdownModel.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return DailyReconciliationModel(
      id: json['id'].toString(),
      reconciliationDate: DateTime.parse(json['reconciliation_date'].toString()),
      totalExpected: (json['total_expected'] ?? 0.0).toDouble(),
      totalCollected: (json['total_collected'] ?? 0.0).toDouble(),
      totalCash: (json['total_cash'] ?? 0.0).toDouble(),
      totalCliq: (json['total_cliq'] ?? 0.0).toDouble(),
      tripsCompleted: (json['trips_completed'] ?? 0) as int,
      deliveriesCompleted: (json['deliveries_completed'] ?? 0) as int,
      totalKmDriven: (json['total_km_driven'] ?? 0.0).toDouble(),
      shopBreakdown: shopBreakdowns,
      status: ReconciliationStatus.fromString(
        json['status']?.toString() ?? 'pending',
      ),
      notes: json['notes'],
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'].toString())
          : null,
      submittedAt: json['submitted_at'] != null
          ? DateTime.parse(json['submitted_at'].toString())
          : null,
      acknowledgedAt: json['acknowledged_at'] != null
          ? DateTime.parse(json['acknowledged_at'].toString())
          : null,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'reconciliation_date': reconciliationDate.toIso8601String(),
      'total_expected': totalExpected,
      'total_collected': totalCollected,
      'total_cash': totalCash,
      'total_cliq': totalCliq,
      'trips_completed': tripsCompleted,
      'deliveries_completed': deliveriesCompleted,
      'total_km_driven': totalKmDriven,
      'shop_breakdown': shopBreakdown.map((s) => s.toJson()).toList(),
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }

  /// Create a copy with optional field replacements
  DailyReconciliationModel copyWith({
    String? id,
    DateTime? reconciliationDate,
    double? totalExpected,
    double? totalCollected,
    double? totalCash,
    double? totalCliq,
    int? tripsCompleted,
    int? deliveriesCompleted,
    double? totalKmDriven,
    List<ShopBreakdownModel>? shopBreakdown,
    ReconciliationStatus? status,
    String? notes,
    DateTime? createdAt,
    DateTime? submittedAt,
    DateTime? acknowledgedAt,
  }) {
    return DailyReconciliationModel(
      id: id ?? this.id,
      reconciliationDate: reconciliationDate ?? this.reconciliationDate,
      totalExpected: totalExpected ?? this.totalExpected,
      totalCollected: totalCollected ?? this.totalCollected,
      totalCash: totalCash ?? this.totalCash,
      totalCliq: totalCliq ?? this.totalCliq,
      tripsCompleted: tripsCompleted ?? this.tripsCompleted,
      deliveriesCompleted: deliveriesCompleted ?? this.deliveriesCompleted,
      totalKmDriven: totalKmDriven ?? this.totalKmDriven,
      shopBreakdown: shopBreakdown ?? this.shopBreakdown,
      status: status ?? this.status,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
      submittedAt: submittedAt ?? this.submittedAt,
      acknowledgedAt: acknowledgedAt ?? this.acknowledgedAt,
    );
  }

  /// Create a mock instance for testing
  factory DailyReconciliationModel.mock({
    double totalExpected = 5000.0,
    double totalCollected = 4800.0,
    double totalCash = 3000.0,
    double totalCliq = 1800.0,
    int tripsCompleted = 5,
    int deliveriesCompleted = 15,
    double totalKmDriven = 45.5,
    List<ShopBreakdownModel>? shopBreakdown,
  }) {
    return DailyReconciliationModel(
      id: const Uuid().v4(),
      reconciliationDate: DateTime.now(),
      totalExpected: totalExpected,
      totalCollected: totalCollected,
      totalCash: totalCash,
      totalCliq: totalCliq,
      tripsCompleted: tripsCompleted,
      deliveriesCompleted: deliveriesCompleted,
      totalKmDriven: totalKmDriven,
      shopBreakdown: shopBreakdown ??
          [
            ShopBreakdownModel.mock(
              shopId: 'SHOP-001',
              shopName: 'Ahmad Shop',
              amountExpected: 1500.0,
              amountCollected: 1500.0,
            ),
            ShopBreakdownModel.mock(
              shopId: 'SHOP-002',
              shopName: 'Omar Store',
              amountExpected: 2000.0,
              amountCollected: 1900.0,
            ),
            ShopBreakdownModel.mock(
              shopId: 'SHOP-003',
              shopName: 'Ali Market',
              amountExpected: 1500.0,
              amountCollected: 1400.0,
            ),
          ],
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is DailyReconciliationModel &&
          runtimeType == other.runtimeType &&
          id == other.id &&
          reconciliationDate == other.reconciliationDate &&
          totalExpected == other.totalExpected &&
          totalCollected == other.totalCollected;

  @override
  int get hashCode =>
      id.hashCode ^
      reconciliationDate.hashCode ^
      totalExpected.hashCode ^
      totalCollected.hashCode;

  @override
  String toString() =>
      'DailyReconciliationModel(date: ${reconciliationDate.toIso8601String()}, collected: $totalCollected/$totalExpected, rate: ${collectionRate.toStringAsFixed(1)}%, shops: ${shopBreakdown.length})';
}
