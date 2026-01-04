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
}
