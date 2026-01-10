import 'package:flutter/material.dart';
import '../../../../shared/theme/app_theme.dart';

class TupperwareBalanceModel {
  final String productType; // e.g., 'boxes', 'trays', 'bags'
  final int currentBalance;
  final int thresholdWarning; // Yellow indicator threshold
  final int thresholdCritical; // Red indicator threshold
  final double depositPerUnit; // Deposit owed calculation

  TupperwareBalanceModel({
    required this.productType,
    required this.currentBalance,
    required this.thresholdWarning,
    required this.thresholdCritical,
    required this.depositPerUnit,
  });

  /// Computed: deposit amount owed (balance × deposit_per_unit)
  double get depositOwed => currentBalance * depositPerUnit;

  /// Computed: warning level indicator color
  Color get balanceColor {
    if (currentBalance >= thresholdCritical) {
      return StatusColors.failed; // Red
    } else if (currentBalance >= thresholdWarning) {
      return StatusColors.arrived; // Yellow
    } else {
      return StatusColors.completed; // Green
    }
  }

  /// Computed: human-readable balance status
  String get balanceStatus {
    if (currentBalance >= thresholdCritical) {
      return 'Critical';
    } else if (currentBalance >= thresholdWarning) {
      return 'Warning';
    } else {
      return 'Normal';
    }
  }

  /// Computed: check if balance is above warning threshold
  bool get isWarning => currentBalance >= thresholdWarning;

  /// Computed: check if balance is critical
  bool get isCritical => currentBalance >= thresholdCritical;

  /// Validate pickup quantity doesn't exceed current balance
  bool canPickup(int quantity) => quantity <= currentBalance && quantity >= 0;

  /// Calculate new balance after pickup
  int getBalanceAfterPickup(int quantity) {
    if (!canPickup(quantity)) {
      return currentBalance;
    }
    return currentBalance - quantity;
  }

  /// Factory constructor to parse from API JSON
  factory TupperwareBalanceModel.fromJson(Map<String, dynamic> json) {
    return TupperwareBalanceModel(
      productType: json['product_type'].toString(),
      currentBalance: (json['current_balance'] ?? 0) as int,
      thresholdWarning: (json['threshold_warning'] ?? 30) as int,
      thresholdCritical: (json['threshold_critical'] ?? 50) as int,
      depositPerUnit: (json['deposit_per_unit'] ?? 0.0).toDouble(),
    );
  }

  /// Convert to JSON for API requests
  Map<String, dynamic> toJson() {
    return {
      'product_type': productType,
      'current_balance': currentBalance,
      'threshold_warning': thresholdWarning,
      'threshold_critical': thresholdCritical,
      'deposit_per_unit': depositPerUnit,
    };
  }

  /// Create a copy with optional field replacements
  TupperwareBalanceModel copyWith({
    String? productType,
    int? currentBalance,
    int? thresholdWarning,
    int? thresholdCritical,
    double? depositPerUnit,
  }) {
    return TupperwareBalanceModel(
      productType: productType ?? this.productType,
      currentBalance: currentBalance ?? this.currentBalance,
      thresholdWarning: thresholdWarning ?? this.thresholdWarning,
      thresholdCritical: thresholdCritical ?? this.thresholdCritical,
      depositPerUnit: depositPerUnit ?? this.depositPerUnit,
    );
  }

  /// Create a mock instance for testing
  factory TupperwareBalanceModel.mock({
    String productType = 'boxes',
    int currentBalance = 25,
    int thresholdWarning = 30,
    int thresholdCritical = 50,
    double depositPerUnit = 5.0,
  }) {
    return TupperwareBalanceModel(
      productType: productType,
      currentBalance: currentBalance,
      thresholdWarning: thresholdWarning,
      thresholdCritical: thresholdCritical,
      depositPerUnit: depositPerUnit,
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is TupperwareBalanceModel &&
          runtimeType == other.runtimeType &&
          productType == other.productType &&
          currentBalance == other.currentBalance &&
          thresholdWarning == other.thresholdWarning &&
          thresholdCritical == other.thresholdCritical;

  @override
  int get hashCode =>
      productType.hashCode ^
      currentBalance.hashCode ^
      thresholdWarning.hashCode ^
      thresholdCritical.hashCode;

  @override
  String toString() =>
      'TupperwareBalanceModel(type: $productType, balance: $currentBalance, deposit: ${depositOwed.toStringAsFixed(2)})';
}
