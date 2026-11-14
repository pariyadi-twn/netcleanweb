import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/features/customers/data/customer_repository.dart';

void main() {
  test('searches customers inside the tenant scope', () async {
    final client = MockClient((request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/api/v1/customers');
      expect(request.url.queryParameters['q'], 'ani');
      expect(request.url.queryParameters['per_page'], '25');
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      return http.Response(
        jsonEncode({
          'data': [
            {
              'id': 'customer-1',
              'name': 'Ani',
              'normalized_phone': '628123456789',
              'is_active': true,
            },
          ],
          'meta': {'next_cursor': null},
        }),
        200,
      );
    });
    final repository = CustomerRepository(
      ApiClient(client: client, baseUrl: 'https://example.test/api/v1'),
    );

    final page = await repository.search(tenantId: 'tenant-1', query: 'ani');

    expect(page.items.single.id, 'customer-1');
    expect(page.items.single.name, 'Ani');
    expect(page.items.single.phone, '628123456789');
    expect(page.nextCursor, isNull);
  });

  test('creates a customer with the tenant scope', () async {
    final client = MockClient((request) async {
      expect(request.method, 'POST');
      expect(request.url.path, '/api/v1/customers');
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(body['name'], 'Budi');
      expect(body['phone'], '0812');
      return http.Response(
        jsonEncode({
          'data': {
            'id': 'customer-2',
            'name': 'Budi',
            'normalized_phone': '62812',
            'is_active': true,
          },
        }),
        201,
      );
    });
    final repository = CustomerRepository(
      ApiClient(client: client, baseUrl: 'https://example.test/api/v1'),
    );

    final customer = await repository.create(
      tenantId: 'tenant-1',
      name: 'Budi',
      phone: '0812',
    );

    expect(customer.id, 'customer-2');
    expect(customer.phone, '62812');
  });
}
