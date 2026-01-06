import 'package:flutter/material.dart';
import '../../../../shared/theme/app_theme.dart';

enum DestinationStatus {
  pending,
  arrived,
  completed,
  failed;

  String get label {
    switch (this) {
      case DestinationStatus.pending:
        return 'Pending';
      case DestinationStatus.arrived:
        return 'Arrived';
      case DestinationStatus.completed:
        return 'Completed';
      case DestinationStatus.failed:
        return 'Failed';
    }
  }

  String get labelAr {
    switch (this) {
      case DestinationStatus.pending:
        return 'قيد الانتظار';
      case DestinationStatus.arrived:
        return 'وصل';
      case DestinationStatus.completed:
        return 'مكتمل';
      case DestinationStatus.failed:
        return 'فشل';
    }
  }

  Color get color {
    switch (this) {
      case DestinationStatus.pending:
        return StatusColors.pending;
      case DestinationStatus.arrived:
        return StatusColors.arrived;
      case DestinationStatus.completed:
        return StatusColors.completed;
      case DestinationStatus.failed:
        return StatusColors.failed;
    }
  }

  /// Parse status from API string (e.g., 'pending', 'arrived', 'completed', 'failed', 'skipped')
  static DestinationStatus fromString(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return DestinationStatus.pending;
      case 'arrived':
        return DestinationStatus.arrived;
      case 'completed':
        return DestinationStatus.completed;
      case 'failed':
      case 'skipped':
        return DestinationStatus.failed;
      default:
        return DestinationStatus.pending;
    }
  }

  /// Convert to API string format
  String toApiString() {
    switch (this) {
      case DestinationStatus.pending:
        return 'pending';
      case DestinationStatus.arrived:
        return 'arrived';
      case DestinationStatus.completed:
        return 'completed';
      case DestinationStatus.failed:
        return 'failed';
    }
  }
}
