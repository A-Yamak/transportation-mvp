/// API Configuration
class ApiConfig {
  /// Base URL for the API
  /// Uses dart-define for production: --dart-define=API_BASE_URL=https://api.example.com
  /// Defaults to localhost for development
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000', // Android emulator -> host machine
  );

  /// API version prefix
  static const String apiPrefix = '/api/v1';

  /// Connection timeout
  static const Duration connectTimeout = Duration(seconds: 30);

  /// Receive timeout
  static const Duration receiveTimeout = Duration(seconds: 30);

  /// Check if running in production mode
  static bool get isProduction =>
      const String.fromEnvironment('FLUTTER_ENV') == 'production';

  /// Sentry DSN for error tracking
  /// Uses dart-define for production: --dart-define=SENTRY_DSN=https://xxx@sentry.io/xxx
  static const String sentryDsn = String.fromEnvironment(
    'SENTRY_DSN',
    defaultValue: '', // Empty disables Sentry in development
  );

  /// Check if Sentry is enabled
  static bool get sentryEnabled => sentryDsn.isNotEmpty;
}

/// API Endpoints
class ApiEndpoints {
  // Auth
  static const String login = '${ApiConfig.apiPrefix}/auth/login';
  static const String logout = '${ApiConfig.apiPrefix}/auth/logout';
  static const String refresh = '${ApiConfig.apiPrefix}/auth/refresh';
  static const String user = '${ApiConfig.apiPrefix}/auth/user';

  // Driver - Trip endpoints
  static const String todaysTrips = '${ApiConfig.apiPrefix}/driver/trips/today';
  static String tripDetails(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id';
  static String startTrip(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id/start';
  static String completeTrip(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id/complete';

  // Driver - Destination endpoints (nested under trips)
  static String arriveAtDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/destinations/$destId/arrive';
  static String completeDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/destinations/$destId/complete';
  static String failDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/destinations/$destId/fail';
  static String navigationUrl(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/destinations/$destId/navigate';
}
