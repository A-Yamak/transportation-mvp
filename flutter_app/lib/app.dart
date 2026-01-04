import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import 'router/app_router.dart';
import 'shared/theme/app_theme.dart';
import 'core/auth/auth_provider.dart';

/// Main application widget - Arabic-First Design
class DriverApp extends ConsumerWidget {
  const DriverApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    final locale = ref.watch(localeProvider);

    return MaterialApp.router(
      title: 'تطبيق السائق', // Driver App in Arabic
      debugShowCheckedModeBanner: false,

      // Arabic-First: Default locale is Arabic
      locale: locale,
      supportedLocales: const [
        Locale('ar'), // Arabic FIRST
        Locale('en'), // English secondary
      ],

      // Localization delegates
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],

      // Theme
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.light,

      // Router
      routerConfig: router,

      // RTL/LTR based on locale
      builder: (context, child) {
        return Directionality(
          textDirection: locale.languageCode == 'ar'
              ? TextDirection.rtl
              : TextDirection.ltr,
          child: child!,
        );
      },
    );
  }
}

/// Provider for current locale - defaults to Arabic
final localeProvider = StateProvider<Locale>((ref) {
  return const Locale('ar'); // Arabic by default
});
