import 'models/destination_model.dart';
import 'models/destination_status.dart';
import 'models/trip_model.dart';
import 'models/trip_status.dart';

class MockTripService {
  static final MockTripService _instance = MockTripService._internal();
  factory MockTripService() => _instance;

  MockTripService._internal() {
    _initializeMockData();
  }

  List<TripModel> _trips = [];

  void _initializeMockData() {
    _trips = [
      // Trip 1: Not started, 3 destinations in Amman
      TripModel.mock(
        id: 'TRIP-001',
        status: TripStatus.notStarted,
        businessName: 'مصنع الحلويات (Sweets Factory)',
        destinations: [
          DestinationModel.mock(
            order: 1,
            address: 'Rainbow St, Amman',
            lat: 31.9539,
            lng: 35.9106,
          ),
          DestinationModel.mock(
            order: 2,
            address: 'King Abdullah Gardens, Amman',
            lat: 31.9454,
            lng: 35.9284,
          ),
          DestinationModel.mock(
            order: 3,
            address: 'Abdali Mall, Amman',
            lat: 31.9730,
            lng: 35.9087,
          ),
        ],
      ),
      // Trip 2: In progress, 1 completed, 1 arrived
      TripModel.mock(
        id: 'TRIP-002',
        status: TripStatus.inProgress,
        businessName: 'مخابز دليش (Delish Bakeries)',
        destinations: [
          DestinationModel.mock(
            order: 1,
            address: 'Wakalat St, Amman',
            lat: 31.9497,
            lng: 35.9327,
            status: DestinationStatus.completed,
          ),
          DestinationModel.mock(
            order: 2,
            address: 'Swefieh, Amman',
            lat: 31.9332,
            lng: 35.8621,
            status: DestinationStatus.arrived,
          ),
        ],
      ),
    ];

    // Update timestamps for trip 2 destinations
    _trips[1] = _trips[1].copyWith(
      startedAt: DateTime.now().subtract(Duration(hours: 1)),
      destinations: [
        _trips[1].destinations[0].copyWith(
          completedAt: DateTime.now().subtract(Duration(minutes: 30)),
        ),
        _trips[1].destinations[1].copyWith(
          arrivedAt: DateTime.now().subtract(Duration(minutes: 5)),
        ),
      ],
    );
  }

  /// Simulate network delay
  Future<List<TripModel>> getTodaysTrips() async {
    await Future.delayed(Duration(milliseconds: 500));
    return List.from(_trips); // Return copy
  }

  Future<TripModel> getTripById(String tripId) async {
    await Future.delayed(Duration(milliseconds: 300));
    return _trips.firstWhere((trip) => trip.id == tripId);
  }

  Future<void> startTrip(String tripId) async {
    await Future.delayed(Duration(milliseconds: 300));
    final index = _trips.indexWhere((t) => t.id == tripId);
    if (index != -1) {
      _trips[index] = _trips[index].copyWith(
        status: TripStatus.inProgress,
        startedAt: DateTime.now(),
      );
    }
  }

  Future<void> markDestinationArrived(String tripId, String destId) async {
    await Future.delayed(Duration(milliseconds: 300));
    final tripIndex = _trips.indexWhere((t) => t.id == tripId);
    if (tripIndex == -1) return;

    final trip = _trips[tripIndex];
    final updatedDestinations = trip.destinations.map((dest) {
      if (dest.id == destId) {
        return dest.copyWith(
          status: DestinationStatus.arrived,
          arrivedAt: DateTime.now(),
        );
      }
      return dest;
    }).toList();

    _trips[tripIndex] = trip.copyWith(destinations: updatedDestinations);
  }

  Future<void> markDestinationCompleted(String tripId, String destId) async {
    await Future.delayed(Duration(milliseconds: 300));
    final tripIndex = _trips.indexWhere((t) => t.id == tripId);
    if (tripIndex == -1) return;

    final trip = _trips[tripIndex];
    final updatedDestinations = trip.destinations.map((dest) {
      if (dest.id == destId) {
        return dest.copyWith(
          status: DestinationStatus.completed,
          completedAt: DateTime.now(),
        );
      }
      return dest;
    }).toList();

    final updatedTrip = trip.copyWith(destinations: updatedDestinations);

    // Check if all destinations are completed
    final allCompleted = updatedTrip.destinations
        .every((d) => d.status == DestinationStatus.completed);

    if (allCompleted) {
      _trips[tripIndex] = updatedTrip.copyWith(
        status: TripStatus.completed,
        completedAt: DateTime.now(),
      );
    } else {
      _trips[tripIndex] = updatedTrip;
    }
  }

  Future<void> markDestinationFailed(String tripId, String destId) async {
    await Future.delayed(Duration(milliseconds: 300));
    final tripIndex = _trips.indexWhere((t) => t.id == tripId);
    if (tripIndex == -1) return;

    final trip = _trips[tripIndex];
    final updatedDestinations = trip.destinations.map((dest) {
      if (dest.id == destId) {
        return dest.copyWith(
          status: DestinationStatus.failed,
        );
      }
      return dest;
    }).toList();

    _trips[tripIndex] = trip.copyWith(destinations: updatedDestinations);
  }
}
