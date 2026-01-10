import 'package:uuid/uuid.dart';
import 'payment_method_enum.dart';
import 'payment_status_enum.dart';
import 'shortage_reason_enum.dart';

class PaymentCollectionModel {
  final String id;
  final String destinationId;
  final double amountExpected;
  final double amountCollected;
  final PaymentMethod paymentMethod;
  final PaymentStatus paymentStatus;
  final String? cliqReference;
  final double? shortageAmount;
  final double? shortagePercentage;
  final ShortageReason? shortageReason;
  final String? notes;
  final DateTime createdAt;
  final DateTime? updatedAt;

  PaymentCollectionModel({
    String? id,
    required this.destinationId,
    required this.amountExpected,
    required this.amountCollected,
    required this.paymentMethod,
    required this.paymentStatus,
    this.cliqReference,
    this.shortageAmount,
    this.shortagePercentage,
    this.shortageReason,
    this.notes,
    DateTime? createdAt,
    this.updatedAt,
  })  : id = id ?? const Uuid().v4(),
        createdAt = createdAt ?? DateTime.now();

  /// Computed: whether payment is fully collected
  bool get isFullyCollected => paymentStatus == PaymentStatus.full;

  /// Computed: whether there's a shortage
  bool get hasShortage => shortageAmount != null && shortageAmount! > 0;

  /// Computed: shortage percentage display
  String get shortagePercentageDisplay =>
      shortagePercentage?.toStringAsFixed(2) ?? '0.00';

  /// Factory constructor to parse from API JSON
  factory PaymentCollectionModel.fromJson(Map<String, dynamic> json) {
    return PaymentCollectionModel(
      id: json['id'].toString(),
      destinationId: json['destination_id'].toString(),
      amountExpected: (json['amount_expected'] ?? 0.0).toDouble(),
      amountCollected: (json['amount_collected'] ?? 0.0).toDouble(),
      paymentMethod: PaymentMethod.fromString(
        json['payment_method']?.toString() ?? 'cash',
      ),
      paymentStatus: PaymentStatus.fromString(
        json['payment_status']?.toString() ?? 'pending',
      ),
      cliqReference: json['cliq_reference'],
      shortageAmount: json['shortage_amount'] != null
          ? (json['shortage_amount'] as num).toDouble()
          : null,
      shortagePercentage: json['shortage_percentage'] != null
          ? (json['shortage_percentage'] as num).toDouble()
          : null,
      shortageReason: json['shortage_reason'] != null
          ? ShortageReason.fromString(json['shortage_reason'].toString())
          : null,
      notes: json['notes'],
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'].toString())
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'].toString())
          : null,
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'destination_id': destinationId,
      'amount_collected': amountCollected,
      'payment_method': paymentMethod.toApiString(),
      if (cliqReference != null) 'cliq_reference': cliqReference,
      if (shortageReason != null) 'shortage_reason': shortageReason?.toApiString(),
      if (notes != null && notes!.isNotEmpty) 'notes': notes,
    };
  }

  /// Create a copy with optional field replacements
  PaymentCollectionModel copyWith({
    String? id,
    String? destinationId,
    double? amountExpected,
    double? amountCollected,
    PaymentMethod? paymentMethod,
    PaymentStatus? paymentStatus,
    String? cliqReference,
    double? shortageAmount,
    double? shortagePercentage,
    ShortageReason? shortageReason,
    String? notes,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return PaymentCollectionModel(
      id: id ?? this.id,
      destinationId: destinationId ?? this.destinationId,
      amountExpected: amountExpected ?? this.amountExpected,
      amountCollected: amountCollected ?? this.amountCollected,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      cliqReference: cliqReference ?? this.cliqReference,
      shortageAmount: shortageAmount ?? this.shortageAmount,
      shortagePercentage: shortagePercentage ?? this.shortagePercentage,
      shortageReason: shortageReason ?? this.shortageReason,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  /// Create a mock instance for testing
  factory PaymentCollectionModel.mock({
    String? id,
    String? destinationId,
    double amountExpected = 1000.0,
    double amountCollected = 1000.0,
    PaymentMethod paymentMethod = PaymentMethod.cash,
    PaymentStatus paymentStatus = PaymentStatus.full,
    String? cliqReference,
    ShortageReason? shortageReason,
  }) {
    final collected = amountCollected;
    final expected = amountExpected;
    final shortage = expected > collected ? expected - collected : null;
    final shortagePercent =
        shortage != null ? (shortage / expected) * 100 : null;

    return PaymentCollectionModel(
      id: id ?? const Uuid().v4(),
      destinationId: destinationId ?? const Uuid().v4(),
      amountExpected: expected,
      amountCollected: collected,
      paymentMethod: paymentMethod,
      paymentStatus: paymentStatus,
      cliqReference: cliqReference,
      shortageAmount: shortage,
      shortagePercentage: shortagePercent,
      shortageReason: shortageReason,
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is PaymentCollectionModel &&
          runtimeType == other.runtimeType &&
          id == other.id &&
          destinationId == other.destinationId &&
          amountExpected == other.amountExpected &&
          amountCollected == other.amountCollected &&
          paymentMethod == other.paymentMethod &&
          paymentStatus == other.paymentStatus;

  @override
  int get hashCode =>
      id.hashCode ^
      destinationId.hashCode ^
      amountExpected.hashCode ^
      amountCollected.hashCode ^
      paymentMethod.hashCode ^
      paymentStatus.hashCode;

  @override
  String toString() =>
      'PaymentCollectionModel(id: $id, destination: $destinationId, collected: $amountCollected/$amountExpected, method: ${paymentMethod.label})';
}
