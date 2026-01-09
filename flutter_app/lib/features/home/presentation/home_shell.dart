import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../trips/presentation/trips_list_screen.dart';
import '../../shops/presentation/shops_list_screen.dart';
import '../../profile/presentation/profile_screen.dart';
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

  final List<Widget> _screens = const [
    TripsListScreen(),
    ShopsListScreen(),
  ];

  @override
  Widget build(BuildContext context) {
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
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.local_shipping_outlined),
            selectedIcon: Icon(Icons.local_shipping),
            label: 'Trips',
          ),
          NavigationDestination(
            icon: Icon(Icons.store_outlined),
            selectedIcon: Icon(Icons.store),
            label: 'Shops',
          ),
        ],
      ),
    );
  }
}
