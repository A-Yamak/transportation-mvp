enum ShortageReason {
  customerAbsent,
  insufficientFunds,
  customerRefused,
  partialDelivery,
  deliveryError,
  other;

  String get label {
    switch (this) {
      case ShortageReason.customerAbsent:
        return 'Customer Absent';
      case ShortageReason.insufficientFunds:
        return 'Insufficient Funds';
      case ShortageReason.customerRefused:
        return 'Customer Refused';
      case ShortageReason.partialDelivery:
        return 'Partial Delivery';
      case ShortageReason.deliveryError:
        return 'Delivery Error';
      case ShortageReason.other:
        return 'Other';
    }
  }

  String get labelAr {
    switch (this) {
      case ShortageReason.customerAbsent:
        return 'العميل غير موجود';
      case ShortageReason.insufficientFunds:
        return 'أموال غير كافية';
      case ShortageReason.customerRefused:
        return 'العميل رفض';
      case ShortageReason.partialDelivery:
        return 'تسليم جزئي';
      case ShortageReason.deliveryError:
        return 'خطأ في التسليم';
      case ShortageReason.other:
        return 'آخر';
    }
  }

  /// Parse reason from API string
  static ShortageReason fromString(String reason) {
    switch (reason.toLowerCase()) {
      case 'customer_absent':
        return ShortageReason.customerAbsent;
      case 'insufficient_funds':
        return ShortageReason.insufficientFunds;
      case 'customer_refused':
        return ShortageReason.customerRefused;
      case 'partial_delivery':
        return ShortageReason.partialDelivery;
      case 'delivery_error':
        return ShortageReason.deliveryError;
      case 'other':
        return ShortageReason.other;
      default:
        return ShortageReason.other;
    }
  }

  /// Convert to API string format
  String toApiString() {
    switch (this) {
      case ShortageReason.customerAbsent:
        return 'customer_absent';
      case ShortageReason.insufficientFunds:
        return 'insufficient_funds';
      case ShortageReason.customerRefused:
        return 'customer_refused';
      case ShortageReason.partialDelivery:
        return 'partial_delivery';
      case ShortageReason.deliveryError:
        return 'delivery_error';
      case ShortageReason.other:
        return 'other';
    }
  }
}
