import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../providers/trips_provider.dart';
import 'widgets/trip_card.dart';
import '../../../core/auth/auth_provider.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../router/app_router.dart';

class TripsListScreen extends ConsumerWidget {
  const TripsListScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final tripsAsync = ref.watch(tripsProvider);
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.todaysTrips),
        centerTitle: true,
        actions: [
          // User menu
          PopupMenuButton<String>(
            icon: CircleAvatar(
              backgroundColor: Colors.white,
              child: Text(
                user?.name.isNotEmpty == true
                    ? user!.name[0].toUpperCase()
                    : '?',
                style: const TextStyle(
                  color: AppTheme.primaryColor,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            onSelected: (value) async {
              if (value == 'logout') {
                await ref.read(authProvider.notifier).logout();
              } else if (value == 'profile') {
                context.push(Routes.profile);
              } else if (value == 'history') {
                context.push(Routes.tripHistory);
              }
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                enabled: false,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user?.name ?? '',
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        color: Colors.black,
                      ),
                    ),
                    Text(
                      user?.email ?? '',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                      ),
                    ),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              PopupMenuItem(
                value: 'profile',
                child: Row(
                  children: [
                    const Icon(Icons.person_outline, color: AppTheme.primaryColor),
                    const SizedBox(width: 8),
                    Text(l10n.profile),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'history',
                child: Row(
                  children: [
                    const Icon(Icons.history, color: AppTheme.primaryColor),
                    const SizedBox(width: 8),
                    Text(l10n.tripHistory),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    const Icon(Icons.logout, color: AppTheme.errorColor),
                    const SizedBox(width: 8),
                    Text(l10n.logout),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: tripsAsync.when(
        data: (trips) {
          if (trips.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.inbox_outlined, size: 64, color: Colors.grey),
                  SizedBox(height: 16),
                  Text(
                    l10n.noTripsToday,
                    style: TextStyle(fontSize: 16),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(tripsProvider);
            },
            child: ListView.builder(
              padding: EdgeInsets.all(16),
              itemCount: trips.length,
              itemBuilder: (context, index) {
                return TripCard(trip: trips[index]);
              },
            ),
          );
        },
        loading: () => Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 64, color: Colors.red),
              SizedBox(height: 16),
              Text('Error loading trips'),
              SizedBox(height: 8),
              Text(
                error.toString(),
                style: TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
