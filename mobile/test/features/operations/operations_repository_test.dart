import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:netclean_staff/core/network/api_client.dart';
import 'package:netclean_staff/features/operations/data/operations_repository.dart';
import 'package:netclean_staff/features/operations/domain/operations_models.dart';

const _baseUrl = 'https://example.test/api/v1';

void main() {
  test('looks up an order by number and parses its bags', () async {
    final client = MockClient((request) async {
      expect(request.method, 'GET');
      expect(request.url.path, '/api/v1/orders');
      expect(request.url.queryParameters['order_number'], 'ORD-001');
      expect(request.url.queryParameters['per_page'], '1');
      expect(request.headers['X-Tenant-ID'], 'tenant-1');
      return http.Response(
        jsonEncode({
          'data': [
            {
              'order_id': 'order-1',
              'order_number': 'ORD-001',
              'operational_status': 'accepted',
              'total_rupiah': 28000,
              'paid_rupiah': 28000,
              'balance_due_rupiah': 0,
              'due_at_current': '2026-11-16T12:00:00Z',
              'bags': [
                {
                  'id': 'bag-1',
                  'custody_status': 'at_location',
                  'current_location_id': null,
                  'version': 1,
                },
              ],
            },
          ],
        }),
        200,
      );
    });
    final repository = OperationsRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final order = await repository.lookupOrder(
      tenantId: 'tenant-1',
      orderNumber: 'ORD-001',
    );

    expect(order?.orderId, 'order-1');
    expect(order?.balanceDueRupiah, 0);
    expect(order?.bags.single.custodyStatus, 'at_location');
  });

  test('lists, creates, rewash, and resolves complaints', () async {
    final requests = <String>[];
    final client = MockClient((request) async {
      requests.add('${request.method} ${request.url.path}');
      if (request.method == 'GET') {
        return http.Response(
          jsonEncode({
            'data': [
              {
                'complaint_id': 'complaint-1',
                'kind': 'rewash',
                'status': 'open',
                'description': 'Noda belum hilang',
                'resolution': null,
                'rewash_attempt_no': null,
                'order_line_id': 'line-1',
                'opened_at': '2026-11-16T10:00:00Z',
              },
            ],
          }),
          200,
        );
      }
      final body = request.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(request.body) as Map<String, dynamic>;
      if (request.url.path.endsWith('/complaints')) {
        expect(body['kind'], 'rewash');
        expect(body['description'], 'Noda belum hilang');
        return http.Response('{}', 201);
      }
      if (request.url.path.endsWith('/rewash')) {
        return http.Response('{}', 201);
      }
      if (request.url.path.endsWith('/resolution')) {
        expect(body['status'], 'resolved');
        expect(body['resolution'], 'Sudah dicuci ulang');
        return http.Response('{}', 200);
      }
      return http.Response('{}', 404);
    });
    final repository = OperationsRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final complaints = await repository.complaints(
      tenantId: 'tenant-1',
      orderId: 'order-1',
    );
    await repository.createComplaint(
      tenantId: 'tenant-1',
      orderId: 'order-1',
      kind: 'rewash',
      description: 'Noda belum hilang',
    );
    await repository.rewashComplaint(
      tenantId: 'tenant-1',
      complaintId: 'complaint-1',
    );
    await repository.resolveComplaint(
      tenantId: 'tenant-1',
      complaintId: 'complaint-1',
      status: 'resolved',
      resolution: 'Sudah dicuci ulang',
    );

    expect(complaints.single.kind, 'rewash');
    expect(complaints.single.status, 'open');
    expect(requests, hasLength(4));
  });

  test('sends production, QC, readiness, and handover payloads', () async {
    final requests = <String>[];
    final client = MockClient((request) async {
      requests.add('${request.method} ${request.url.path}');
      final body = request.body.isEmpty
          ? <String, dynamic>{}
          : jsonDecode(request.body) as Map<String, dynamic>;
      if (request.url.path.endsWith('/receive')) {
        expect(body['location_id'], 'location-1');
        expect(body['expected_version'], 1);
        return http.Response('{}', 201);
      }
      if (request.url.path.endsWith('/production-tasks')) {
        expect(body['location_id'], 'location-1');
        return http.Response('{}', 201);
      }
      if (request.url.path.endsWith('/transitions')) {
        expect(body['expected_version'], 2);
        expect(body['to_status'], 'in_progress');
        return http.Response(
          jsonEncode({
            'data': {
              'task_id': 'task-1',
              'order_id': 'order-1',
              'order_number': 'ORD-001',
              'bag_id': 'bag-1',
              'service_name': 'Kiloan UAT',
              'step_code': 'washing',
              'status': 'in_progress',
              'due_at': '2026-11-16T12:00:00Z',
              'overdue': false,
              'version': 3,
              'requires_qc': false,
              'is_required': true,
            },
          }),
          200,
        );
      }
      if (request.url.path.endsWith('/qc-results')) {
        expect(body['outcome'], 'failed');
        expect(body['notes'], 'Noda belum hilang');
        expect(body['checklist'], isA<Map<String, dynamic>>());
        return http.Response('{}', 200);
      }
      if (request.url.path.endsWith('/readiness')) {
        return http.Response(
          jsonEncode({
            'data': {
              'order_id': 'order-1',
              'order_number': 'ORD-001',
              'operational_status': 'ready',
              'can_ready': true,
              'partially_ready': false,
              'contents': [
                {
                  'bag_content_id': 'content-1',
                  'ready': true,
                  'qc_passed': true,
                  'qc_required': true,
                  'open_required_tasks': 0,
                },
              ],
            },
          }),
          200,
        );
      }
      if (request.url.path.endsWith('/handovers')) {
        expect(body['bag_ids'], ['bag-1']);
        expect(body['location_id'], 'location-1');
        expect(body['recipient_name'], 'Ani');
        expect(body['verification_method'], 'receipt');
        expect(body['evidence_notes'], 'Nota fisik ditunjukkan');
        return http.Response(
          jsonEncode({
            'data': {
              'handover': {'handover_id': 'handover-1'},
              'order': {'operational_status': 'completed'},
            },
          }),
          201,
        );
      }
      return http.Response('{}', 404);
    });
    final repository = OperationsRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );
    final task = ProductionTaskSummary(
      taskId: 'task-1',
      orderId: 'order-1',
      orderNumber: 'ORD-001',
      bagId: 'bag-1',
      serviceName: 'Kiloan UAT',
      stepCode: 'washing',
      status: 'queued',
      dueAt: DateTime.utc(2026, 11, 16, 12),
      overdue: false,
      version: 2,
      requiresQc: false,
      isRequired: true,
    );

    await repository.receiveBag(
      tenantId: 'tenant-1',
      orderId: 'order-1',
      bagId: 'bag-1',
      locationId: 'location-1',
      expectedVersion: 1,
    );
    await repository.planTasks(
      tenantId: 'tenant-1',
      orderId: 'order-1',
      locationId: 'location-1',
    );
    final transitioned = await repository.transition(
      tenantId: 'tenant-1',
      task: task,
      toStatus: 'in_progress',
    );
    await repository.recordQc(
      tenantId: 'tenant-1',
      task: task,
      outcome: 'failed',
      notes: 'Noda belum hilang',
    );
    final readiness = await repository.readiness(
      tenantId: 'tenant-1',
      orderId: 'order-1',
    );
    final handover = await repository.handover(
      tenantId: 'tenant-1',
      orderId: 'order-1',
      bagIds: const ['bag-1'],
      locationId: 'location-1',
      recipientName: 'Ani',
      evidenceNotes: 'Nota fisik ditunjukkan',
    );

    expect(requests, hasLength(6));
    expect(transitioned.status, 'in_progress');
    expect(readiness.canReady, isTrue);
    expect(readiness.contents.single.qcPassed, isTrue);
    expect(handover.orderStatus, 'completed');
  });

  test('completes all production stages in simple mode', () async {
    late http.Request captured;
    final client = MockClient((request) async {
      captured = request;
      return http.Response(
        jsonEncode({
          'data': {
            'order_id': 'order-1',
            'order_number': 'ORD-001',
            'operational_status': 'ready',
            'can_ready': true,
            'partially_ready': false,
            'contents': [
              {
                'bag_content_id': 'content-1',
                'ready': true,
                'qc_passed': true,
                'qc_required': false,
                'open_required_tasks': 0,
              },
            ],
          },
        }),
        200,
      );
    });
    final repository = OperationsRepository(
      ApiClient(client: client, baseUrl: _baseUrl),
    );

    final readiness = await repository.completeProduction(
      tenantId: 'tenant-1',
      orderId: 'order-1',
    );

    expect(captured.method, 'POST');
    expect(captured.url.path, '/api/v1/orders/order-1/production-complete');
    expect(captured.headers['X-Tenant-ID'], 'tenant-1');
    expect(readiness.canReady, isTrue);
  });
}
