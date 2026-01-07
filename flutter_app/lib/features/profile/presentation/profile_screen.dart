import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../../../shared/theme/app_theme.dart';
import '../providers/profile_provider.dart';
import '../data/models/driver_profile_model.dart';
import '../data/models/driver_stats_model.dart';
import 'widgets/stats_card.dart';
import 'widgets/vehicle_info_card.dart';

/// Driver Profile/Dashboard Screen
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final profileAsync = ref.watch(driverProfileProvider);
    final statsAsync = ref.watch(driverStatsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.profile),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.history),
            onPressed: () => context.push('/trips/history'),
            tooltip: l10n.tripHistory,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(driverProfileProvider);
          ref.invalidate(driverStatsProvider);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Profile Header
              profileAsync.when(
                data: (profile) => _ProfileHeader(profile: profile),
                loading: () => const _ProfileHeaderSkeleton(),
                error: (e, _) => _ErrorCard(message: e.toString()),
              ),

              const SizedBox(height: 24),

              // Stats Dashboard
              statsAsync.when(
                data: (stats) => _StatsDashboard(stats: stats),
                loading: () => const _StatsDashboardSkeleton(),
                error: (e, _) => _ErrorCard(message: e.toString()),
              ),

              const SizedBox(height: 24),

              // Vehicle Info
              profileAsync.when(
                data: (profile) => profile.vehicle != null
                    ? VehicleInfoCard(vehicle: profile.vehicle!)
                    : _NoVehicleCard(),
                loading: () => const _VehicleCardSkeleton(),
                error: (_, __) => const SizedBox.shrink(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Profile header with photo and basic info
class _ProfileHeader extends StatelessWidget {
  final DriverProfileModel profile;

  const _ProfileHeader({required this.profile});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            // Profile Photo
            CircleAvatar(
              radius: 40,
              backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
              backgroundImage: profile.profilePhotoUrl != null
                  ? NetworkImage(profile.profilePhotoUrl!)
                  : null,
              child: profile.profilePhotoUrl == null
                  ? Text(
                      profile.name.isNotEmpty ? profile.name[0].toUpperCase() : '?',
                      style: const TextStyle(
                        fontSize: 32,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.primaryColor,
                      ),
                    )
                  : null,
            ),
            const SizedBox(width: 16),
            // Info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    profile.name,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.email_outlined, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          profile.email,
                          style: TextStyle(color: Colors.grey[600]),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.phone_outlined, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Text(
                        profile.phone.isNotEmpty ? profile.phone : '-',
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.badge_outlined, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 4),
                      Text(
                        profile.licenseNumber.isNotEmpty ? profile.licenseNumber : '-',
                        style: TextStyle(color: Colors.grey[600]),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Stats dashboard with today, monthly, and all-time stats
class _StatsDashboard extends StatelessWidget {
  final DriverStatsModel stats;

  const _StatsDashboard({required this.stats});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          l10n.statistics,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: StatsCard(
                title: l10n.today,
                tripsCount: stats.today.tripsCount,
                destinationsCompleted: stats.today.destinationsCompleted,
                kmDriven: stats.today.kmDriven,
                color: AppTheme.primaryColor,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: StatsCard(
                title: l10n.thisMonth,
                tripsCount: stats.thisMonth.tripsCount,
                destinationsCompleted: stats.thisMonth.destinationsCompleted,
                kmDriven: stats.thisMonth.kmDriven,
                color: AppTheme.secondaryColor,
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        StatsCard(
          title: l10n.allTime,
          tripsCount: stats.allTime.tripsCount,
          destinationsCompleted: stats.allTime.destinationsCompleted,
          kmDriven: stats.allTime.kmDriven,
          color: Colors.teal,
          expanded: true,
        ),
      ],
    );
  }
}

/// Skeleton loaders
class _ProfileHeaderSkeleton extends StatelessWidget {
  const _ProfileHeaderSkeleton();

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: Colors.grey[300],
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(width: 150, height: 20, color: Colors.grey[300]),
                  const SizedBox(height: 8),
                  Container(width: 200, height: 14, color: Colors.grey[300]),
                  const SizedBox(height: 8),
                  Container(width: 120, height: 14, color: Colors.grey[300]),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatsDashboardSkeleton extends StatelessWidget {
  const _StatsDashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(width: 100, height: 18, color: Colors.grey[300]),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: Container(height: 120, color: Colors.grey[200]),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Container(height: 120, color: Colors.grey[200]),
            ),
          ],
        ),
      ],
    );
  }
}

class _VehicleCardSkeleton extends StatelessWidget {
  const _VehicleCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 150,
      decoration: BoxDecoration(
        color: Colors.grey[200],
        borderRadius: BorderRadius.circular(12),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  final String message;

  const _ErrorCard({required this.message});

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppTheme.errorColor.withOpacity(0.1),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            const Icon(Icons.error_outline, color: AppTheme.errorColor),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(color: AppTheme.errorColor),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NoVehicleCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(Icons.directions_car_outlined, color: Colors.grey[400], size: 40),
            const SizedBox(width: 16),
            Text(
              l10n.noVehicleAssigned,
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      ),
    );
  }
}
