import 'package:flutter/material.dart';

@immutable
class AppStatusColors extends ThemeExtension<AppStatusColors> {
  const AppStatusColors({
    required this.successText,
    required this.successBackground,
    required this.warningText,
    required this.warningBackground,
    required this.dangerText,
    required this.dangerBackground,
    required this.infoText,
    required this.infoBackground,
  });

  static const standard = AppStatusColors(
    successText: Color(0xFF166534),
    successBackground: Color(0xFFF0FDF4),
    warningText: Color(0xFF92400E),
    warningBackground: Color(0xFFFFFBEB),
    dangerText: Color(0xFFB91C1C),
    dangerBackground: Color(0xFFFEF2F2),
    infoText: Color(0xFF1E40AF),
    infoBackground: Color(0xFFEFF6FF),
  );

  final Color successText;
  final Color successBackground;
  final Color warningText;
  final Color warningBackground;
  final Color dangerText;
  final Color dangerBackground;
  final Color infoText;
  final Color infoBackground;

  @override
  AppStatusColors copyWith({
    Color? successText,
    Color? successBackground,
    Color? warningText,
    Color? warningBackground,
    Color? dangerText,
    Color? dangerBackground,
    Color? infoText,
    Color? infoBackground,
  }) {
    return AppStatusColors(
      successText: successText ?? this.successText,
      successBackground: successBackground ?? this.successBackground,
      warningText: warningText ?? this.warningText,
      warningBackground: warningBackground ?? this.warningBackground,
      dangerText: dangerText ?? this.dangerText,
      dangerBackground: dangerBackground ?? this.dangerBackground,
      infoText: infoText ?? this.infoText,
      infoBackground: infoBackground ?? this.infoBackground,
    );
  }

  @override
  AppStatusColors lerp(AppStatusColors? other, double t) {
    if (other == null) {
      return this;
    }

    return AppStatusColors(
      successText: Color.lerp(successText, other.successText, t)!,
      successBackground: Color.lerp(
        successBackground,
        other.successBackground,
        t,
      )!,
      warningText: Color.lerp(warningText, other.warningText, t)!,
      warningBackground: Color.lerp(
        warningBackground,
        other.warningBackground,
        t,
      )!,
      dangerText: Color.lerp(dangerText, other.dangerText, t)!,
      dangerBackground: Color.lerp(
        dangerBackground,
        other.dangerBackground,
        t,
      )!,
      infoText: Color.lerp(infoText, other.infoText, t)!,
      infoBackground: Color.lerp(infoBackground, other.infoBackground, t)!,
    );
  }
}

class AppTheme {
  const AppTheme._();

  static const primary = Color(0xFF1D4ED8);
  static const text = Color(0xFF0F172A);
  static const textSecondary = Color(0xFF475569);
  static const surface = Color(0xFFFFFFFF);
  static const background = Color(0xFFF8FAFC);
  static const inputBorder = Color(0xFF64748B);
  static const divider = Color(0xFFE2E8F0);
  static const controlRadius = 8.0;
  static const containerRadius = 12.0;

  static ThemeData get light {
    const colorScheme = ColorScheme.light(
      primary: primary,
      onPrimary: Colors.white,
      surface: surface,
      onSurface: text,
      error: Color(0xFFB91C1C),
      onError: Colors.white,
    );
    const border = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
      borderSide: BorderSide(color: inputBorder),
    );

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: background,
      dividerColor: divider,
      extensions: const [AppStatusColors.standard],
      textTheme: const TextTheme(
        headlineSmall: TextStyle(
          color: text,
          fontSize: 24,
          fontWeight: FontWeight.w700,
          height: 1.25,
        ),
        titleMedium: TextStyle(
          color: text,
          fontSize: 16,
          fontWeight: FontWeight.w600,
        ),
        bodyLarge: TextStyle(color: text, fontSize: 16, height: 1.5),
        bodyMedium: TextStyle(color: textSecondary, fontSize: 16, height: 1.5),
        labelLarge: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        labelMedium: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: surface,
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      cardTheme: const CardThemeData(
        color: surface,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(containerRadius)),
          side: BorderSide(color: divider),
        ),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        filled: true,
        fillColor: surface,
        border: border,
        enabledBorder: border,
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          borderSide: BorderSide(color: primary, width: 2),
        ),
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 48),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          ),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
      ),
      navigationBarTheme: const NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: Color(0xFFDBEAFE),
        height: 72,
      ),
      navigationRailTheme: const NavigationRailThemeData(
        backgroundColor: surface,
        indicatorColor: Color(0xFFDBEAFE),
        minWidth: 80,
      ),
    );
  }
}
