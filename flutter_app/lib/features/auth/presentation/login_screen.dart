import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../../../core/auth/auth_provider.dart';
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

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (!_formKey.currentState!.validate()) return;

    final success = await ref.read(authProvider.notifier).login(
      _emailController.text.trim(),
      _passwordController.text,
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
                const SizedBox(height: 48),

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
