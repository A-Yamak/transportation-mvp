import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../../../shared/theme/app_theme.dart';
import '../providers/profile_provider.dart';
import '../data/models/trip_history_model.dart';

/// Trip History Screen with pagination and filters
class TripHistoryScreen extends ConsumerStatefulWidget {
  const TripHistoryScreen({super.key});

  @override
  ConsumerState<TripHistoryScreen> createState() => _TripHistoryScreenState();
}

class _TripHistoryScreenState extends ConsumerState<TripHistoryScreen> {
  final _scrollController = ScrollController();
  String? _selectedStatus;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    // Load initial data
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(tripHistoryProvider.notifier).loadTrips();
    });
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(tripHistoryProvider.notifier).loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final state = ref.watch(tripHistoryProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.tripHistory),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => context.pop(),
        ),
        actions: [
          PopupMenuButton<String>(
            icon: Badge(
              isLabelVisible: _selectedStatus != null,
              child: const Icon(Icons.filter_list),
            ),
            onSelected: (status) {
              setState(() {
                _selectedStatus = status == 'all' ? null : status;
              });
              ref.read(tripHistoryProvider.notifier).loadTrips(
                    status: _selectedStatus,
                  );
            },
            itemBuilder: (context) => [
              PopupMenuItem(
                value: 'all',
                child: Text(l10n.allStatuses),
              ),
              PopupMenuItem(
                value: 'completed',
                child: Text(l10n.completed),
              ),
              PopupMenuItem(
                value: 'cancelled',
                child: Text(l10n.cancelled),
              ),
            ],
          ),
        ],
      ),
      body: state.error != null
          ? _ErrorView(
              message: state.error!,
              onRetry: () => ref.read(tripHistoryProvider.notifier).refresh(),
            )
          : state.trips.isEmpty && !state.isLoading
              ? _EmptyView()
              : RefreshIndicator(
                  onRefresh: () async {
                    await ref.read(tripHistoryProvider.notifier).refresh();
                  },
                  child: ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: state.trips.length + (state.hasMore ? 1 : 0),
                    itemBuilder: (context, index) {
                      if (index >= state.trips.length) {
                        return const Center(
                          child: Padding(
                            padding: EdgeInsets.all(16),
                            child: CircularProgressIndicator(),
                          ),
                        );
                      }
                      return _TripHistoryCard(trip: state.trips[index]);
                    },
                  ),
                ),
    );
  }
}

/// Individual trip history card
class _TripHistoryCard extends StatelessWidget {
  final TripHistoryModel trip;

  const _TripHistoryCard({required this.trip});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          // Could navigate to trip details
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header: Date and Status
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Icon(Icons.calendar_today, size: 16, color: Colors.grey[600]),
                      const SizedBox(width: 8),
                      Text(
                        trip.date,
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                  _StatusChip(status: trip.status),
                ],
              ),

              const SizedBox(height: 12),

              // Business name if available
              if (trip.businessName != null) ...[
                Row(
                  children: [
                    Icon(Icons.business, size: 16, color: Colors.grey[600]),
                    const SizedBox(width: 8),
                    Text(
                      trip.businessName!,
                      style: TextStyle(color: Colors.grey[700]),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
              ],

              // Stats row
              Row(
                children: [
                  _StatChip(
                    icon: Icons.location_on,
                    value: '${trip.destinationsCompleted}/${trip.destinationsCount}',
                    label: l10n.destinations,
                  ),
                  const SizedBox(width: 16),
                  if (trip.actualKm != null)
                    _StatChip(
                      icon: Icons.speed,
                      value: '${trip.actualKm!.toStringAsFixed(1)} ${l10n.km}',
                      label: l10n.distance,
                    ),
                  const SizedBox(width: 16),
                  _StatChip(
                    icon: Icons.timer_outlined,
                    value: trip.formattedDuration,
                    label: l10n.duration,
                  ),
                ],
              ),

              // Vehicle info
              if (trip.vehicle != null) ...[
                const SizedBox(height: 12),
                const Divider(height: 1),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Icon(Icons.directions_car, size: 16, color: Colors.grey[500]),
                    const SizedBox(width: 8),
                    Text(
                      '${trip.vehicle!.fullName} (${trip.vehicle!.licensePlate})',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.grey[600],
                      ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Status chip with color coding
class _StatusChip extends StatelessWidget {
  final String status;

  const _StatusChip({required this.status});

  @override
  Widget build(BuildContext context) {
    Color backgroundColor;
    Color textColor;

    switch (status) {
      case 'completed':
        backgroundColor = AppTheme.successColor.withOpacity(0.1);
        textColor = AppTheme.successColor;
        break;
      case 'cancelled':
        backgroundColor = AppTheme.errorColor.withOpacity(0.1);
        textColor = AppTheme.errorColor;
        break;
      case 'in_progress':
        backgroundColor = AppTheme.warningColor.withOpacity(0.1);
        textColor = AppTheme.warningColor;
        break;
      default:
        backgroundColor = Colors.grey.withOpacity(0.1);
        textColor = Colors.grey;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        _getStatusText(status, context),
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
          color: textColor,
        ),
      ),
    );
  }

  String _getStatusText(String status, BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    switch (status) {
      case 'completed':
        return l10n.completed;
      case 'cancelled':
        return l10n.cancelled;
      case 'in_progress':
        return l10n.inProgress;
      default:
        return status;
    }
  }
}

/// Small stat chip
class _StatChip extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;

  const _StatChip({
    required this.icon,
    required this.value,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: Colors.grey[600]),
        const SizedBox(width: 4),
        Text(
          value,
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

/// Empty state view
class _EmptyView extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(Icons.history, size: 64, color: Colors.grey[400]),
          const SizedBox(height: 16),
          Text(
            l10n.noTripHistory,
            style: TextStyle(
              fontSize: 16,
              color: Colors.grey[600],
            ),
          ),
        ],
      ),
    );
  }
}

/// Error view with retry
class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline, size: 64, color: AppTheme.errorColor),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: Text(l10n.retry),
            ),
          ],
        ),
      ),
    );
  }
}
