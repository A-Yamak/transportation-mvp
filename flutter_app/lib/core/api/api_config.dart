/// API Configuration
class ApiConfig {
  /// Base URL for the API
  /// TODO: Update with production URL
  static const String baseUrl = 'http://localhost:8000';

  /// API version prefix
  static const String apiPrefix = '/api/v1';

  /// Connection timeout
  static const Duration connectTimeout = Duration(seconds: 30);

  /// Receive timeout
  static const Duration receiveTimeout = Duration(seconds: 30);
}

/// API Endpoints
class ApiEndpoints {
  // Auth
  static const String login = '${ApiConfig.apiPrefix}/auth/login';
  static const String logout = '${ApiConfig.apiPrefix}/auth/logout';
  static const String refresh = '${ApiConfig.apiPrefix}/auth/refresh';
  static const String user = '${ApiConfig.apiPrefix}/auth/user';

  // Driver
  static const String todaysTrips = '${ApiConfig.apiPrefix}/driver/trips/today';
  static String tripDetails(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id';
  static String startTrip(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id/start';
  static String arriveAtDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/arrive/$destId';
  static String completeDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/complete/$destId';
  static String failDestination(String tripId, String destId) =>
      '${ApiConfig.apiPrefix}/driver/trips/$tripId/fail/$destId';
  static String completeTrip(String id) => '${ApiConfig.apiPrefix}/driver/trips/$id/complete';
  static String navigationUrl(String destId) =>
      '${ApiConfig.apiPrefix}/driver/navigation/$destId';
}
