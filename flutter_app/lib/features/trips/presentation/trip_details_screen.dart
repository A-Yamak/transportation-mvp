import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../providers/trips_provider.dart';
import '../services/location_service.dart';
import 'widgets/destination_card.dart';
import 'widgets/gps_accuracy_indicator.dart';
import 'widgets/route_preview_card.dart';

class TripDetailsScreen extends ConsumerStatefulWidget {
  final String tripId;

  const TripDetailsScreen({Key? key, required this.tripId}) : super(key: key);

  @override
  ConsumerState<TripDetailsScreen> createState() => _TripDetailsScreenState();
}

class _TripDetailsScreenState extends ConsumerState<TripDetailsScreen> {
  late LocationService _locationService;

  @override
  void initState() {
    super.initState();
    _locationService = LocationService();
  }

  @override
  void dispose() {
    _locationService.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final tripAsync = ref.watch(tripDetailsProvider(widget.tripId));

    return Scaffold(
      appBar: AppBar(
        title: Text('${l10n.trip} #${widget.tripId}'),
      ),
      body: tripAsync.when(
        data: (trip) {
          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(tripDetailsProvider(tripId));
            },
            child: Column(
              children: [
                // Trip Header
                Container(
                  width: double.infinity,
                  padding: EdgeInsets.all(16),
                  color: Colors.blue.shade50,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        trip.businessName,
                        style:
                            TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      SizedBox(height: 8),
                      Text('${l10n.trip} #${trip.id}'),
                      SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(Icons.location_on, size: 16),
                          SizedBox(width: 4),
                          Text(
                              '${trip.completedDestinationsCount}/${trip.totalDestinations} ${l10n.completed}'),
                        ],
                      ),
                      SizedBox(height: 8),
                      LinearProgressIndicator(value: trip.progress),
                    ],
                  ),
                ),

                // Trip Metrics Card
                Container(
                  margin: EdgeInsets.all(16),
                  padding: EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: Colors.blue.shade200),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      // Distance
                      Column(
                        children: [
                          Icon(Icons.route, color: Colors.blue, size: 28),
                          SizedBox(height: 8),
                          Text(
                            '${trip.estimatedKm?.toStringAsFixed(1) ?? '—'} km',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Distance',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                      // Cost
                      Column(
                        children: [
                          Icon(Icons.attach_money, color: Colors.green, size: 28),
                          SizedBox(height: 8),
                          Text(
                            '${trip.estimatedCost?.toStringAsFixed(2) ?? '—'} JOD',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Estimated Cost',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                      // Estimated Time
                      Column(
                        children: [
                          Icon(Icons.schedule, color: Colors.orange, size: 28),
                          SizedBox(height: 8),
                          Text(
                            '${((trip.estimatedKm ?? 0) / 40).toStringAsFixed(0)} min',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            'Est. Time',
                            style: TextStyle(fontSize: 12, color: Colors.grey),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                // GPS Accuracy Indicator
                Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16),
                  child: GPSAccuracyIndicator(
                    accuracy: _locationService.currentAccuracy,
                    isTracking: _locationService.isTracking,
                  ),
                ),

                SizedBox(height: 16),

                // Route Preview Card
                RoutePreviewCard(trip: trip),

                // Destinations List
                Expanded(
                  child: ListView.builder(
                    padding: EdgeInsets.all(16),
                    itemCount: trip.destinations.length,
                    itemBuilder: (context, index) {
                      return DestinationCard(
                        destination: trip.destinations[index],
                        tripId: trip.id,
                      );
                    },
                  ),
                ),
              ],
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
              Text('Error loading trip details'),
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
