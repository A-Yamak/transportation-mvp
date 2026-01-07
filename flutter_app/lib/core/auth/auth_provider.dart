import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../api/api_client.dart';
import '../api/api_config.dart';
import '../api/api_exceptions.dart';

/// User model
class User {
  final String id;
  final String name;
  final String email;

  User({
    required this.id,
    required this.name,
    required this.email,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'].toString(),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
    );
  }
}

/// Auth state
class AuthState {
  final User? user;
  final bool isLoading;
  final bool isAuthenticated;
  final String? error;

  const AuthState({
    this.user,
    this.isLoading = false,
    this.isAuthenticated = false,
    this.error,
  });

  AuthState copyWith({
    User? user,
    bool? isLoading,
    bool? isAuthenticated,
    String? error,
  }) {
    return AuthState(
      user: user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      error: error,
    );
  }
}

/// Auth state notifier
class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _apiClient;
  final FlutterSecureStorage _storage;

  AuthNotifier(this._apiClient, this._storage)
      : super(const AuthState()) {
    _checkAuth();
  }

  /// Check if user is already authenticated
  Future<void> _checkAuth() async {
    state = state.copyWith(isLoading: true);

    final token = await _storage.read(key: 'access_token');
    if (token != null) {
      try {
        final response = await _apiClient.get(ApiEndpoints.user);
        final user = User.fromJson(response.data['data'] ?? response.data);
        state = AuthState(
          user: user,
          isAuthenticated: true,
          isLoading: false,
        );
      } catch (e) {
        // Token invalid, clear it
        await _storage.delete(key: 'access_token');
        await _storage.delete(key: 'refresh_token');
        state = const AuthState(isLoading: false);
      }
    } else {
      state = const AuthState(isLoading: false);
    }
  }

  /// Login with email and password
  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final response = await _apiClient.post(
        ApiEndpoints.login,
        data: {
          'email': email,
          'password': password,
        },
      );

      final data = response.data;
      final accessToken = data['access_token'] as String?;
      final refreshToken = data['refresh_token'] as String?;

      if (accessToken == null) {
        state = state.copyWith(
          isLoading: false,
          error: 'فشل تسجيل الدخول - لم يتم استلام رمز الوصول',
        );
        return false;
      }

      // Store tokens
      await _storage.write(key: 'access_token', value: accessToken);
      if (refreshToken != null) {
        await _storage.write(key: 'refresh_token', value: refreshToken);
      }

      // Parse user from response
      final userData = data['user'] as Map<String, dynamic>?;
      final user = userData != null ? User.fromJson(userData) : null;

      state = AuthState(
        user: user,
        isAuthenticated: true,
        isLoading: false,
      );
      return true;
    } on ApiException catch (e) {
      String errorMessage;
      if (e.code == 'UNAUTHORIZED') {
        errorMessage = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
      } else if (e.code == 'VALIDATION_ERROR') {
        errorMessage = e.message;
      } else if (e.code == 'NETWORK_ERROR') {
        errorMessage = 'لا يوجد اتصال بالإنترنت';
      } else if (e.code == 'TIMEOUT') {
        errorMessage = 'انتهت مهلة الاتصال';
      } else {
        errorMessage = e.message;
      }
      state = state.copyWith(
        isLoading: false,
        error: errorMessage,
      );
      return false;
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'حدث خطأ غير متوقع',
      );
      return false;
    }
  }

  /// Logout
  Future<void> logout() async {
    try {
      await _apiClient.post(ApiEndpoints.logout);
    } catch (_) {
      // Ignore errors during logout
    }

    await _storage.delete(key: 'access_token');
    await _storage.delete(key: 'refresh_token');

    state = const AuthState();
  }

  /// Clear error
  void clearError() {
    state = state.copyWith(error: null);
  }
}

/// Auth provider
final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthNotifier(apiClient, storage);
});

/// Convenience provider for checking if authenticated
final isAuthenticatedProvider = Provider<bool>((ref) {
  return ref.watch(authProvider).isAuthenticated;
});

/// Convenience provider for current user
final currentUserProvider = Provider<User?>((ref) {
  return ref.watch(authProvider).user;
});
