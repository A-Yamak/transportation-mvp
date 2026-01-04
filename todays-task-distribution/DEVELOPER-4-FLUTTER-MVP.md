# Developer 4: Flutter Driver App MVP (Mock Data Only)

**Date**: 2026-01-04
**Phase**: 5.1 - Flutter App Basic Implementation (No Backend)
**Estimated Time**: 6-8 hours
**Priority**: MEDIUM (Parallel to backend development)

---

## 🎯 Mission

Build a functional Flutter driver app MVP using **mock data only**. No backend connection required. This allows the mobile app to be developed in parallel while backend APIs are being built. Focus on core driver workflows: viewing trips, navigating to destinations, and completing deliveries.

---

## 📋 Task Overview

| Task | Files | Time Estimate |
|------|-------|---------------|
| 1. Mock data models & services | 4 files | 60 min |
| 2. Trips list screen (today's trips) | 2 files | 90 min |
| 3. Trip details screen | 2 files | 90 min |
| 4. Destination card with status | 2 files | 60 min |
| 5. Mock navigation integration | 1 file | 30 min |
| 6. Mark arrived/completed actions | 2 files | 90 min |
| 7. Arabic localization | 2 files | 30 min |
| **Total** | **15 files** | **6-8 hours** |

---

## 📱 App Flow (MVP)

```
App Launch
    ↓
Login Screen (mock - auto login)
    ↓
Today's Trips List
    ↓
Tap Trip → Trip Details (shows all destinations)
    ↓
Tap Destination → Actions:
    - Navigate (opens Google Maps)
    - Mark Arrived
    - Mark Completed
    ↓
All destinations completed → Trip Complete
```

---

## 🗂️ Files You Will Create/Modify

### Models (4 files)
```
lib/features/trips/data/models/trip_model.dart
lib/features/trips/data/models/destination_model.dart
lib/features/trips/data/models/trip_status.dart
lib/features/trips/data/models/destination_status.dart
```

### Mock Services (2 files)
```
lib/features/trips/data/mock_trip_service.dart
lib/core/services/mock_navigation_service.dart
```

### State Management (3 files)
```
lib/features/trips/providers/trips_provider.dart
lib/features/trips/providers/trip_details_provider.dart
lib/features/trips/providers/destination_actions_provider.dart
```

### Screens (4 files)
```
lib/features/trips/presentation/trips_list_screen.dart (already exists - enhance)
lib/features/trips/presentation/trip_details_screen.dart (already exists - enhance)
lib/features/trips/presentation/widgets/trip_card.dart
lib/features/trips/presentation/widgets/destination_card.dart
```

### Localization (2 files)
```
lib/l10n/app_en.arb (enhance)
lib/l10n/app_ar.arb (enhance)
```

---

## 📐 Technical Specifications

### 1. Data Models

#### trip_model.dart

```dart
class TripModel {
  final String id;
  final String deliveryRequestId;
  final TripStatus status;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final double? actualKmDriven;
  final String businessName;
  final List<DestinationModel> destinations;

  TripModel({
    required this.id,
    required this.deliveryRequestId,
    required this.status,
    this.startedAt,
    this.completedAt,
    this.actualKmDriven,
    required this.businessName,
    required this.destinations,
  });

  // Mock factory
  factory TripModel.mock({
    required String id,
    required TripStatus status,
    required List<DestinationModel> destinations,
  }) {
    return TripModel(
      id: id,
      deliveryRequestId: 'DR-${id}',
      status: status,
      startedAt: status != TripStatus.notStarted ? DateTime.now().subtract(Duration(hours: 2)) : null,
      businessName: 'Sweets Factory ERP',
      destinations: destinations,
    );
  }

  int get completedDestinationsCount =>
    destinations.where((d) => d.status == DestinationStatus.completed).length;

  int get totalDestinations => destinations.length;

  bool get isCompleted => completedDestinationsCount == totalDestinations;

  DestinationModel? get nextDestination =>
    destinations.firstWhere(
      (d) => d.status == DestinationStatus.pending,
      orElse: () => destinations.first,
    );
}
```

#### destination_model.dart

```dart
class DestinationModel {
  final String id;
  final String address;
  final double lat;
  final double lng;
  final int sequenceOrder;
  final DestinationStatus status;
  final DateTime? arrivedAt;
  final DateTime? completedAt;
  final String? externalId;

  DestinationModel({
    required this.id,
    required this.address,
    required this.lat,
    required this.lng,
    required this.sequenceOrder,
    required this.status,
    this.arrivedAt,
    this.completedAt,
    this.externalId,
  });

  factory DestinationModel.mock({
    required int order,
    required String address,
    required double lat,
    required double lng,
    DestinationStatus status = DestinationStatus.pending,
  }) {
    return DestinationModel(
      id: 'DEST-$order',
      address: address,
      lat: lat,
      lng: lng,
      sequenceOrder: order,
      status: status,
      externalId: 'ORDER-$order',
    );
  }

  String get navigationUrl =>
    'https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=driving';
}
```

#### Enums

```dart
// trip_status.dart
enum TripStatus {
  notStarted,
  inProgress,
  completed,
  cancelled;

  String get label {
    switch (this) {
      case TripStatus.notStarted: return 'Not Started';
      case TripStatus.inProgress: return 'In Progress';
      case TripStatus.completed: return 'Completed';
      case TripStatus.cancelled: return 'Cancelled';
    }
  }

  String get labelAr {
    switch (this) {
      case TripStatus.notStarted: return 'لم يبدأ';
      case TripStatus.inProgress: return 'جاري التنفيذ';
      case TripStatus.completed: return 'مكتمل';
      case TripStatus.cancelled: return 'ملغي';
    }
  }
}

// destination_status.dart
enum DestinationStatus {
  pending,
  arrived,
  completed,
  failed;

  String get label {
    switch (this) {
      case DestinationStatus.pending: return 'Pending';
      case DestinationStatus.arrived: return 'Arrived';
      case DestinationStatus.completed: return 'Completed';
      case DestinationStatus.failed: return 'Failed';
    }
  }

  String get labelAr {
    switch (this) {
      case DestinationStatus.pending: return 'قيد الانتظار';
      case DestinationStatus.arrived: return 'وصل';
      case DestinationStatus.completed: return 'مكتمل';
      case DestinationStatus.failed: return 'فشل';
    }
  }
}
```

---

### 2. Mock Service

#### mock_trip_service.dart

```dart
class MockTripService {
  // Mock data for today's trips
  static List<TripModel> getTodaysTrips() {
    return [
      TripModel.mock(
        id: 'TRIP-001',
        status: TripStatus.notStarted,
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
      TripModel.mock(
        id: 'TRIP-002',
        status: TripStatus.inProgress,
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
  }

  // Simulate network delay
  static Future<List<TripModel>> fetchTodaysTrips() async {
    await Future.delayed(Duration(seconds: 1));
    return getTodaysTrips();
  }

  static Future<TripModel> fetchTripDetails(String tripId) async {
    await Future.delayed(Duration(milliseconds: 500));
    return getTodaysTrips().firstWhere((trip) => trip.id == tripId);
  }

  static Future<void> startTrip(String tripId) async {
    await Future.delayed(Duration(milliseconds: 300));
    // In real app, this would update backend
    print('Trip $tripId started');
  }

  static Future<void> markDestinationArrived(String tripId, String destinationId) async {
    await Future.delayed(Duration(milliseconds: 300));
    print('Destination $destinationId marked as arrived');
  }

  static Future<void> markDestinationCompleted(String tripId, String destinationId) async {
    await Future.delayed(Duration(milliseconds: 300));
    print('Destination $destinationId marked as completed');
  }
}
```

---

### 3. State Management (Riverpod)

#### trips_provider.dart

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/mock_trip_service.dart';
import '../data/models/trip_model.dart';

final tripsProvider = FutureProvider<List<TripModel>>((ref) async {
  return MockTripService.fetchTodaysTrips();
});

final tripDetailsProvider = FutureProvider.family<TripModel, String>((ref, tripId) async {
  return MockTripService.fetchTripDetails(tripId);
});
```

---

### 4. UI Screens

#### trips_list_screen.dart

```dart
class TripsListScreen extends ConsumerWidget {
  const TripsListScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tripsAsync = ref.watch(tripsProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text('Today\'s Trips'),
        centerTitle: true,
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
                  Text('No trips for today', style: TextStyle(fontSize: 16)),
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
          child: Text('Error loading trips: $error'),
        ),
      ),
    );
  }
}
```

#### trip_details_screen.dart

```dart
class TripDetailsScreen extends ConsumerWidget {
  final String tripId;

