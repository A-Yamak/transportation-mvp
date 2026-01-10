import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../trips/presentation/trips_list_screen.dart';
import '../../shops/presentation/shops_list_screen.dart';
import '../../profile/presentation/profile_screen.dart';
import '../../notifications/presentation/screens/inbox_screen.dart';
import '../../notifications/providers/notifications_provider.dart';
import '../../notifications/services/fcm_integration_service.dart';
import '../../../core/auth/auth_provider.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../router/app_router.dart';

/// Home shell with bottom navigation
class HomeShell extends ConsumerStatefulWidget {
  const HomeShell({Key? key}) : super(key: key);

  @override
  ConsumerState<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends ConsumerState<HomeShell> {
  int _currentIndex = 0;
  bool _fcmInitialized = false;

  final List<Widget> _screens = const [
    TripsListScreen(),
    ShopsListScreen(),
    InboxScreen(),
  ];

  @override
  void initState() {
    super.initState();
    // Initialize FCM after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _initializeFcm();
    });
  }

  Future<void> _initializeFcm() async {
    if (_fcmInitialized) return;
    _fcmInitialized = true;

    // Initialize FCM integration service
    try {
      await ref.read(fcmIntegrationServiceProvider.future);
      debugPrint('FCM integration initialized from HomeShell');
    } catch (e) {
      debugPrint('Failed to initialize FCM: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final unreadCountAsync = ref.watch(unreadCountProvider);

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.local_shipping_outlined),
            selectedIcon: Icon(Icons.local_shipping),
            label: 'Trips',
          ),
          const NavigationDestination(
            icon: Icon(Icons.store_outlined),
            selectedIcon: Icon(Icons.store),
            label: 'Shops',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: unreadCountAsync.maybeWhen(
                data: (count) => count > 0,
                orElse: () => false,
              ),
              label: unreadCountAsync.maybeWhen(
                data: (count) => Text(count > 99 ? '99+' : '$count'),
                orElse: () => null,
              ),
              child: const Icon(Icons.inbox_outlined),
            ),
            selectedIcon: Badge(
              isLabelVisible: unreadCountAsync.maybeWhen(
                data: (count) => count > 0,
                orElse: () => false,
              ),
              label: unreadCountAsync.maybeWhen(
                data: (count) => Text(count > 99 ? '99+' : '$count'),
                orElse: () => null,
              ),
              child: const Icon(Icons.inbox),
            ),
            label: 'Inbox',
          ),
        ],
      ),
    );
  }
}
