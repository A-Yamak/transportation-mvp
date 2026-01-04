import 'auth_provider.dart';

class MockAuthService {
  Future<AuthResult> login(String email, String password) async {
    await Future.delayed(Duration(milliseconds: 800)); // Simulate network

    // Mock credentials
    if (email == 'driver@test.com' && password == 'password123') {
      return AuthResult(
        success: true,
        user: User(
          id: '1',
          name: 'أحمد السائق', // Ahmad the Driver
          email: 'driver@test.com',
        ),
        accessToken: 'mock_access_token_12345',
        refreshToken: 'mock_refresh_token_67890',
      );
    }

    return AuthResult(
      success: false,
      error: 'بيانات الدخول غير صحيحة', // Invalid credentials
    );
  }
}

class AuthResult {
  final bool success;
  final User? user;
  final String? accessToken;
  final String? refreshToken;
  final String? error;

  AuthResult({
    required this.success,
    this.user,
    this.accessToken,
    this.refreshToken,
    this.error,
  });
}
