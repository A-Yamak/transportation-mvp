import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:driver_app/generated/l10n/app_localizations.dart';
import '../providers/trips_provider.dart';
import 'widgets/destination_card.dart';

class TripDetailsScreen extends ConsumerWidget {
  final String tripId;

  const TripDetailsScreen({Key? key, required this.tripId}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = AppLocalizations.of(context)!;
    final tripAsync = ref.watch(tripDetailsProvider(tripId));

    return Scaffold(
      appBar: AppBar(
        title: Text('${l10n.trip} #$tripId'),
      ),
      body: tripAsync.when(
        data: (trip) {
          return Column(
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
