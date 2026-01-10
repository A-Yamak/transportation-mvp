enum PaymentMethod {
  cash,
  cliqNow,
  cliqLater;

  String get label {
    switch (this) {
      case PaymentMethod.cash:
        return 'Cash';
      case PaymentMethod.cliqNow:
        return 'CliQ Now';
      case PaymentMethod.cliqLater:
        return 'CliQ Later';
    }
  }

  String get labelAr {
    switch (this) {
      case PaymentMethod.cash:
        return 'نقد';
      case PaymentMethod.cliqNow:
        return 'كليق الآن';
      case PaymentMethod.cliqLater:
        return 'كليق لاحقاً';
    }
  }

  /// Parse payment method from API string (e.g., 'cash', 'cliq_now', 'cliq_later')
  static PaymentMethod fromString(String method) {
    switch (method.toLowerCase()) {
      case 'cash':
        return PaymentMethod.cash;
      case 'cliq_now':
        return PaymentMethod.cliqNow;
      case 'cliq_later':
        return PaymentMethod.cliqLater;
      default:
        return PaymentMethod.cash;
    }
  }

  /// Convert to API string format
  String toApiString() {
    switch (this) {
      case PaymentMethod.cash:
        return 'cash';
      case PaymentMethod.cliqNow:
        return 'cliq_now';
      case PaymentMethod.cliqLater:
        return 'cliq_later';
    }
  }

  /// Requires CliQ reference number
  bool get requiresReference => this != PaymentMethod.cash;
}
