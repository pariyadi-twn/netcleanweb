class ScanPayload {
  const ScanPayload._();

  static final RegExp _whitespace = RegExp(r'\s+');
  static final RegExp _url = RegExp(
    r'^[a-z][a-z0-9+.-]*://',
    caseSensitive: false,
  );

  static String? normalize(String? raw) {
    if (raw == null) {
      return null;
    }
    final trimmed = raw.trim();
    if (trimmed.isEmpty || trimmed.length > 100) {
      return null;
    }
    if (_url.hasMatch(trimmed)) {
      return null;
    }
    final normalized = trimmed.replaceAll(_whitespace, '').toUpperCase();
    if (normalized.isEmpty || normalized.length > 100) {
      return null;
    }
    return normalized;
  }

  static bool matchesLabel(String? raw, String? label) {
    final normalizedRaw = normalize(raw);
    final normalizedLabel = normalize(label);
    return normalizedRaw != null &&
        normalizedLabel != null &&
        normalizedRaw == normalizedLabel;
  }
}
