import '../../../core/network/api_client.dart';
import '../domain/customer_summary.dart';

class CustomerRepository {
  CustomerRepository(this._api);

  final ApiClient _api;

  Future<CustomerPage> search({
    required String tenantId,
    String query = '',
    String? cursor,
  }) async {
    final page = await _api.getPage(
      '/customers',
      query: {'q': query},
      cursor: cursor,
      perPage: 25,
      tenantId: tenantId,
    );

    return CustomerPage(
      items: page.items.map(CustomerSummary.fromJson).toList(growable: false),
      nextCursor: page.nextCursor,
    );
  }

  Future<CustomerSummary> create({
    required String tenantId,
    required String name,
    String? phone,
  }) async {
    final payload = await _api.post(
      '/customers',
      tenantId: tenantId,
      body: {
        'name': name,
        if (phone != null && phone.trim().isNotEmpty) 'phone': phone.trim(),
      },
    );
    final data = payload['data'];
    if (data is! Map<String, dynamic>) {
      throw StateError('Customer response is missing data.');
    }

    return CustomerSummary.fromJson(data);
  }
}

class CustomerPage {
  const CustomerPage({required this.items, this.nextCursor});

  final List<CustomerSummary> items;
  final String? nextCursor;
}
