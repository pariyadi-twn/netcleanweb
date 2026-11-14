import 'dart:convert';
import 'dart:math';

import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import 'api_exception.dart';

typedef TokenProvider = Future<String?> Function();

class ApiClient {
  ApiClient({
    http.Client? client,
    this.baseUrl = AppConfig.apiBaseUrl,
    this.tokenProvider,
  }) : _client = client ?? http.Client();

  final http.Client _client;
  final String baseUrl;
  final TokenProvider? tokenProvider;

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String>? query,
    String? tenantId,
  }) {
    return _send('GET', path, query: query, tenantId: tenantId);
  }

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    String? tenantId,
    String? idempotencyKey,
    bool authenticated = true,
  }) {
    return _send(
      'POST',
      path,
      body: body,
      query: query,
      tenantId: tenantId,
      idempotencyKey: idempotencyKey,
      authenticated: authenticated,
    );
  }

  Future<ApiPage> getPage(
    String path, {
    String? cursor,
    int perPage = 25,
    Map<String, String>? query,
    String? tenantId,
  }) async {
    final params = {...?query, 'per_page': '$perPage'};
    if (cursor != null) {
      params['cursor'] = cursor;
    }
    final payload = await get(path, query: params, tenantId: tenantId);
    final data = payload['data'];
    final meta = payload['meta'];
    return ApiPage(
      items: data is List
          ? data.whereType<Map<String, dynamic>>().toList()
          : const [],
      nextCursor: meta is Map<String, dynamic>
          ? meta['next_cursor'] as String?
          : null,
    );
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    Map<String, String>? query,
    String? tenantId,
    String? idempotencyKey,
    bool authenticated = true,
  }) async {
    final uri = Uri.parse('$baseUrl$path').replace(queryParameters: query);
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Correlation-ID': _correlationId(),
    };
    if (tenantId != null) {
      headers['X-Tenant-ID'] = tenantId;
    }
    if (idempotencyKey != null) {
      headers['Idempotency-Key'] = idempotencyKey;
    }

    if (authenticated && tokenProvider != null) {
      final token = await tokenProvider!();
      if (token != null && token.isNotEmpty) {
        headers['Authorization'] = 'Bearer $token';
      }
    }

    final response = await _client
        .send(
          http.Request(method, uri)
            ..headers.addAll(headers)
            ..body = body == null ? '' : jsonEncode(body),
        )
        .then(http.Response.fromStream);

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body);
    if (response.statusCode >= 400) {
      final error =
          decoded is Map<String, dynamic> &&
              decoded['error'] is Map<String, dynamic>
          ? decoded['error'] as Map<String, dynamic>
          : <String, dynamic>{};
      final fields = <String, List<String>>{};
      final rawFields = error['field_errors'];
      if (rawFields is Map<String, dynamic>) {
        for (final entry in rawFields.entries) {
          if (entry.value is List) {
            fields[entry.key] = (entry.value as List)
                .map((value) => '$value')
                .toList();
          }
        }
      }
      throw ApiException(
        code: '${error['code'] ?? 'HTTP_ERROR'}',
        message: '${error['message'] ?? 'The request could not be completed.'}',
        statusCode: response.statusCode,
        fieldErrors: fields,
        correlationId: response.headers['x-correlation-id'],
        retryAfterSeconds: int.tryParse(response.headers['retry-after'] ?? ''),
      );
    }

    return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
  }

  String _correlationId() {
    final random = Random.secure();
    return List.generate(
      16,
      (_) => random.nextInt(256).toRadixString(16).padLeft(2, '0'),
    ).join();
  }
}

class ApiPage {
  const ApiPage({required this.items, required this.nextCursor});

  final List<Map<String, dynamic>> items;
  final String? nextCursor;
}
