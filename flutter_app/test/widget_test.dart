// Basic Flutter widget test for the Driver App
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:driver_app/app.dart';

void main() {
  testWidgets('App renders without crashing', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(
      const ProviderScope(
        child: DriverApp(),
      ),
    );

    // Give the app time to initialize
    await tester.pump();

    // Verify the app starts (should show login or main screen)
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
