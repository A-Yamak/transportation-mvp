import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/api/api_client.dart';
import 'models/driver_profile_model.dart';
import 'models/driver_stats_model.dart';
import 'models/trip_history_model.dart';

/// Profile Repository Provider
final profileRepositoryProvider = Provider<ProfileRepository>((ref) {
  return ProfileRepository(ref.watch(apiClientProvider));
});

/// Repository for driver profile, stats, and history APIs
class ProfileRepository {
  final ApiClient _client;

  ProfileRepository(this._client);

  /// Get driver profile with vehicle info
  Future<DriverProfileModel> getProfile() async {
    final response = await _client.get<Map<String, dynamic>>(
      '/api/v1/driver/profile',
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return DriverProfileModel.fromJson(data);
  }

  /// Update driver profile (phone, name)
  Future<DriverProfileModel> updateProfile({
    String? name,
    String? phone,
  }) async {
    final updates = <String, dynamic>{};
    if (name != null) updates['name'] = name;
    if (phone != null) updates['phone'] = phone;

    final response = await _client.patch<Map<String, dynamic>>(
      '/api/v1/driver/profile',
      data: updates,
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return DriverProfileModel.fromJson(data);
  }

  /// Upload profile photo
  Future<String> uploadProfilePhoto(String filePath) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(filePath),
    });

    final dio = Dio(BaseOptions(
      baseUrl: _client.toString(), // This won't work, need to fix
      headers: {'Accept': 'application/json'},
    ));

    // For now, just return the path - implement properly with actual upload
    return filePath;
  }

  /// Get driver statistics
  Future<DriverStatsModel> getStats() async {
    final response = await _client.get<Map<String, dynamic>>(
      '/api/v1/driver/stats',
    );

    final data = response.data!['data'] as Map<String, dynamic>;
    return DriverStatsModel.fromJson(data);
  }

  /// Get trip history with pagination
  Future<TripHistoryResponse> getTripHistory({
    int page = 1,
    String? from,
    String? to,
    String? status,
  }) async {
    final queryParams = <String, dynamic>{'page': page};
    if (from != null) queryParams['from'] = from;
    if (to != null) queryParams['to'] = to;
    if (status != null) queryParams['status'] = status;

    final response = await _client.get<Map<String, dynamic>>(
      '/api/v1/driver/trips/history',
      queryParameters: queryParams,
    );

    return TripHistoryResponse.fromJson(response.data!);
  }
}
