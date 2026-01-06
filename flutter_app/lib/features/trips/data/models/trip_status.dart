enum TripStatus {
  notStarted,
  inProgress,
  completed,
  cancelled;

  String get label {
    switch (this) {
      case TripStatus.notStarted:
        return 'Not Started';
      case TripStatus.inProgress:
        return 'In Progress';
      case TripStatus.completed:
        return 'Completed';
      case TripStatus.cancelled:
        return 'Cancelled';
    }
  }

  String get labelAr {
    switch (this) {
      case TripStatus.notStarted:
        return 'لم يبدأ';
      case TripStatus.inProgress:
        return 'جاري التنفيذ';
      case TripStatus.completed:
        return 'مكتمل';
      case TripStatus.cancelled:
        return 'ملغي';
    }
  }

  bool get canStart => this == TripStatus.notStarted;
  bool get canComplete => this == TripStatus.inProgress;

  /// Parse status from API string (e.g., 'pending', 'in_progress', 'completed', 'cancelled')
  static TripStatus fromString(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
      case 'not_started':
        return TripStatus.notStarted;
      case 'in_progress':
        return TripStatus.inProgress;
      case 'completed':
        return TripStatus.completed;
      case 'cancelled':
        return TripStatus.cancelled;
      default:
        return TripStatus.notStarted;
    }
  }

  /// Convert to API string format
  String toApiString() {
    switch (this) {
      case TripStatus.notStarted:
        return 'pending';
      case TripStatus.inProgress:
        return 'in_progress';
      case TripStatus.completed:
        return 'completed';
      case TripStatus.cancelled:
        return 'cancelled';
    }
  }
}
