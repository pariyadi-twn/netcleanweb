import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/scan/scan_payload.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../../orders/domain/order_models.dart';
import '../../orders/presentation/receipt_preview_screen.dart';
import '../../payments/presentation/order_payment_sheet.dart';
import '../domain/operations_models.dart';

class OperationsScreen extends ConsumerStatefulWidget {
  const OperationsScreen({super.key});

  @override
  ConsumerState<OperationsScreen> createState() => _OperationsScreenState();
}

class _OperationsScreenState extends ConsumerState<OperationsScreen> {
  final _orderNumber = TextEditingController();
  OrderLookup? _order;
  List<ProductionTaskSummary> _tasks = const [];
  List<ComplaintSummary> _complaints = const [];
  OrderReadiness? _readiness;
  HandoverSummary? _handover;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _orderNumber.dispose();
    super.dispose();
  }

  String? get _tenantId =>
      ref.read(sessionControllerProvider).asData?.value?.tenantId;

  String? get _locationId {
    final locations = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.locationIds;
    if (locations == null || locations.isEmpty) {
      return null;
    }
    return locations.first;
  }

  Future<void> _lookup({bool latest = false}) async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      _say('Konteks tenant belum tersedia.');
      return;
    }
    if (!latest && _orderNumber.text.trim().isEmpty) {
      _say('Masukkan nomor nota terlebih dahulu.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _readiness = null;
      _handover = null;
    });
    try {
      final order = await ref
          .read(operationsRepositoryProvider)
          .lookupOrder(
            tenantId: tenantId,
            orderNumber: latest ? null : _orderNumber.text,
          );
      if (!mounted) {
        return;
      }
      if (order == null) {
        setState(() {
          _order = null;
          _tasks = const [];
          _error = 'Order tidak ditemukan.';
          _busy = false;
        });
        return;
      }
      _orderNumber.text = order.orderNumber;
      setState(() => _order = order);
      await _loadTasks();
      await _loadComplaints();
    } on ApiException catch (error) {
      _fail(_errorMessage(error));
    } catch (_) {
      _fail('Order belum dapat dimuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _openScanner() async {
    final code = await ref
        .read(codeScannerProvider)
        .scan(context, title: 'Scan order');
    if (!mounted || code == null) {
      return;
    }
    final normalized = ScanPayload.normalize(code);
    if (normalized == null) {
      _say('Kode tidak valid. Coba scan ulang.');
      return;
    }
    _orderNumber.text = normalized;
    await _lookup();
  }

  Future<void> _loadTasks() async {
    final tenantId = _tenantId;
    final locationId = _locationId;
    final order = _order;
    if (tenantId == null || locationId == null || order == null) {
      return;
    }
    final tasks = await ref
        .read(operationsRepositoryProvider)
        .tasks(tenantId: tenantId, locationId: locationId);
    if (!mounted) {
      return;
    }
    setState(() {
      _tasks = tasks
          .where((task) => task.orderId == order.orderId)
          .toList(growable: false);
    });
  }

  Future<void> _loadComplaints() async {
    final tenantId = _tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    final complaints = await ref
        .read(operationsRepositoryProvider)
        .complaints(tenantId: tenantId, orderId: order.orderId);
    if (mounted) {
      setState(() => _complaints = complaints);
    }
  }

  Future<void> _createComplaint() async {
    final tenantId = _tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    final result = await showDialog<({String kind, String description})>(
      context: context,
      builder: (context) => const _ComplaintDialog(),
    );
    if (result == null || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .createComplaint(
            tenantId: tenantId,
            orderId: order.orderId,
            kind: result.kind,
            description: result.description,
          );
      await _loadComplaints();
      _say('Komplain dicatat.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Komplain belum dapat dicatat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _rewash(ComplaintSummary complaint) async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .rewashComplaint(tenantId: tenantId, complaintId: complaint.id);
      await _loadTasks();
      await _loadComplaints();
      _say('Rewash attempt baru dibuat.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Rewash belum dapat dibuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _resolveComplaint(ComplaintSummary complaint) async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    final result = await showDialog<({String status, String resolution})>(
      context: context,
      builder: (context) => const _ResolveComplaintDialog(),
    );
    if (result == null || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .resolveComplaint(
            tenantId: tenantId,
            complaintId: complaint.id,
            status: result.status,
            resolution: result.resolution,
          );
      await _loadComplaints();
      _say('Komplain diperbarui.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Komplain belum dapat diperbarui.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _receiveBags() async {
    final tenantId = _tenantId;
    final locationId = _locationId;
    final order = _order;
    if (tenantId == null || locationId == null || order == null) {
      return;
    }
    final pending = order.bags.where((bag) => bag.currentLocationId == null);
    if (pending.isEmpty) {
      return;
    }
    setState(() => _busy = true);
    try {
      for (final bag in pending) {
        await ref
            .read(operationsRepositoryProvider)
            .receiveBag(
              tenantId: tenantId,
              orderId: order.orderId,
              bagId: bag.id,
              locationId: locationId,
              expectedVersion: bag.version,
            );
      }
      await _reloadOrder();
      _say('Bag diterima di lokasi produksi.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Bag belum dapat diterima.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _planTasks() async {
    final tenantId = _tenantId;
    final locationId = _locationId;
    final order = _order;
    if (tenantId == null || locationId == null || order == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .planTasks(
            tenantId: tenantId,
            orderId: order.orderId,
            locationId: locationId,
          );
      await _loadTasks();
      _say('Tugas produksi dibuat.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Tugas produksi belum dapat dibuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _transition(ProductionTaskSummary task, String status) async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .transition(tenantId: tenantId, task: task, toStatus: status);
      await _loadTasks();
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Status tugas belum dapat diubah.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _recordQc(ProductionTaskSummary task) async {
    final tenantId = _tenantId;
    if (tenantId == null) {
      return;
    }
    final result = await showDialog<({String outcome, String? notes})>(
      context: context,
      builder: (context) => const _QcDialog(),
    );
    if (result == null || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(operationsRepositoryProvider)
          .recordQc(
            tenantId: tenantId,
            task: task,
            outcome: result.outcome,
            notes: result.notes,
          );
      await _loadTasks();
      _say(
        result.outcome == 'passed' ? 'QC lulus dicatat.' : 'QC gagal dicatat.',
      );
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Hasil QC belum dapat dicatat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _checkReadiness() async {
    final tenantId = _tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final readiness = await ref
          .read(operationsRepositoryProvider)
          .readiness(tenantId: tenantId, orderId: order.orderId);
      if (!mounted) {
        return;
      }
      setState(() => _readiness = readiness);
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Kesiapan order belum dapat dimuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<PaymentSummary?> _collectPayment(OrderLookup order) {
    return showOrderPaymentSheet(
      context: context,
      invoiceId: order.invoiceId,
      orderNumber: order.orderNumber,
      balanceDueRupiah: order.balanceDueRupiah,
    );
  }

  Future<void> _openPaymentSheet() async {
    final order = _order;
    if (order == null) {
      return;
    }
    final payment = await _collectPayment(order);
    if (payment == null || !mounted) {
      return;
    }
    await _reloadOrder();
    await _checkReadiness();
    if (!mounted) {
      return;
    }
    _say(
      payment.balanceDueRupiah > 0
          ? 'Pembayaran tercatat. Sisa ${formatRupiah(payment.balanceDueRupiah)}.'
          : 'Pembayaran tercatat. Invoice lunas.',
    );
  }

  Future<void> _submitHandover() async {
    final tenantId = _tenantId;
    final locationId = _locationId;
    var order = _order;
    if (tenantId == null || locationId == null || order == null) {
      return;
    }

    if (order.balanceDueRupiah > 0) {
      final session = ref.read(sessionControllerProvider).asData?.value;
      if (session?.canManagePayments != true) {
        _say(
          'Pembayaran belum lunas. Minta kasir atau supervisor mencatat '
          'pembayaran.',
        );
        return;
      }
      final payment = await _collectPayment(order);
      if (payment == null || !mounted) {
        return;
      }
      await _reloadOrder();
      await _checkReadiness();
      if (!mounted) {
        return;
      }
      order = _order;
      if (order == null) {
        return;
      }
    }

    final readiness = _readiness;
    if (readiness == null || !readiness.canReady) {
      _say('Periksa kesiapan dan pastikan semua bagian siap.');
      return;
    }
    final readyBags = order.bags
        .where((bag) => bag.currentLocationId == locationId)
        .toList(growable: false);
    if (readyBags.isEmpty) {
      _say('Tidak ada bag yang siap diserahkan di lokasi ini.');
      return;
    }

    final result =
        await showDialog<
          ({List<String> bagIds, String name, String method, String? notes})
        >(
          context: context,
          builder: (context) => _HandoverDialog(bags: readyBags),
        );
    if (result == null || !mounted) {
      return;
    }
    if (result.bagIds.isEmpty) {
      _say('Pilih minimal satu bag untuk diserahkan.');
      return;
    }

    setState(() => _busy = true);
    try {
      final handover = await ref
          .read(operationsRepositoryProvider)
          .handover(
            tenantId: tenantId,
            orderId: order.orderId,
            bagIds: result.bagIds,
            locationId: locationId,
            recipientName: result.name,
            verificationMethod: result.method,
            evidenceNotes: result.notes,
          );
      if (!mounted) {
        return;
      }
      setState(() => _handover = handover);
      await _reloadOrder();
      await _loadTasks();
      _say('Serah terima selesai: ${handover.orderStatus}.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Serah terima belum dapat diselesaikan.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _reloadOrder() async {
    final tenantId = _tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    final refreshed = await ref
        .read(operationsRepositoryProvider)
        .lookupOrder(tenantId: tenantId, orderNumber: order.orderNumber);
    if (mounted && refreshed != null) {
      setState(() => _order = refreshed);
    }
  }

  Future<void> _openPrintPreview() async {
    final tenantId = _tenantId;
    final order = _order;
    if (tenantId == null || order == null) {
      return;
    }
    setState(() => _busy = true);
    try {
      final receipt = await ref
          .read(orderRepositoryProvider)
          .receipt(tenantId: tenantId, orderId: order.orderId);
      if (!mounted) {
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (context) => ReceiptPreviewScreen(receipt: receipt),
        ),
      );
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Nota belum dapat dimuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _fail(String message) {
    if (mounted) {
      setState(() {
        _error = message;
        _busy = false;
      });
    }
  }

  void _say(String message) {
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  String _errorMessage(ApiException error) {
    for (final messages in error.fieldErrors.values) {
      if (messages.isNotEmpty) {
        return messages.first;
      }
    }
    return error.message;
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final padding = Responsive.horizontalPadding(width);

    return Scaffold(
      appBar: AppBar(title: const Text('Produksi')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('operations-screen'),
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              const AppPageHeader(
                eyebrow: 'Produksi',
                title: 'Operasi & serah terima',
                subtitle:
                    'Scan QR nota dengan kamera, atau masukkan nomor nota '
                    'secara manual.',
              ),
              if (_busy) ...[
                const SizedBox(height: 12),
                const LinearProgressIndicator(),
              ],
              const SizedBox(height: 16),
              _Panel(
                title: 'Scan order',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton.icon(
                      onPressed: _busy ? null : _openScanner,
                      icon: const Icon(Icons.qr_code_scanner_rounded),
                      label: const Text('Scan kamera'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      key: const Key('operations-order-number'),
                      controller: _orderNumber,
                      textCapitalization: TextCapitalization.characters,
                      decoration: const InputDecoration(
                        labelText: 'Nomor nota',
                        helperText: 'Contoh: UAT-MAIN-20261114-000002',
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton(
                            onPressed: _busy ? null : _lookup,
                            child: const Text('Cari order'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: _busy
                                ? null
                                : () => _lookup(latest: true),
                            child: const Text('Order terbaru'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                _ErrorPanel(message: _error!),
              ],
              if (_order != null) ...[
                const SizedBox(height: 16),
                _orderCard(context),
                const SizedBox(height: 16),
                _tasksCard(context),
                const SizedBox(height: 16),
                _complaintsCard(context),
                if (_readiness != null) ...[
                  const SizedBox(height: 16),
                  _readinessCard(context),
                ],
                if (_handover != null) ...[
                  const SizedBox(height: 16),
                  _Panel(
                    title: 'Serah terima selesai',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Status order: ${_handover!.orderStatus}. '
                          'Handover ${_handover!.handoverId.substring(0, 8)}...',
                        ),
                        if (_handover!.evidenceNotes != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(
                              'Bukti: ${_handover!.evidenceNotes}',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                      ],
                    ),
                  ),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _orderCard(BuildContext context) {
    final order = _order!;
    final session = ref.watch(sessionControllerProvider).asData?.value;
    final canPay = session?.canManagePayments ?? false;
    final bags = order.bags;
    final hasUnreceived = bags.any((bag) => bag.currentLocationId == null);
    final readyBags = bags
        .where((bag) => bag.currentLocationId == _locationId)
        .length;
    return _Panel(
      title: 'Order ${order.orderNumber}',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _Row(label: 'Status', value: order.status),
          _Row(label: 'Total', value: formatRupiah(order.totalRupiah)),
          _Row(label: 'Sisa', value: formatRupiah(order.balanceDueRupiah)),
          _Row(label: 'Estimasi selesai', value: formatDateTime(order.dueAt)),
          const SizedBox(height: 8),
          Text(
            'Bag: ${bags.length} • di lokasi ini: $readyBags',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          if (hasUnreceived) ...[
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : _receiveBags,
              child: const Text('Terima bag di lokasi'),
            ),
          ],
          if (!hasUnreceived && _tasks.isEmpty) ...[
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : _planTasks,
              child: const Text('Rencanakan produksi'),
            ),
          ],
          if (order.balanceDueRupiah > 0 && !canPay) ...[
            const SizedBox(height: 8),
            const Text(
              'Pembayaran belum lunas. Minta kasir atau supervisor mencatat '
              'pembayaran sebelum serah terima.',
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton(
                onPressed: _busy ? null : _checkReadiness,
                child: const Text('Cek kesiapan'),
              ),
              if (order.balanceDueRupiah > 0 && canPay)
                FilledButton(
                  onPressed: _busy ? null : _openPaymentSheet,
                  child: const Text('Catat pembayaran'),
                ),
              OutlinedButton(
                onPressed: _busy ? null : _openPrintPreview,
                child: const Text('Pratinjau & cetak'),
              ),
              if ((_readiness?.canReady ?? false) && readyBags > 0)
                FilledButton.tonal(
                  onPressed: _busy ? null : _submitHandover,
                  child: const Text('Serah terima'),
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tasksCard(BuildContext context) {
    return _Panel(
      title: 'Tugas produksi',
      child: _tasks.isEmpty
          ? const Text('Belum ada tugas. Terima bag lalu rencanakan produksi.')
          : Column(
              children: _tasks.map((task) {
                final tone = switch (task.status) {
                  'done' => StatusTone.success,
                  'in_progress' => StatusTone.info,
                  'blocked' => StatusTone.danger,
                  _ => StatusTone.warning,
                };
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                '${_stepLabel(task.stepCode)} • '
                                '${task.serviceName}',
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                            ),
                            StatusChip(
                              label: task.status,
                              icon: task.overdue
                                  ? Icons.warning_amber_outlined
                                  : Icons.circle_outlined,
                              tone: task.overdue ? StatusTone.danger : tone,
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Tenggat ${formatDateTime(task.dueAt)}',
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          children: [
                            if (task.status == 'queued')
                              FilledButton(
                                onPressed: _busy
                                    ? null
                                    : () => _transition(task, 'in_progress'),
                                child: const Text('Mulai'),
                              ),
                            if (task.status == 'in_progress')
                              FilledButton(
                                onPressed: _busy
                                    ? null
                                    : () => _transition(task, 'done'),
                                child: const Text('Selesai'),
                              ),
                            if (task.status == 'done' && task.requiresQc)
                              FilledButton.tonal(
                                onPressed: _busy ? null : () => _recordQc(task),
                                child: const Text('Nilai QC'),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
    );
  }

  Widget _readinessCard(BuildContext context) {
    final readiness = _readiness!;
    return _Panel(
      title: 'Kesiapan order',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            readiness.canReady
                ? 'Semua bagian siap diserahkan.'
                : readiness.partiallyReady
                ? 'Sebagian bagian siap.'
                : 'Belum siap diserahkan.',
          ),
          const SizedBox(height: 8),
          ...readiness.contents.map(
            (content) => Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                children: [
                  Icon(
                    content.ready
                        ? Icons.check_circle_outline
                        : Icons.pending_outlined,
                    color: content.ready
                        ? Theme.of(context).colorScheme.primary
                        : AppTheme.textSecondary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Bagian ${content.bagContentId.substring(0, 8)}... • '
                      'QC ${content.qcPassed ? 'lulus' : 'belum'} • '
                      '${content.openRequiredTasks} tugas wajib terbuka',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _complaintsCard(BuildContext context) {
    return _Panel(
      title: 'Komplain',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_complaints.isEmpty)
            const Text('Belum ada komplain untuk order ini.')
          else
            ..._complaints.map((complaint) {
              final tone = switch (complaint.status) {
                'resolved' => StatusTone.success,
                'rejected' => StatusTone.danger,
                'investigating' => StatusTone.warning,
                _ => StatusTone.info,
              };
              final active = ![
                'resolved',
                'rejected',
              ].contains(complaint.status);
              return Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              complaint.kind,
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                          ),
                          StatusChip(
                            label: complaint.status,
                            icon: Icons.flag_outlined,
                            tone: tone,
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(complaint.description),
                      if (complaint.resolution != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            'Resolusi: ${complaint.resolution}',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ),
                      if (complaint.rewashAttemptNo != null)
                        Text('Rewash attempt ${complaint.rewashAttemptNo}'),
                      if (active)
                        Wrap(
                          spacing: 8,
                          children: [
                            if (complaint.kind == 'rewash')
                              OutlinedButton(
                                onPressed: _busy
                                    ? null
                                    : () => _rewash(complaint),
                                child: const Text('Buat rewash'),
                              ),
                            FilledButton.tonal(
                              onPressed: _busy
                                  ? null
                                  : () => _resolveComplaint(complaint),
                              child: const Text('Selesaikan'),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              );
            }),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: _busy ? null : _createComplaint,
            icon: const Icon(Icons.add_alert_outlined),
            label: const Text('Buat komplain'),
          ),
        ],
      ),
    );
  }

  String _stepLabel(String code) {
    return switch (code) {
      'washing' => 'Cuci',
      'drying' => 'Kering',
      'ironing' => 'Setrika',
      'qc' => 'QC',
      'packing' => 'Packing',
      _ => code,
    };
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label)),
          Text(value),
        ],
      ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    final palette =
        Theme.of(context).extension<AppStatusColors>() ??
        AppStatusColors.standard;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: palette.dangerBackground,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Text(message),
    );
  }
}

class _QcDialog extends StatefulWidget {
  const _QcDialog();

  @override
  State<_QcDialog> createState() => _QcDialogState();
}

class _QcDialogState extends State<_QcDialog> {
  final _notes = TextEditingController();
  String _outcome = 'passed';

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  void _submit() {
    if (_outcome == 'failed' && _notes.text.trim().isEmpty) {
      return;
    }
    Navigator.of(context).pop((
      outcome: _outcome,
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Hasil QC'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'passed', label: Text('Lulus')),
              ButtonSegment(value: 'failed', label: Text('Gagal')),
            ],
            selected: {_outcome},
            onSelectionChanged: (value) =>
                setState(() => _outcome = value.first),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _notes,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: _outcome == 'failed'
                  ? 'Catatan wajib'
                  : 'Catatan (opsional)',
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(onPressed: _submit, child: const Text('Simpan QC')),
      ],
    );
  }
}

class _HandoverDialog extends ConsumerStatefulWidget {
  const _HandoverDialog({required this.bags});

  final List<OrderBagSummary> bags;

  @override
  ConsumerState<_HandoverDialog> createState() => _HandoverDialogState();
}

class _HandoverDialogState extends ConsumerState<_HandoverDialog> {
  final _name = TextEditingController();
  final _notes = TextEditingController();
  late final Set<String> _selected = widget.bags.map((bag) => bag.id).toSet();
  String _method = 'receipt';

  @override
  void dispose() {
    _name.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _scanBag() async {
    final code = await ref
        .read(codeScannerProvider)
        .scan(context, title: 'Scan bag');
    if (!mounted || code == null) {
      return;
    }
    OrderBagSummary? match;
    for (final bag in widget.bags) {
      if (ScanPayload.matchesLabel(code, bag.labelCode)) {
        match = bag;
        break;
      }
    }
    final selectedBag = match;
    if (selectedBag == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bag tidak termasuk order ini.')),
      );
      return;
    }
    setState(() {
      if (_selected.contains(selectedBag.id)) {
        _selected.remove(selectedBag.id);
      } else {
        _selected.add(selectedBag.id);
      }
    });
  }

  void _submit() {
    if (_name.text.trim().isEmpty || _selected.isEmpty) {
      return;
    }
    Navigator.of(context).pop((
      bagIds: _selected.toList(growable: false),
      name: _name.text.trim(),
      method: _method,
      notes: _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Serah terima'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Pilih bag yang diserahkan. Bag lain tetap tersimpan.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: _scanBag,
                icon: const Icon(Icons.qr_code_scanner_rounded, size: 18),
                label: const Text('Scan bag'),
              ),
            ),
            const SizedBox(height: 4),
            ...widget.bags.map(
              (bag) => CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  bag.labelCode ?? 'Bag ${bag.id.substring(0, 8)}...',
                ),
                subtitle: Text(bag.custodyStatus),
                value: _selected.contains(bag.id),
                onChanged: (value) => setState(() {
                  if (value ?? false) {
                    _selected.add(bag.id);
                  } else {
                    _selected.remove(bag.id);
                  }
                }),
              ),
            ),
            TextField(
              controller: _name,
              autofocus: true,
              decoration: const InputDecoration(labelText: 'Nama penerima'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: const InputDecoration(labelText: 'Verifikasi'),
              items: const [
                DropdownMenuItem(value: 'receipt', child: Text('Nota')),
                DropdownMenuItem(value: 'otp', child: Text('OTP')),
                DropdownMenuItem(
                  value: 'verified_staff_exception',
                  child: Text('Verifikasi staf'),
                ),
              ],
              onChanged: (value) =>
                  setState(() => _method = value ?? 'receipt'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _notes,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Bukti/catatan penerima (simulasi foto)',
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(onPressed: _submit, child: const Text('Serahkan')),
      ],
    );
  }
}

class _ComplaintDialog extends StatefulWidget {
  const _ComplaintDialog();

  @override
  State<_ComplaintDialog> createState() => _ComplaintDialogState();
}

class _ComplaintDialogState extends State<_ComplaintDialog> {
  final _description = TextEditingController();
  String _kind = 'rewash';

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  void _submit() {
    if (_description.text.trim().isEmpty) {
      return;
    }
    Navigator.of(
      context,
    ).pop((kind: _kind, description: _description.text.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Buat komplain'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          DropdownButtonFormField<String>(
            initialValue: _kind,
            decoration: const InputDecoration(labelText: 'Jenis'),
            items: const [
              DropdownMenuItem(value: 'rewash', child: Text('Rewash')),
              DropdownMenuItem(value: 'missing', child: Text('Hilang')),
              DropdownMenuItem(value: 'damaged', child: Text('Rusak')),
              DropdownMenuItem(value: 'other', child: Text('Lainnya')),
            ],
            onChanged: (value) => setState(() => _kind = value ?? 'rewash'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Deskripsi'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(onPressed: _submit, child: const Text('Simpan')),
      ],
    );
  }
}

class _ResolveComplaintDialog extends StatefulWidget {
  const _ResolveComplaintDialog();

  @override
  State<_ResolveComplaintDialog> createState() =>
      _ResolveComplaintDialogState();
}

class _ResolveComplaintDialogState extends State<_ResolveComplaintDialog> {
  final _resolution = TextEditingController();
  String _status = 'resolved';

  @override
  void dispose() {
    _resolution.dispose();
    super.dispose();
  }

  void _submit() {
    if (_resolution.text.trim().isEmpty) {
      return;
    }
    Navigator.of(
      context,
    ).pop((status: _status, resolution: _resolution.text.trim()));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Selesaikan komplain'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'resolved', label: Text('Selesai')),
              ButtonSegment(value: 'rejected', label: Text('Tolak')),
            ],
            selected: {_status},
            onSelectionChanged: (value) =>
                setState(() => _status = value.first),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _resolution,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Resolusi'),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(onPressed: _submit, child: const Text('Simpan')),
      ],
    );
  }
}
