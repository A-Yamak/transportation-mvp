import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_gen/gen_l10n/app_localizations.dart';
import 'package:go_router/go_router.dart';
import '../../../core/auth/auth_provider.dart';
import '../../../router/app_router.dart';
import '../../../shared/theme/app_theme.dart';

class TripsListScreen extends ConsumerWidget {
  const TripsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final user = ref.watch(currentUserProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.todaysTrips),
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
      body: RefreshIndicator(
        onRefresh: () async {
          // TODO: Refresh trips
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // Date header
            Text(
              _formatDate(DateTime.now(), context),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Text(
              '${l10n.noTripsToday}',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 24),

            // Placeholder trip card
            _TripCard(
              tripId: '1234',
              businessName: 'مصنع الحلويات',
              destinationsCount: 8,
              estimatedKm: 32.5,
              status: TripStatus.notStarted,
              onTap: () {
                context.push(Routes.tripDetailsPath('1234'));
              },
            ),
            _TripCard(
              tripId: '1235',
              businessName: 'مخبز دليش',
              destinationsCount: 5,
              estimatedKm: 18.5,
              completedDestinations: 3,
              status: TripStatus.inProgress,
              onTap: () {
                context.push(Routes.tripDetailsPath('1235'));
              },
            ),
            _TripCard(
              tripId: '1233',
              businessName: 'شركة ABC',
              destinationsCount: 5,
              estimatedKm: 18.5,
              actualKm: 19.2,
              completedDestinations: 5,
              status: TripStatus.completed,
              onTap: () {
                context.push(Routes.tripDetailsPath('1233'));
              },
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(DateTime date, BuildContext context) {
    final locale = Localizations.localeOf(context);
    final weekdays = locale.languageCode == 'ar'
        ? ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']
        : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    final months = locale.languageCode == 'ar'
        ? ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
        : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    return '${weekdays[date.weekday % 7]}، ${date.day} ${months[date.month - 1]} ${date.year}';
  }
}

enum TripStatus { notStarted, inProgress, completed, cancelled }

class _TripCard extends StatelessWidget {
  final String tripId;
  final String businessName;
  final int destinationsCount;
  final double estimatedKm;
  final double? actualKm;
  final int completedDestinations;
  final TripStatus status;
  final VoidCallback onTap;

  const _TripCard({
    required this.tripId,
    required this.businessName,
    required this.destinationsCount,
    required this.estimatedKm,
    this.actualKm,
    this.completedDestinations = 0,
    required this.status,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header row
              Row(
                children: [
                  const Icon(Icons.local_shipping, color: AppTheme.primaryColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${l10n.trip} #$tripId',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  _StatusBadge(status: status),
                ],
              ),
              const SizedBox(height: 8),

              // Business name
              Text(
                businessName,
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 4),

              // Stats row
              Text(
                status == TripStatus.completed
                    ? '$destinationsCount ${l10n.destinations} • ${actualKm?.toStringAsFixed(1) ?? estimatedKm.toStringAsFixed(1)} ${l10n.km}'
                    : status == TripStatus.inProgress
                        ? '$completedDestinations/$destinationsCount ${l10n.destinations}'
                        : '$destinationsCount ${l10n.destinations} • ~${estimatedKm.toStringAsFixed(1)} ${l10n.km}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Colors.grey[600],
                ),
              ),

              // Action button
              if (status == TripStatus.notStarted) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: onTap,
                    child: Text(l10n.startTrip),
                  ),
                ),
              ] else if (status == TripStatus.inProgress) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: onTap,
                    child: Text(l10n.continueTrip),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final TripStatus status;

  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    final (label, color) = switch (status) {
      TripStatus.notStarted => (l10n.notStarted, StatusColors.pending),
      TripStatus.inProgress => (l10n.inProgress, StatusColors.inProgress),
      TripStatus.completed => (l10n.completed, StatusColors.completed),
      TripStatus.cancelled => (l10n.cancelled, StatusColors.cancelled),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.5)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
