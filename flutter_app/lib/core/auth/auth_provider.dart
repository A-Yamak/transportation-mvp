import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../api/api_client.dart';
import '../api/api_config.dart';
import 'mock_auth_service.dart';

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

/// Mock auth service provider
final mockAuthServiceProvider = Provider((ref) => MockAuthService());

/// Auth state notifier
class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _apiClient;
  final FlutterSecureStorage _storage;
  final MockAuthService _mockAuth;

  AuthNotifier(this._apiClient, this._storage, this._mockAuth)
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
      // USE MOCK SERVICE (comment out real API for now)
      final result = await _mockAuth.login(email, password);

      if (result.success) {
        // Store mock tokens
        await _storage.write(key: 'access_token', value: result.accessToken!);
        await _storage.write(key: 'refresh_token', value: result.refreshToken!);

        state = AuthState(
          user: result.user,
          isAuthenticated: true,
          isLoading: false,
        );
        return true;
      } else {
        state = state.copyWith(
          isLoading: false,
          error: result.error,
        );
        return false;
      }
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
  final mockAuth = ref.watch(mockAuthServiceProvider);
  return AuthNotifier(apiClient, storage, mockAuth);
});

/// Convenience provider for checking if authenticated
final isAuthenticatedProvider = Provider<bool>((ref) {
  return ref.watch(authProvider).isAuthenticated;
});

/// Convenience provider for current user
final currentUserProvider = Provider<User?>((ref) {
  return ref.watch(authProvider).user;
});
