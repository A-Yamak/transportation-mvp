import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../../../core/auth/auth_provider.dart';
import '../../../core/auth/biometric_service.dart';
import '../../../core/api/api_client.dart';
import '../../../app.dart';
import '../../../shared/theme/app_theme.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController(text: 'driver@alsabiqoon.com');
  final _passwordController = TextEditingController(text: 'driver123');
  bool _obscurePassword = true;
  bool _isSendingResetEmail = false;
  bool _isBiometricLoading = false;

  @override
  void initState() {
    super.initState();
    // Try biometric login on screen load if available
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _tryBiometricLogin();
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _tryBiometricLogin() async {
    final biometricService = ref.read(biometricServiceProvider);

    // Check if biometric login is enabled
    final isEnabled = await biometricService.isBiometricLoginEnabled();
    if (!isEnabled) return;

    // Check if biometrics are available
    final isAvailable = await biometricService.isBiometricAvailable();
    if (!isAvailable) return;

    // Attempt biometric authentication
    await _loginWithBiometric();
  }

  Future<void> _loginWithBiometric() async {
    setState(() {
      _isBiometricLoading = true;
    });

    try {
      final biometricService = ref.read(biometricServiceProvider);
      final credentials = await biometricService.getStoredCredentials();

      if (credentials == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Biometric authentication cancelled'),
              backgroundColor: Colors.orange,
            ),
          );
        }
        return;
      }

      // Login with stored credentials
      final success = await ref.read(authProvider.notifier).login(
        credentials.email,
        credentials.password,
      );

      if (!success && mounted) {
        final error = ref.read(authProvider).error;
        if (error != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(error),
              backgroundColor: AppTheme.errorColor,
            ),
          );
        }
      }
    } finally {
      if (mounted) {
        setState(() {
          _isBiometricLoading = false;
        });
      }
    }
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;

    final email = _emailController.text.trim();
    final password = _passwordController.text;

    final success = await ref.read(authProvider.notifier).login(email, password);

    if (success && mounted) {
      // Offer to enable biometric after successful login
      await _offerBiometricSetup(email, password);
    } else if (!success && mounted) {
      final error = ref.read(authProvider).error;
      if (error != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(error),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
    }
  }

  Future<void> _offerBiometricSetup(String email, String password) async {
    final biometricService = ref.read(biometricServiceProvider);

    // Check if biometrics are available
    final isAvailable = await biometricService.isBiometricAvailable();
    if (!isAvailable) return;

    // Check if already enabled
    final isEnabled = await biometricService.isBiometricLoginEnabled();
    if (isEnabled) return;

    // Get biometric type for display
    final biometricType = await biometricService.getBiometricTypeLabel();

    if (!mounted) return;

    // Show dialog to offer biometric setup
    final shouldEnable = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Enable $biometricType Login?'),
        content: Text(
          'Would you like to use $biometricType for faster login next time?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not Now'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Enable'),
          ),
        ],
      ),
    );

    if (shouldEnable == true) {
      final enabled = await biometricService.enableBiometricLogin(
        email: email,
        password: password,
      );

      if (enabled && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('$biometricType login enabled!'),
            backgroundColor: AppTheme.successColor,
          ),
        );
      }
    }
  }

  void _toggleLanguage() {
    final currentLocale = ref.read(localeProvider);
    final newLocale = currentLocale.languageCode == 'ar'
        ? const Locale('en')
        : const Locale('ar');
    ref.read(localeProvider.notifier).state = newLocale;
  }

  Future<void> _showForgotPasswordDialog() async {
    final l10n = AppLocalizations.of(context)!;
    final emailController = TextEditingController();
    final formKey = GlobalKey<FormState>();

    final result = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(l10n.forgotPassword),
        content: Form(
          key: formKey,
          child: TextFormField(
            controller: emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              labelText: l10n.email,
              prefixIcon: const Icon(Icons.email_outlined),
            ),
            validator: (value) {
              if (value == null || value.isEmpty) {
                return 'Please enter your email';
              }
              if (!value.contains('@')) {
                return 'Please enter a valid email';
              }
              return null;
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(l10n.cancel),
          ),
          ElevatedButton(
            onPressed: () {
              if (formKey.currentState!.validate()) {
                Navigator.pop(context, true);
              }
            },
            child: Text(l10n.send),
          ),
        ],
      ),
    );

    if (result == true && mounted) {
      await _sendPasswordResetEmail(emailController.text.trim());
    }

    emailController.dispose();
  }

  Future<void> _sendPasswordResetEmail(String email) async {
    final l10n = AppLocalizations.of(context)!;

    setState(() {
      _isSendingResetEmail = true;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      await apiClient.post(
        '/api/v1/auth/forgot-password',
        data: {'email': email},
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.passwordResetEmailSent),
            backgroundColor: AppTheme.successColor,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        // Still show success message for security (prevent email enumeration)
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(l10n.passwordResetEmailSent),
            backgroundColor: AppTheme.successColor,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isSendingResetEmail = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final authState = ref.watch(authProvider);
    final locale = ref.watch(localeProvider);

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 60),

                // Logo
                const Icon(
                  Icons.local_shipping,
                  size: 80,
                  color: AppTheme.primaryColor,
                ),
                const SizedBox(height: 24),

                // Title
                Text(
                  l10n.appTitle,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineLarge,
                ),
                const SizedBox(height: 48),

                // Email field
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: l10n.email,
                    prefixIcon: const Icon(Icons.email_outlined),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'الرجاء إدخال البريد الإلكتروني';
                    }
                    if (!value.contains('@')) {
                      return 'الرجاء إدخال بريد إلكتروني صحيح';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 16),

                // Password field
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _login(),
                  decoration: InputDecoration(
                    labelText: l10n.password,
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(
                        _obscurePassword
                            ? Icons.visibility_off
                            : Icons.visibility,
                      ),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'الرجاء إدخال كلمة المرور';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 8),

                // Forgot password
                Align(
                  alignment: locale.languageCode == 'ar'
                      ? Alignment.centerLeft
                      : Alignment.centerRight,
                  child: TextButton(
                    onPressed: _isSendingResetEmail ? null : _showForgotPasswordDialog,
                    child: _isSendingResetEmail
                        ? const SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(l10n.forgotPassword),
                  ),
                ),
                const SizedBox(height: 24),

                // Login button
                ElevatedButton(
                  onPressed: authState.isLoading ? null : _login,
                  child: authState.isLoading
                      ? const SizedBox(
                          height: 24,
                          width: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(l10n.loginButton),
                ),
                const SizedBox(height: 16),

                // Biometric login button
                Consumer(
                  builder: (context, ref, child) {
                    final biometricAvailable = ref.watch(biometricAvailableProvider);
                    final biometricEnabled = ref.watch(biometricEnabledProvider);

                    // Only show if biometrics are available AND enabled
                    final showBiometric = biometricAvailable.maybeWhen(
                      data: (available) => available && biometricEnabled.maybeWhen(
                        data: (enabled) => enabled,
                        orElse: () => false,
                      ),
                      orElse: () => false,
                    );

                    if (!showBiometric) return const SizedBox.shrink();

                    return Column(
                      children: [
                        const Row(
                          children: [
                            Expanded(child: Divider()),
                            Padding(
                              padding: EdgeInsets.symmetric(horizontal: 16),
                              child: Text('OR', style: TextStyle(color: Colors.grey)),
                            ),
                            Expanded(child: Divider()),
                          ],
                        ),
                        const SizedBox(height: 16),
                        OutlinedButton.icon(
                          onPressed: _isBiometricLoading ? null : _loginWithBiometric,
                          icon: _isBiometricLoading
                              ? const SizedBox(
                                  height: 20,
                                  width: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.fingerprint),
                          label: FutureBuilder<String>(
                            future: ref.read(biometricServiceProvider).getBiometricTypeLabel(),
                            builder: (context, snapshot) {
                              final label = snapshot.data ?? 'Biometric';
                              return Text('Login with $label');
                            },
                          ),
                          style: OutlinedButton.styleFrom(
                            minimumSize: const Size.fromHeight(48),
                          ),
                        ),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 32),

                // Language switcher
                const Divider(),
                const SizedBox(height: 16),
                TextButton.icon(
                  onPressed: _toggleLanguage,
                  icon: const Icon(Icons.language),
                  label: Text(
                    locale.languageCode == 'ar' ? 'English' : 'العربية',
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
