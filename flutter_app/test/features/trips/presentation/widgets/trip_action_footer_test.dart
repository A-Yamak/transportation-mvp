import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:driver_app/features/trips/data/models/trip_model.dart';
import 'package:driver_app/features/trips/data/models/trip_status.dart';
import 'package:driver_app/features/trips/presentation/widgets/trip_action_footer.dart';

void main() {
  group('TripActionFooter Tests', () {
    testWidgets('renders when trip not started',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-1',
        status: TripStatus.notStarted,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Expanded(child: Container()),
                TripActionFooter(
                  trip: trip,
                  tripId: 'trip-1',
                ),
              ],
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooter), findsOneWidget);
    });

    testWidgets('renders when trip in progress',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-2',
        status: TripStatus.inProgress,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Expanded(child: Container()),
                TripActionFooter(
                  trip: trip,
                  tripId: 'trip-2',
                ),
              ],
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooter), findsOneWidget);
    });

    testWidgets('renders when trip completed',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-3',
        status: TripStatus.completed,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Expanded(child: Container()),
                TripActionFooter(
                  trip: trip,
                  tripId: 'trip-3',
                ),
              ],
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooter), findsOneWidget);
    });

    testWidgets('displays with callback handlers',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-4',
        status: TripStatus.inProgress,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Expanded(child: Container()),
                TripActionFooter(
                  trip: trip,
                  tripId: 'trip-4',
                  onTripStarted: () {},
                  onTripCompleted: () {},
                ),
              ],
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooter), findsOneWidget);
    });

    testWidgets('renders compact variant',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-5',
        status: TripStatus.inProgress,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Column(
              children: [
                Expanded(child: Container()),
                TripActionFooterCompact(
                  trip: trip,
                  tripId: 'trip-5',
                ),
              ],
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooterCompact), findsOneWidget);
    });

    testWidgets('renders with ProviderScope',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        id: 'trip-6',
        status: TripStatus.inProgress,
        destinations: [],
      );

      // Act
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp(
            home: Scaffold(
              body: Column(
                children: [
                  Expanded(child: Container()),
                  TripActionFooter(
                    trip: trip,
                    tripId: 'trip-6',
                  ),
                ],
              ),
            ),
          ),
        ),
      );

      // Assert
      expect(find.byType(TripActionFooter), findsOneWidget);
    });
  });
}
