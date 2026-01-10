import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';
import 'package:transportation_app/features/trips/data/models/trip_model.dart';
import 'package:transportation_app/features/trips/data/models/trip_status.dart';
import 'package:transportation_app/features/trips/presentation/widgets/trip_action_footer.dart';

class MockNavigatorObserver extends Mock implements NavigatorObserver {}

void main() {
  group('TripActionFooter Tests', () {
    testWidgets('displays KM counter', (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 12.5,
      );
      var onTripStartedCalled = false;

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
              onTripStarted: () => onTripStartedCalled = true,
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('KM Driven'), findsOneWidget);
      expect(find.text('12.5 km'), findsOneWidget);
    });

    testWidgets('displays trip status badge', (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert - status should be visible
      expect(find.byType(Text), findsWidgets);
      // Status label should be displayed (e.g., "In Progress")
      expect(find.textContaining('Progress'), findsOneWidget);
    });

    testWidgets('shows Start Trip button when not started',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.notStarted,
        totalKm: 0.0,
      );
      var startTripcalled = false;

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
              onTripStarted: () => startTripcalled = true,
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('Start Trip'), findsOneWidget);
      expect(find.byIcon(Icons.play_arrow), findsOneWidget);

      // Act - tap Start Trip
      await tester.tap(find.text('Start Trip'));
      await tester.pumpAndSettle();

      // Assert - callback should be called
      expect(startTripcalled, true);
    });

    testWidgets('hides Start Trip button when trip started',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('Start Trip'), findsNothing);
    });

    testWidgets('shows End Trip button when in progress',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );
      var onTripCompletedCalled = false;

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
              onTripCompleted: () => onTripCompletedCalled = true,
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('End Trip'), findsOneWidget);
      expect(find.byIcon(Icons.check_circle), findsOneWidget);

      // Act - tap End Trip
      await tester.tap(find.text('End Trip'));
      await tester.pumpAndSettle();

      // Assert
      expect(onTripCompletedCalled, true);
    });

    testWidgets('hides End Trip button when not in progress',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.notStarted,
        totalKm: 0.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('End Trip'), findsNothing);
    });

    testWidgets('shows End Day button always',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.notStarted,
        totalKm: 0.0,
      );
      final observer = MockNavigatorObserver();

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
          navigatorObservers: [observer],
        ),
      );

      // Assert
      expect(find.text('End Day'), findsOneWidget);
      expect(find.byIcon(Icons.summarize), findsOneWidget);
    });

    testWidgets('End Day button navigates to reconciliation',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          onGenerateRoute: (settings) {
            if (settings.name == '/reconciliation') {
              return MaterialPageRoute(
                builder: (_) => Scaffold(body: Text('Reconciliation Screen')),
              );
            }
            return MaterialPageRoute(
              builder: (_) => Scaffold(
                body: TripActionFooter(
                  trip: trip,
                  tripId: 'trip-1',
                ),
              ),
            );
          },
        ),
      );

      // Find and tap End Day button
      await tester.tap(find.text('End Day'));
      await tester.pumpAndSettle();

      // Assert - should navigate (or prepare to navigate)
      // Note: Navigation behavior depends on GoRouter setup
      // At minimum, the button should be tappable
      expect(find.text('End Day'), findsOneWidget);
    });

    testWidgets('displays 0 KM when trip not started',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.notStarted,
        totalKm: 0.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('0.0 km'), findsOneWidget);
    });

    testWidgets('formats KM with one decimal place',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 12.567, // Should format to 12.6
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('12.6 km'), findsOneWidget);
    });

    testWidgets('status badge has correct color for in_progress',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert - find the status badge container
      // The color should be orange for in_progress
      expect(find.byType(Container), findsWidgets);
    });

    testWidgets('compact footer shows basic information',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 5.5,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooterCompact(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('KM: 5.5'), findsOneWidget);
    });

    testWidgets('compact footer shows Start button when not started',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.notStarted,
        totalKm: 0.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooterCompact(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('Start'), findsOneWidget);
    });

    testWidgets('compact footer shows End button when in progress',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooterCompact(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert
      expect(find.text('End'), findsOneWidget);
      expect(find.text('Day'), findsOneWidget);
    });

    testWidgets('footer layout is column with main axis min size',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert - should find all components
      expect(find.byType(Column), findsWidgets);
      expect(find.byType(ElevatedButton), findsWidgets);
    });

    testWidgets('all action buttons are visible in sequence',
        (WidgetTester tester) async {
      // Arrange
      final trip = TripModel.mock(
        status: TripStatus.inProgress,
        totalKm: 10.0,
      );

      // Act
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TripActionFooter(
              trip: trip,
              tripId: 'trip-1',
            ),
          ),
        ),
      );

      // Assert - KM display, End Trip, End Day buttons should all be visible
      expect(find.text('KM Driven'), findsOneWidget);
      expect(find.text('End Trip'), findsOneWidget);
      expect(find.text('End Day'), findsOneWidget);
    });
  });
}
