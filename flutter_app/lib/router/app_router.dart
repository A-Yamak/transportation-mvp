import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../core/auth/auth_provider.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/trips/presentation/trips_list_screen.dart';
import '../features/trips/presentation/trip_details_screen.dart';

/// Route names
class Routes {
  static const String splash = '/';
  static const String login = '/login';
  static const String home = '/home';
  static const String tripDetails = '/trips/:id';

  static String tripDetailsPath(String id) => '/trips/$id';
}

/// Router provider
final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: Routes.splash,
    debugLogDiagnostics: true,

    // Redirect based on auth state
    redirect: (context, state) {
      final isLoading = authState.isLoading;
      final isAuthenticated = authState.isAuthenticated;
      final isLoggingIn = state.matchedLocation == Routes.login;
      final isSplash = state.matchedLocation == Routes.splash;

      // Show splash while checking auth
      if (isLoading && isSplash) {
        return null;
      }

      // If not authenticated and not on login, go to login
      if (!isAuthenticated && !isLoggingIn) {
        return Routes.login;
      }

      // If authenticated and on login/splash, go to home
      if (isAuthenticated && (isLoggingIn || isSplash)) {
        return Routes.home;
      }

      return null;
    },

    routes: [
      // Splash screen
      GoRoute(
        path: Routes.splash,
        builder: (context, state) => const SplashScreen(),
      ),

      // Login
      GoRoute(
        path: Routes.login,
        builder: (context, state) => const LoginScreen(),
      ),

      // Home (trips list)
      GoRoute(
        path: Routes.home,
        builder: (context, state) => const TripsListScreen(),
      ),

      // Trip details
      GoRoute(
        path: Routes.tripDetails,
        builder: (context, state) {
          final tripId = state.pathParameters['id']!;
          return TripDetailsScreen(tripId: tripId);
        },
      ),
    ],
  );
});

/// Simple splash screen
class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.local_shipping,
              size: 80,
              color: Color(0xFF1E88E5),
            ),
            SizedBox(height: 24),
            Text(
              'تطبيق السائق',
              style: TextStyle(
                fontSize: 28,
                fontWeight: FontWeight.bold,
                fontFamily: 'Cairo',
              ),
            ),
            SizedBox(height: 16),
            CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
