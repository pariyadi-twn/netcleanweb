import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../../orders/domain/order_models.dart';
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

  Future<void> _submitHandover() async {
    final tenantId = _tenantId;
    final locationId = _locationId;
    final order = _order;
    final readiness = _readiness;
    if (tenantId == null || locationId == null || order == null) {
      return;
    }
    if (readiness == null || !readiness.canReady) {
      _say('Periksa kesiapan dan pastikan semua bagian siap.');
      return;
    }
    final bags = order.bags
        .where((bag) => bag.currentLocationId == locationId)
        .map((bag) => bag.id)
        .toList(growable: false);
    if (bags.isEmpty) {
      _say('Tidak ada bag yang siap diserahkan di lokasi ini.');
      return;
    }

    final result = await showDialog<({String name, String method})>(
      context: context,
      builder: (context) => const _HandoverDialog(),
    );
    if (result == null || !mounted) {
      return;
    }

    setState(() => _busy = true);
    try {
      final handover = await ref
          .read(operationsRepositoryProvider)
          .handover(
            tenantId: tenantId,
            orderId: order.orderId,
            bagIds: bags,
            locationId: locationId,
            recipientName: result.name,
            verificationMethod: result.method,
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

  Future<void> _simulatePrint() async {
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
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => _PrintPreviewDialog(receipt: receipt),
      );
      if (confirmed == true) {
        await ref
            .read(orderRepositoryProvider)
            .recordPrintAttempt(tenantId: tenantId, orderId: order.orderId);
        _say('Cetak disimulasikan dan tercatat.');
      }
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Simulasi cetak belum dapat dicatat.');
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
      appBar: AppBar(title: const Text('Operasi')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('operations-screen'),
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              Text(
                'Produksi dan serah terima',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 4),
              Text(
                'Kamera/scan fisik belum tersedia. Masukkan nomor nota atau '
                'gunakan order terbaru sebagai simulasi scan.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              if (_busy) ...[
                const SizedBox(height: 12),
                const LinearProgressIndicator(),
              ],
              const SizedBox(height: 16),
              _Panel(
                title: 'Simulasi scan order',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
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
                if (_readiness != null) ...[
                  const SizedBox(height: 16),
                  _readinessCard(context),
                ],
                if (_handover != null) ...[
                  const SizedBox(height: 16),
                  _Panel(
                    title: 'Serah terima selesai',
                    child: Text(
                      'Status order: ${_handover!.orderStatus}. '
                      'Handover ${_handover!.handoverId.substring(0, 8)}...',
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
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              OutlinedButton(
                onPressed: _busy ? null : _checkReadiness,
                child: const Text('Cek kesiapan'),
              ),
              OutlinedButton(
                onPressed: _busy ? null : _simulatePrint,
                child: const Text('Simulasi cetak'),
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
                        ? AppTheme.light.colorScheme.primary
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
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
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
        borderRadius: BorderRadius.circular(12),
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

class _HandoverDialog extends StatefulWidget {
  const _HandoverDialog();

  @override
  State<_HandoverDialog> createState() => _HandoverDialogState();
}

class _HandoverDialogState extends State<_HandoverDialog> {
  final _name = TextEditingController();
  String _method = 'receipt';

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  void _submit() {
    if (_name.text.trim().isEmpty) {
      return;
    }
    Navigator.of(context).pop((name: _name.text.trim(), method: _method));
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Serah terima'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
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
            onChanged: (value) => setState(() => _method = value ?? 'receipt'),
          ),
        ],
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

class _PrintPreviewDialog extends StatelessWidget {
  const _PrintPreviewDialog({required this.receipt});

  final ReceiptSummary receipt;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Simulasi cetak nota'),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Nota ${receipt.orderNumber}'),
            Text('Invoice ${receipt.invoiceNumber}'),
            Text('Pelanggan ${receipt.customerName}'),
            Text('Total ${formatRupiah(receipt.totalRupiah)}'),
            Text('Dibayar ${formatRupiah(receipt.paidRupiah)}'),
            Text('Sisa ${formatRupiah(receipt.balanceDueRupiah)}'),
            const SizedBox(height: 8),
            const Text(
              'Tidak ada printer fisik. Konfirmasi akan mencatat percobaan '
              'cetak sebagai simulasi berhasil.',
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(true),
          child: const Text('Catat simulasi cetak'),
        ),
      ],
    );
  }
}