  const TripDetailsScreen({Key? key, required this.tripId}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tripAsync = ref.watch(tripDetailsProvider(tripId));

    return Scaffold(
      appBar: AppBar(
        title: Text('Trip Details'),
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
                      style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                    SizedBox(height: 8),
                    Text('Trip ID: ${trip.id}'),
                    SizedBox(height: 4),
                    Row(
                      children: [
                        Icon(Icons.location_on, size: 16),
                        SizedBox(width: 4),
                        Text('${trip.completedDestinationsCount}/${trip.totalDestinations} completed'),
                      ],
                    ),
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
        error: (error, stack) => Center(child: Text('Error: $error')),
      ),
    );
  }
}
```

---

### 5. Widgets

#### trip_card.dart

```dart
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
              SizedBox(height: 8),
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
        style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}
```

#### destination_card.dart

```dart
class DestinationCard extends ConsumerWidget {
  final DestinationModel destination;
  final String tripId;

  const DestinationCard({
    Key? key,
    required this.destination,
    required this.tripId,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Card(
      margin: EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  child: Text('${destination.sequenceOrder}'),
                  backgroundColor: _getStatusColor(destination.status),
                ),
                SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        destination.address,
                        style: TextStyle(fontWeight: FontWeight.w600),
                      ),
                      SizedBox(height: 4),
                      Text(
                        destination.status.label,
                        style: TextStyle(
                          color: _getStatusColor(destination.status),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            SizedBox(height: 12),
            _buildActions(context, ref),
          ],
        ),
      ),
    );
  }

  Widget _buildActions(BuildContext context, WidgetRef ref) {
    if (destination.status == DestinationStatus.completed) {
      return Row(
        children: [
          Icon(Icons.check_circle, color: Colors.green, size: 16),
          SizedBox(width: 4),
          Text('Completed', style: TextStyle(color: Colors.green)),
        ],
      );
    }

    return Wrap(
      spacing: 8,
      children: [
        ElevatedButton.icon(
          onPressed: () async {
            // Open Google Maps
            final url = destination.navigationUrl;
            // In real app: await launchUrl(Uri.parse(url));
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Opening navigation to ${destination.address}')),
            );
          },
          icon: Icon(Icons.navigation, size: 16),
          label: Text('Navigate'),
          style: ElevatedButton.styleFrom(backgroundColor: Colors.blue),
        ),
        if (destination.status == DestinationStatus.pending)
          OutlinedButton.icon(
            onPressed: () async {
              await MockTripService.markDestinationArrived(tripId, destination.id);
              ref.invalidate(tripDetailsProvider(tripId));
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Marked as arrived')),
              );
            },
            icon: Icon(Icons.location_on, size: 16),
            label: Text('Arrived'),
          ),
        if (destination.status == DestinationStatus.arrived)
          ElevatedButton.icon(
            onPressed: () async {
              await MockTripService.markDestinationCompleted(tripId, destination.id);
              ref.invalidate(tripDetailsProvider(tripId));
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Delivery completed!')),
              );
            },
            icon: Icon(Icons.check, size: 16),
            label: Text('Complete'),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
          ),
      ],
    );
  }

  Color _getStatusColor(DestinationStatus status) {
    switch (status) {
      case DestinationStatus.pending:
        return Colors.grey;
      case DestinationStatus.arrived:
        return Colors.orange;
      case DestinationStatus.completed:
        return Colors.green;
      case DestinationStatus.failed:
        return Colors.red;
    }
  }
}
```

---

## ✅ Completion Checklist

### Phase 1: Models & Mock Data (90 min)
- [ ] Create `TripModel` with mock factory
- [ ] Create `DestinationModel` with mock factory
- [ ] Create `TripStatus` enum
- [ ] Create `DestinationStatus` enum
- [ ] Create `MockTripService` with 2 sample trips

### Phase 2: State Management (60 min)
- [ ] Create `tripsProvider`
- [ ] Create `tripDetailsProvider`
- [ ] Test providers with mock data

### Phase 3: UI Screens (180 min)
- [ ] Enhance `TripsListScreen` with real data
- [ ] Create `TripCard` widget
- [ ] Enhance `TripDetailsScreen` with destinations
- [ ] Create `DestinationCard` widget with actions
- [ ] Add navigation between screens

### Phase 4: Actions & Navigation (90 min)
- [ ] Implement "Mark Arrived" action
- [ ] Implement "Mark Completed" action
- [ ] Implement "Navigate" (Google Maps link)
- [ ] Add refresh functionality
- [ ] Add loading states

### Phase 5: Polish (30 min)
- [ ] Add Arabic translations for new strings
- [ ] Test RTL layout
- [ ] Add empty states
- [ ] Test on both Android and iOS simulators

---

## 🎯 Success Criteria

By end of day, you should have:
- ✅ Working trips list screen with 2 mock trips
- ✅ Trip details screen showing destinations
- ✅ Ability to mark destinations as arrived/completed
- ✅ Navigation button that shows Google Maps URL
- ✅ Proper status colors and indicators
- ✅ Arabic support (RTL layout works)
- ✅ App runs on simulator without crashes

---

## 🚫 What NOT to Do

**Do NOT:**
- ❌ Connect to backend APIs (use mock data only)
- ❌ Implement actual GPS tracking
- ❌ Add authentication (skip login screen for now)
- ❌ Store data persistently (in-memory only)
- ❌ Add photo/signature capture (future feature)
- ❌ Implement real Google Maps navigation (just open URL)

---

## 🧪 Testing Approach

### Manual Testing Checklist
- [ ] Open app → See 2 trips in list
- [ ] Tap trip → See destinations
- [ ] Tap "Navigate" → See snackbar message
- [ ] Tap "Arrived" → Status updates to orange
- [ ] Tap "Complete" → Status updates to green
- [ ] Pull to refresh → Data reloads
- [ ] Test on Arabic locale → RTL works

### Test Data Scenarios
1. **Trip 1**: Not started, 3 pending destinations
2. **Trip 2**: In progress, 1 completed, 1 arrived

---

## 📱 Screenshots to Aim For

**Trips List:**
```
┌─────────────────────────┐
│ Today's Trips      🔄   │
├─────────────────────────┤
│ ┌─────────────────────┐ │
│ │ TRIP-001   [Not St] │ │
│ │ Sweets Factory ERP  │ │
│ │ 📍 3 dest  ✓ 0 done │ │
│ └─────────────────────┘ │
│ ┌─────────────────────┐ │
│ │ TRIP-002 [Progress] │ │
│ │ Sweets Factory ERP  │ │
│ │ 📍 2 dest  ✓ 1 done │ │
│ └─────────────────────┘ │
└─────────────────────────┘
```

**Trip Details:**
```
┌─────────────────────────┐
│ ← Trip Details          │
├─────────────────────────┤
│ Sweets Factory ERP      │
│ Trip ID: TRIP-001       │
│ 📍 0/3 completed        │
├─────────────────────────┤
│ ┌─────────────────────┐ │
│ │ ① Rainbow St        │ │
│ │    Pending          │ │
│ │ [Navigate] [Arrived]│ │
│ └─────────────────────┘ │
│ ┌─────────────────────┐ │
│ │ ② King Abdullah     │ │
│ │    Pending          │ │
│ │ [Navigate] [Arrived]│ │
│ └─────────────────────┘ │
└─────────────────────────┘
```

---

## 🔧 Useful Commands

```bash
# Run app
flutter run

# Run on specific device
flutter run -d chrome  # Web
flutter run -d iphone  # iOS Simulator

# Hot reload while developing
r  # (in running app)

# Check for issues
flutter analyze

# Format code
flutter format lib/

# Generate localization
flutter gen-l10n
```

---

## 💡 Implementation Tips

1. **Start with models** - Get data structure right first
2. **Use mock service** - Makes development faster
3. **Test frequently** - Hot reload is your friend
4. **Follow existing patterns** - Look at auth screens for examples
5. **Keep it simple** - MVP means minimal features that work

---

## 📚 Reference Files

Look at these existing files for patterns:
- `lib/features/auth/presentation/login_screen.dart` - Screen structure
- `lib/core/auth/auth_provider.dart` - Riverpod pattern
- `lib/l10n/app_ar.arb` - Arabic translations example

---

## 🎯 Next Steps (Future - Not Today)

After backend is ready:
1. Replace `MockTripService` with real API calls
2. Add authentication flow
3. Implement GPS tracking for actual KM
4. Add photo/signature capture
5. Add offline support with local database

---

**Good luck! Focus on making it work with mock data first. Polish can come later.**
