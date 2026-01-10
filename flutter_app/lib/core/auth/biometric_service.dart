import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

/// Provider for BiometricService
final biometricServiceProvider = Provider<BiometricService>((ref) {
  return BiometricService();
});

/// Provider for biometric availability
final biometricAvailableProvider = FutureProvider<bool>((ref) async {
  final service = ref.watch(biometricServiceProvider);
  return service.isBiometricAvailable();
});

/// Provider for checking if biometric login is enabled
final biometricEnabledProvider = FutureProvider<bool>((ref) async {
  final service = ref.watch(biometricServiceProvider);
  return service.isBiometricLoginEnabled();
});

/// Service for handling biometric authentication (Face ID / Fingerprint)
class BiometricService {
  final LocalAuthentication _localAuth = LocalAuthentication();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  // Storage keys
  static const String _biometricEnabledKey = 'biometric_enabled';
  static const String _storedEmailKey = 'biometric_email';
  static const String _storedPasswordKey = 'biometric_password';

  /// Check if device supports biometric authentication
  Future<bool> isBiometricAvailable() async {
    try {
      // Check if device supports biometrics
      final canAuthenticateWithBiometrics = await _localAuth.canCheckBiometrics;
      final canAuthenticate = await _localAuth.isDeviceSupported();

      if (!canAuthenticateWithBiometrics || !canAuthenticate) {
        return false;
      }

      // Check if any biometrics are enrolled
      final availableBiometrics = await _localAuth.getAvailableBiometrics();
      return availableBiometrics.isNotEmpty;
    } on PlatformException catch (e) {
      debugPrint('Error checking biometric availability: $e');
      return false;
    }
  }

  /// Get list of available biometric types
  Future<List<BiometricType>> getAvailableBiometrics() async {
    try {
      return await _localAuth.getAvailableBiometrics();
    } on PlatformException catch (e) {
      debugPrint('Error getting available biometrics: $e');
      return [];
    }
  }

  /// Check if biometric login is enabled by user
  Future<bool> isBiometricLoginEnabled() async {
    final enabled = await _storage.read(key: _biometricEnabledKey);
    return enabled == 'true';
  }

  /// Enable biometric login and store credentials securely
  Future<bool> enableBiometricLogin({
    required String email,
    required String password,
  }) async {
    try {
      // First authenticate to confirm user wants to enable
      final authenticated = await authenticate(
        reason: 'Authenticate to enable biometric login',
      );

      if (!authenticated) {
        return false;
      }

      // Store credentials securely
      await _storage.write(key: _biometricEnabledKey, value: 'true');
      await _storage.write(key: _storedEmailKey, value: email);
      await _storage.write(key: _storedPasswordKey, value: password);

      debugPrint('Biometric login enabled for $email');
      return true;
    } catch (e) {
      debugPrint('Error enabling biometric login: $e');
      return false;
    }
  }

  /// Disable biometric login and clear stored credentials
  Future<void> disableBiometricLogin() async {
    await _storage.delete(key: _biometricEnabledKey);
    await _storage.delete(key: _storedEmailKey);
    await _storage.delete(key: _storedPasswordKey);
    debugPrint('Biometric login disabled');
  }

  /// Authenticate using biometrics
  Future<bool> authenticate({
    required String reason,
  }) async {
    try {
      return await _localAuth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          stickyAuth: true,
          biometricOnly: true,
        ),
      );
    } on PlatformException catch (e) {
      debugPrint('Biometric authentication error: $e');
      return false;
    }
  }

  /// Get stored credentials after biometric authentication
  Future<({String email, String password})?> getStoredCredentials() async {
    try {
      // First check if biometric login is enabled
      final enabled = await isBiometricLoginEnabled();
      if (!enabled) {
        return null;
      }

      // Authenticate with biometrics
      final authenticated = await authenticate(
        reason: 'Authenticate to login',
      );

      if (!authenticated) {
        return null;
      }

      // Get stored credentials
      final email = await _storage.read(key: _storedEmailKey);
      final password = await _storage.read(key: _storedPasswordKey);

      if (email == null || password == null) {
        return null;
      }

      return (email: email, password: password);
    } catch (e) {
      debugPrint('Error getting stored credentials: $e');
      return null;
    }
  }

  /// Check if credentials are stored for biometric login
  Future<bool> hasStoredCredentials() async {
    final email = await _storage.read(key: _storedEmailKey);
    final password = await _storage.read(key: _storedPasswordKey);
    return email != null && password != null;
  }

  /// Get the biometric type label for UI
  Future<String> getBiometricTypeLabel() async {
    final biometrics = await getAvailableBiometrics();

    if (biometrics.contains(BiometricType.face)) {
      return 'Face ID';
    } else if (biometrics.contains(BiometricType.fingerprint)) {
      return 'Fingerprint';
    } else if (biometrics.contains(BiometricType.iris)) {
      return 'Iris';
    } else if (biometrics.contains(BiometricType.strong) ||
               biometrics.contains(BiometricType.weak)) {
      return 'Biometric';
    }
    return 'Biometric';
  }

  /// Get the biometric type icon for UI
  Future<String> getBiometricTypeIcon() async {
    final biometrics = await getAvailableBiometrics();

    if (biometrics.contains(BiometricType.face)) {
      return 'face_id'; // Use custom icon
    } else {
      return 'fingerprint';
    }
  }
}
