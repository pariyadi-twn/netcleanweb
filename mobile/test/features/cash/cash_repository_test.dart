import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/features/cash/data/cash_repository.dart';

const _baseUrl = 'https://example.test/api/v1';

void main() {
  test('loads registers and their open session within tenant scope', () async {
    final client = MockClient((request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/api/v1/branches/branch-1/cash-registers');
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      return http.Response(
        jsonEncode({
          'data': [
            {
              'id': 'register-1',
              'code': 'KASIR-1',
              'is_active': true,
              'current_session': {
                'id': 'session-1',
                'cash_register_id': 'register-1',
                'status': 'open',
                'opening_rupiah': 200000,
                'ledger_rupiah': 50000,
                'expected_rupiah': 250000,
                'counted_closing_rupiah': null,
                'difference_rupiah': null,
                'discrepancy_reason': null,
              },
            },
          ],
        }),
        200,
      );
    });
    final repository = CashRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final registers = await repository.registers(
      tenantId: 'tenant-1',
      branchId: 'branch-1',
    );

    expect(registers.single.code, 'KASIR-1');
    expect(registers.single.currentSession?.id, 'session-1');
    expect(registers.single.currentSession?.expectedRupiah, 250000);
  });

  test('opens and closes a shift with the expected payloads', () async {
    var requestCount = 0;
    final client = MockClient((request) async {
      requestCount++;
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      if (request.url.path.endsWith('/sessions')) {
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        expect(body['opening_rupiah'], 200000);
        return http.Response(
          jsonEncode({
            'data': {
              'id': 'session-1',
              'cash_register_id': 'register-1',
              'status': 'open',
              'opening_rupiah': 200000,
              'ledger_rupiah': 0,
              'expected_rupiah': 200000,
            },
          }),
          201,
        );
      }
      expect(request.url.path, '/api/v1/cash-sessions/session-1/close');
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      expect(body['counted_closing_rupiah'], 198000);
      expect(body['reason'], 'Kurang Rp2.000');
      return http.Response(
        jsonEncode({
          'data': {
            'id': 'session-1',
            'cash_register_id': 'register-1',
            'status': 'closed',
            'opening_rupiah': 200000,
            'ledger_rupiah': 0,
            'expected_rupiah': 200000,
            'counted_closing_rupiah': 198000,
            'difference_rupiah': -2000,
          },
        }),
        200,
      );
    });
    final repository = CashRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final opened = await repository.openSession(
      tenantId: 'tenant-1',
      registerId: 'register-1',
      openingRupiah: 200000,
    );
    final closed = await repository.closeSession(
      tenantId: 'tenant-1',
      sessionId: 'session-1',
      countedClosingRupiah: 198000,
      reason: 'Kurang Rp2.000',
    );

    expect(requestCount, 2);
    expect(opened.status, 'open');
    expect(closed.status, 'closed');
    expect(closed.differenceRupiah, -2000);
  });
}
