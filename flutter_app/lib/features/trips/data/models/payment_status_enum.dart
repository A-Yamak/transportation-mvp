import 'package:flutter/material.dart';
import '../../../../shared/theme/app_theme.dart';

enum PaymentStatus {
  pending,
  partial,
  full;

  String get label {
    switch (this) {
      case PaymentStatus.pending:
        return 'Pending';
      case PaymentStatus.partial:
        return 'Partial';
      case PaymentStatus.full:
        return 'Full';
    }
  }

  String get labelAr {
    switch (this) {
      case PaymentStatus.pending:
        return 'قيد الانتظار';
      case PaymentStatus.partial:
        return 'جزئي';
      case PaymentStatus.full:
        return 'مكتمل';
    }
  }

  Color get color {
    switch (this) {
      case PaymentStatus.pending:
        return StatusColors.pending;
      case PaymentStatus.partial:
        return StatusColors.arrived;
      case PaymentStatus.full:
        return StatusColors.completed;
    }
  }

  /// Parse status from API string
  static PaymentStatus fromString(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return PaymentStatus.pending;
      case 'partial':
        return PaymentStatus.partial;
      case 'full':
        return PaymentStatus.full;
      default:
        return PaymentStatus.pending;
    }
  }

  /// Convert to API string format
  String toApiString() {
    switch (this) {
      case PaymentStatus.pending:
        return 'pending';
      case PaymentStatus.partial:
        return 'partial';
      case PaymentStatus.full:
        return 'full';
    }
  }
}
