import 'package:uuid/uuid.dart';
import 'payment_method_enum.dart';
import 'payment_status_enum.dart';

class ShopBreakdownModel {
  final String shopId;
  final String shopName;
  final double amountExpected;
  final double amountCollected;
  final PaymentMethod primaryPaymentMethod;
  final PaymentStatus paymentStatus; // full, partial, none
  final double? shortageAmount;
  final DateTime createdAt;

  ShopBreakdownModel({
    required this.shopId,
    required this.shopName,
    required this.amountExpected,
    required this.amountCollected,
    required this.primaryPaymentMethod,
    required this.paymentStatus,
    this.shortageAmount,
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  /// Computed: collection rate percentage
  double get collectionRate {
    if (amountExpected == 0) return 0.0;
    return (amountCollected / amountExpected) * 100;
  }

  /// Computed: whether fully collected
  bool get isFullyCollected => paymentStatus == PaymentStatus.full;

  /// Computed: whether partially collected
  bool get isPartiallyCollected => paymentStatus == PaymentStatus.partial;

  /// Computed: whether has shortage
  bool get hasShortage => shortageAmount != null && shortageAmount! > 0;

  /// Computed: shortage percentage
  double get shortagePercentage {
    if (amountExpected == 0) return 0.0;
    final shortage = shortageAmount ?? 0.0;
    return (shortage / amountExpected) * 100;
  }

  /// Factory constructor to parse from API JSON
  factory ShopBreakdownModel.fromJson(Map<String, dynamic> json) {
    final expected = (json['amount_expected'] ?? 0.0).toDouble();
    final collected = (json['amount_collected'] ?? 0.0).toDouble();
    final shortage = expected > collected ? expected - collected : 0.0;

    return ShopBreakdownModel(
      shopId: json['shop_id'].toString(),
      shopName: json['shop_name'].toString(),
      amountExpected: expected,
      amountCollected: collected,
      primaryPaymentMethod: PaymentMethod.fromString(
        json['primary_payment_method']?.toString() ?? 'cash',
      ),
      paymentStatus: PaymentStatus.fromString(
        json['payment_status']?.toString() ?? 'pending',
      ),
      shortageAmount: shortage > 0 ? shortage : null,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'].toString())
          : null,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'shop_id': shopId,
      'shop_name': shopName,
      'amount_expected': amountExpected,
      'amount_collected': amountCollected,
      'primary_payment_method': primaryPaymentMethod.toApiString(),
      'payment_status': paymentStatus.toApiString(),
    };
  }

  /// Create a copy with optional field replacements
  ShopBreakdownModel copyWith({
    String? shopId,
    String? shopName,
    double? amountExpected,
    double? amountCollected,
    PaymentMethod? primaryPaymentMethod,
    PaymentStatus? paymentStatus,
    double? shortageAmount,
    DateTime? createdAt,
  }) {
    return ShopBreakdownModel(
      shopId: shopId ?? this.shopId,
      shopName: shopName ?? this.shopName,
      amountExpected: amountExpected ?? this.amountExpected,
      amountCollected: amountCollected ?? this.amountCollected,
      primaryPaymentMethod:
          primaryPaymentMethod ?? this.primaryPaymentMethod,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      shortageAmount: shortageAmount ?? this.shortageAmount,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  /// Create a mock instance for testing
  factory ShopBreakdownModel.mock({
    String? shopId,
    String shopName = 'Test Shop',
    double amountExpected = 1000.0,
    double amountCollected = 1000.0,
    PaymentMethod primaryPaymentMethod = PaymentMethod.cash,
    PaymentStatus paymentStatus = PaymentStatus.full,
  }) {
    final expected = amountExpected;
    final collected = amountCollected;
    final shortage = expected > collected ? expected - collected : null;

    return ShopBreakdownModel(
      shopId: shopId ?? 'SHOP-${const Uuid().v4().substring(0, 8)}',
      shopName: shopName,
      amountExpected: expected,
      amountCollected: collected,
      primaryPaymentMethod: primaryPaymentMethod,
      paymentStatus: paymentStatus,
      shortageAmount: shortage,
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is ShopBreakdownModel &&
          runtimeType == other.runtimeType &&
          shopId == other.shopId &&
          amountExpected == other.amountExpected &&
          amountCollected == other.amountCollected;

  @override
  int get hashCode =>
      shopId.hashCode ^
      amountExpected.hashCode ^
      amountCollected.hashCode;

  @override
  String toString() =>
      'ShopBreakdownModel(shop: $shopName, expected: $amountExpected, collected: $amountCollected, rate: ${collectionRate.toStringAsFixed(1)}%)';
}
