// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Driver App';

  @override
  String get login => 'Login';

  @override
  String get logout => 'Logout';

  @override
  String get email => 'Email';

  @override
  String get password => 'Password';

  @override
  String get forgotPassword => 'Forgot Password?';

  @override
  String get loginButton => 'Login';

  @override
  String get loggingIn => 'Logging in...';

  @override
  String get todaysTrips => 'Today\'s Trips';

  @override
  String get noTripsToday => 'No trips today';

  @override
  String get trip => 'Trip';

  @override
  String get trips => 'Trips';

  @override
  String get destinations => 'Destinations';

  @override
  String get destination => 'Destination';

  @override
  String get startTrip => 'Start Trip';

  @override
  String get continueTrip => 'Continue';

  @override
  String get endTrip => 'End Trip';

  @override
  String get tripStarted => 'Trip Started';

  @override
  String get tripCompleted => 'Trip Completed';

  @override
  String get navigate => 'Navigate';

  @override
  String get markArrived => 'Mark Arrived';

  @override
  String get markComplete => 'Mark Complete';

  @override
  String get markFailed => 'Mark Failed';

  @override
  String get arrived => 'Arrived';

  @override
  String get completed => 'Completed';

  @override
  String get failed => 'Failed';

  @override
  String get pending => 'Pending';

  @override
  String get inProgress => 'In Progress';

  @override
  String get notStarted => 'Not Started';

  @override
  String get cancelled => 'Cancelled';

  @override
  String get kilometers => 'Kilometers';

  @override
  String get km => 'km';

  @override
  String get estimatedKm => 'Estimated Distance';

  @override
  String get actualKm => 'Actual Distance';

  @override
  String get failureReasonNotHome => 'Customer Not Home';

  @override
  String get failureReasonRefused => 'Refused Delivery';

  @override
  String get failureReasonWrongAddress => 'Wrong Address';

  @override
  String get failureReasonInaccessible => 'Location Inaccessible';

  @override
  String get failureReasonOther => 'Other';

  @override
  String get selectFailureReason => 'Select Failure Reason';

  @override
  String get additionalNotes => 'Additional Notes';

  @override
  String get recipientName => 'Recipient Name';

  @override
  String get deliveryNotes => 'Delivery Notes';

  @override
  String get confirmDelivery => 'Confirm Delivery';

  @override
  String get settings => 'Settings';

  @override
  String get language => 'Language';

  @override
  String get arabic => 'العربية';

  @override
  String get english => 'English';

  @override
  String get error => 'Error';

  @override
  String get tryAgain => 'Try Again';

  @override
  String get networkError => 'Network Error';

  @override
  String get unknownError => 'An unexpected error occurred';

  @override
  String get invalidCredentials => 'Invalid credentials';

  @override
  String get loading => 'Loading...';

  @override
  String get pullToRefresh => 'Pull to refresh';

  @override
  String get totalTrips => 'Total Trips';

  @override
  String get completedTrips => 'Completed Trips';

  @override
  String get totalKmDriven => 'Total KM Driven';

  @override
  String get welcome => 'Welcome';

  @override
  String get goodMorning => 'Good Morning';

  @override
  String get goodAfternoon => 'Good Afternoon';

  @override
  String get goodEvening => 'Good Evening';

  @override
  String get arrivedSuccess => 'Marked as arrived';

  @override
  String get completedSuccess => 'Delivery completed!';

  @override
  String navigateTo(Object address) {
    return 'Navigate to: $address';
  }

  @override
  String get tripId => 'Trip ID';
}
