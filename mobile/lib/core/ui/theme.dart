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
    successText: Color(0xFF0F6B4F),
    successBackground: Color(0xFFE7F7EF),
    warningText: Color(0xFF8A5A00),
    warningBackground: Color(0xFFFFF5E0),
    dangerText: Color(0xFFB42318),
    dangerBackground: Color(0xFFFEECE9),
    infoText: Color(0xFF0E5F8A),
    infoBackground: Color(0xFFE8F4FB),
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

  static const primary = Color(0xFF0E7A63);
  static const primaryDeep = Color(0xFF063B32);
  static const primarySoft = Color(0xFFDDF3EB);
  static const accent = Color(0xFFD7F36A);
  static const text = Color(0xFF10231D);
  static const textSecondary = Color(0xFF5B6B65);
  static const surface = Color(0xFFFFFFFF);
  static const surfaceMuted = Color(0xFFF2F6F4);
  static const background = Color(0xFFF5F8F6);
  static const inputFill = Color(0xFFF7FAF8);
  static const inputBorder = Color(0xFFDCE5E0);
  static const divider = Color(0xFFE5ECE8);
  static const controlRadius = 16.0;
  static const containerRadius = 24.0;

  static const heroGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF062F2A), Color(0xFF0B4E43), Color(0xFF0E7A63)],
  );

  static const accentGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFE8FF9A), Color(0xFFD7F36A), Color(0xFF9FE3C8)],
  );

  static const canvasGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFF9FCFA), Color(0xFFF2F7F4), Color(0xFFF7FAF8)],
  );

  static ThemeData get light {
    const colorScheme = ColorScheme.light(
      primary: primary,
      onPrimary: Colors.white,
      primaryContainer: primarySoft,
      onPrimaryContainer: primaryDeep,
      secondary: Color(0xFF3D6B5F),
      onSecondary: Colors.white,
      secondaryContainer: Color(0xFFE3F2EC),
      onSecondaryContainer: Color(0xFF123B31),
      tertiary: Color(0xFF58681E),
      onTertiary: Colors.white,
      tertiaryContainer: Color(0xFFEFF7C8),
      onTertiaryContainer: Color(0xFF222B00),
      surface: surface,
      onSurface: text,
      error: Color(0xFFB42318),
      onError: Colors.white,
      errorContainer: Color(0xFFFEECE9),
      onErrorContainer: Color(0xFF7A1A12),
      outline: Color(0xFF7C8D86),
      outlineVariant: divider,
      shadow: Color(0x1A0B1F19),
      scrim: Color(0x990B1F19),
      inverseSurface: Color(0xFF16241F),
      onInverseSurface: Color(0xFFF1F6F3),
      inversePrimary: Color(0xFF8ADBC8),
      surfaceTint: primary,
    );

    const border = OutlineInputBorder(
      borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
      borderSide: BorderSide(color: inputBorder),
    );

    final textTheme = const TextTheme(
      displaySmall: TextStyle(
        color: text,
        fontSize: 36,
        fontWeight: FontWeight.w800,
        height: 1.08,
        letterSpacing: -1.1,
      ),
      headlineMedium: TextStyle(
        color: text,
        fontSize: 28,
        fontWeight: FontWeight.w800,
        height: 1.14,
        letterSpacing: -0.7,
      ),
      headlineSmall: TextStyle(
        color: text,
        fontSize: 24,
        fontWeight: FontWeight.w800,
        height: 1.18,
        letterSpacing: -0.45,
      ),
      titleLarge: TextStyle(
        color: text,
        fontSize: 20,
        fontWeight: FontWeight.w700,
        height: 1.25,
        letterSpacing: -0.25,
      ),
      titleMedium: TextStyle(
        color: text,
        fontSize: 16,
        fontWeight: FontWeight.w700,
        height: 1.35,
      ),
      titleSmall: TextStyle(
        color: text,
        fontSize: 14,
        fontWeight: FontWeight.w700,
        height: 1.35,
      ),
      bodyLarge: TextStyle(
        color: text,
        fontSize: 16,
        fontWeight: FontWeight.w400,
        height: 1.55,
      ),
      bodyMedium: TextStyle(
        color: textSecondary,
        fontSize: 14,
        fontWeight: FontWeight.w400,
        height: 1.5,
      ),
      bodySmall: TextStyle(
        color: textSecondary,
        fontSize: 12,
        fontWeight: FontWeight.w400,
        height: 1.45,
      ),
      labelLarge: TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w700,
        letterSpacing: 0,
      ),
      labelMedium: TextStyle(
        fontSize: 12.5,
        fontWeight: FontWeight.w600,
        letterSpacing: 0.1,
      ),
      labelSmall: TextStyle(
        fontSize: 10.5,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.1,
      ),
    );

    return ThemeData(
      colorScheme: colorScheme,
      useMaterial3: true,
      scaffoldBackgroundColor: background,
      dividerColor: divider,
      extensions: const [AppStatusColors.standard],
      textTheme: textTheme,
      appBarTheme: const AppBarTheme(
        backgroundColor: background,
        foregroundColor: text,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: 68,
        titleTextStyle: TextStyle(
          color: text,
          fontSize: 18,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
      ),
      cardTheme: const CardThemeData(
        color: surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(containerRadius)),
          side: BorderSide(color: divider),
        ),
      ),
      inputDecorationTheme: const InputDecorationTheme(
        filled: true,
        fillColor: inputFill,
        border: border,
        enabledBorder: border,
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          borderSide: BorderSide(color: primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          borderSide: BorderSide(color: Color(0xFFB42318)),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          borderSide: BorderSide(color: Color(0xFFB42318), width: 1.6),
        ),
        contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: TextStyle(
          color: Color(0xFF8A9A93),
          fontSize: 14,
          fontWeight: FontWeight.w400,
        ),
        labelStyle: TextStyle(
          color: textSecondary,
          fontSize: 14,
          fontWeight: FontWeight.w500,
        ),
        floatingLabelStyle: TextStyle(
          color: primary,
          fontSize: 12.5,
          fontWeight: FontWeight.w700,
        ),
        helperStyle: TextStyle(color: textSecondary, fontSize: 12, height: 1.4),
        prefixIconColor: Color(0xFF7B8C85),
        suffixIconColor: Color(0xFF7B8C85),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          backgroundColor: primary,
          foregroundColor: Colors.white,
          elevation: 0,
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            letterSpacing: 0,
          ),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(48, 52),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          foregroundColor: primaryDeep,
          textStyle: const TextStyle(
            fontSize: 14.5,
            fontWeight: FontWeight.w700,
          ),
          side: const BorderSide(color: Color(0xFFCBD9D3)),
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(controlRadius)),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize: const Size(48, 48),
          foregroundColor: textSecondary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: surfaceMuted,
        selectedColor: primarySoft,
        disabledColor: surfaceMuted,
        side: const BorderSide(color: divider),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        labelStyle: const TextStyle(
          color: textSecondary,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
        secondaryLabelStyle: const TextStyle(
          color: primaryDeep,
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: surface,
        indicatorColor: primarySoft,
        elevation: 0,
        height: 68,
        surfaceTintColor: Colors.transparent,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            color: selected ? primary : textSecondary,
            fontSize: 11.5,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? primary : textSecondary,
            size: 24,
          );
        }),
      ),
      navigationRailTheme: const NavigationRailThemeData(
        backgroundColor: Colors.transparent,
        indicatorColor: primarySoft,
        selectedIconTheme: IconThemeData(color: primary, size: 24),
        unselectedIconTheme: IconThemeData(color: textSecondary, size: 24),
        selectedLabelTextStyle: TextStyle(
          color: primary,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
        unselectedLabelTextStyle: TextStyle(
          color: textSecondary,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
        minWidth: 84,
      ),
      dialogTheme: const DialogThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(28)),
        ),
        titleTextStyle: TextStyle(
          color: text,
          fontSize: 20,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.3,
        ),
        contentTextStyle: TextStyle(
          color: textSecondary,
          fontSize: 14,
          height: 1.5,
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        backgroundColor: Color(0xFF10231D),
        contentTextStyle: TextStyle(color: Colors.white, fontSize: 14),
        actionTextColor: accent,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(16)),
        ),
        insetPadding: EdgeInsets.fromLTRB(16, 0, 16, 16),
      ),
      dividerTheme: const DividerThemeData(
        color: divider,
        thickness: 1,
        space: 24,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: primary,
        linearTrackColor: primarySoft,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
      ),
      textSelectionTheme: const TextSelectionThemeData(
        cursorColor: primary,
        selectionColor: primarySoft,
        selectionHandleColor: primary,
      ),
    );
  }
}
