import 'package:flutter/material.dart';

/// Application theme - optimized for Arabic text
class AppTheme {
  // Brand Colors
  static const Color primaryColor = Color(0xFF1E88E5); // Blue
  static const Color secondaryColor = Color(0xFF43A047); // Green (success)
  static const Color successColor = Color(0xFF43A047); // Green (same as secondary)
  static const Color errorColor = Color(0xFFE53935);
  static const Color warningColor = Color(0xFFFFA000);

  // Neutral Colors
  static const Color backgroundColor = Color(0xFFF5F5F5);
  static const Color surfaceColor = Colors.white;
  static const Color textPrimary = Color(0xFF212121);
  static const Color textSecondary = Color(0xFF757575);

  /// Light theme
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primaryColor,
        brightness: Brightness.light,
        error: errorColor,
      ),

      // Arabic-optimized font
      fontFamily: 'Cairo',
      textTheme: _buildTextTheme(Brightness.light),

      // AppBar
      appBarTheme: const AppBarTheme(
        centerTitle: true,
        elevation: 0,
        backgroundColor: primaryColor,
        foregroundColor: Colors.white,
      ),

      // Buttons - Large for driver use
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          minimumSize: const Size(double.infinity, 56),
          textStyle: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w600,
            fontFamily: 'Cairo',
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),

      // Cards
      cardTheme: CardThemeData(
        elevation: 2,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      ),

      // Input fields
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surfaceColor,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE0E0E0)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Color(0xFFE0E0E0)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: primaryColor, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: errorColor),
        ),
        labelStyle: const TextStyle(fontSize: 16),
        hintStyle: TextStyle(fontSize: 16, color: textSecondary.withOpacity(0.7)),
      ),

      // Scaffold
      scaffoldBackgroundColor: backgroundColor,
    );
  }

  /// Dark theme
  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      colorScheme: ColorScheme.fromSeed(
        seedColor: primaryColor,
        brightness: Brightness.dark,
        error: errorColor,
      ),
      fontFamily: 'Cairo',
      textTheme: _buildTextTheme(Brightness.dark),
    );
  }

  /// Build text theme with Arabic-friendly sizes
  static TextTheme _buildTextTheme(Brightness brightness) {
    final Color textColor = brightness == Brightness.light
        ? textPrimary
        : Colors.white;

    return TextTheme(
      // Display
      displayLarge: TextStyle(
        fontSize: 32,
        fontWeight: FontWeight.bold,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      displayMedium: TextStyle(
        fontSize: 28,
        fontWeight: FontWeight.bold,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      displaySmall: TextStyle(
        fontSize: 24,
        fontWeight: FontWeight.bold,
        color: textColor,
        fontFamily: 'Cairo',
      ),

      // Headlines
      headlineLarge: TextStyle(
        fontSize: 22,
        fontWeight: FontWeight.w600,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      headlineMedium: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.w600,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      headlineSmall: TextStyle(
        fontSize: 18,
        fontWeight: FontWeight.w600,
        color: textColor,
        fontFamily: 'Cairo',
      ),

      // Body - Larger for readability
      bodyLarge: TextStyle(
        fontSize: 18,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      bodyMedium: TextStyle(
        fontSize: 16,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      bodySmall: TextStyle(
        fontSize: 14,
        color: textColor.withOpacity(0.8),
        fontFamily: 'Cairo',
      ),

      // Labels
      labelLarge: TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w500,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      labelMedium: TextStyle(
        fontSize: 14,
        fontWeight: FontWeight.w500,
        color: textColor,
        fontFamily: 'Cairo',
      ),
      labelSmall: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w500,
        color: textColor.withOpacity(0.8),
        fontFamily: 'Cairo',
      ),
    );
  }
}

/// Status colors for trip/destination states
class StatusColors {
  static const Color pending = Color(0xFF9E9E9E);
  static const Color inProgress = Color(0xFF1E88E5);
  static const Color arrived = Color(0xFFFFA000);
  static const Color completed = Color(0xFF43A047);
  static const Color failed = Color(0xFFE53935);
  static const Color cancelled = Color(0xFF757575);
}
