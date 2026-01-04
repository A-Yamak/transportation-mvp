import 'package:flutter/material.dart';
import '../../data/models/trip_model.dart';
import '../../data/models/trip_status.dart';
import '../trip_details_screen.dart';

class TripCard extends StatelessWidget {
  final TripModel trip;

  const TripCard({Key? key, required this.trip}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.only(bottom: 12),
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => TripDetailsScreen(tripId: trip.id),
            ),
          );
        },
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    trip.id,
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  _buildStatusChip(trip.status),
                ],
              ),
              SizedBox(height: 8),
              Text(
                trip.businessName,
                style: TextStyle(color: Colors.grey[700]),
              ),
              SizedBox(height: 12),
              Row(
                children: [
                  Icon(Icons.location_on, size: 16, color: Colors.blue),
                  SizedBox(width: 4),
                  Text('${trip.totalDestinations} destinations'),
                  SizedBox(width: 16),
                  Icon(Icons.check_circle, size: 16, color: Colors.green),
                  SizedBox(width: 4),
                  Text('${trip.completedDestinationsCount} completed'),
                ],
              ),
              if (trip.status == TripStatus.inProgress) ...[
                SizedBox(height: 8),
                LinearProgressIndicator(value: trip.progress),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusChip(TripStatus status) {
    Color color;
    switch (status) {
      case TripStatus.notStarted:
        color = Colors.grey;
        break;
      case TripStatus.inProgress:
        color = Colors.orange;
        break;
      case TripStatus.completed:
        color = Colors.green;
        break;
      case TripStatus.cancelled:
        color = Colors.red;
        break;
    }

    return Container(
      padding: EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        status.label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
