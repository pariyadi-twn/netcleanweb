import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/status_chip.dart';
import '../../../core/ui/theme.dart';
import '../domain/cash_models.dart';

class CashScreen extends ConsumerStatefulWidget {
  const CashScreen({super.key});

  @override
  ConsumerState<CashScreen> createState() => _CashScreenState();
}

class _CashScreenState extends ConsumerState<CashScreen> {
  final _openingAmount = TextEditingController();
  final _countedAmount = TextEditingController();
  final _reason = TextEditingController();
  final _registerCode = TextEditingController(text: 'KASIR-1');
  String? _selectedRegisterId;
  bool _busy = false;

  @override
  void dispose() {
    _openingAmount.dispose();
    _countedAmount.dispose();
    _reason.dispose();
    _registerCode.dispose();
    super.dispose();
  }

  Future<void> _open(CashRegisterSummary register) async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    if (tenantId == null) {
      return;
    }
    final amount = int.tryParse(_openingAmount.text.trim());
    if (amount == null || amount < 0) {
      _say('Kas awal belum valid.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(cashRepositoryProvider)
          .openSession(
            tenantId: tenantId,
            registerId: register.id,
            openingRupiah: amount,
          );
      if (!mounted) {
        return;
      }
      _openingAmount.clear();
      ref.invalidate(cashRegistersProvider);
      ref.invalidate(activeCashSessionProvider);
      _say('Shift kas dibuka.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Shift kas belum dapat dibuka.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _close(CashSessionSummary session) async {
    final tenantId = ref
        .read(sessionControllerProvider)
        .asData
        ?.value
        ?.tenantId;
    if (tenantId == null) {
      return;
    }
    final counted = int.tryParse(_countedAmount.text.trim());
    if (counted == null || counted < 0) {
      _say('Kas fisik belum valid.');
      return;
    }
    final difference = counted - session.expectedRupiah;
    if (difference != 0 && _reason.text.trim().isEmpty) {
      _say('Alasan wajib diisi jika ada selisih kas.');
      return;
    }
    setState(() => _busy = true);
    try {
      final closed = await ref
          .read(cashRepositoryProvider)
          .closeSession(
            tenantId: tenantId,
            sessionId: session.id,
            countedClosingRupiah: counted,
            reason: _reason.text,
          );
      if (!mounted) {
        return;
      }
      _countedAmount.clear();
      _reason.clear();
      ref.invalidate(cashRegistersProvider);
      ref.invalidate(activeCashSessionProvider);
      _say(
        (closed.differenceRupiah ?? 0) == 0
            ? 'Shift kas ditutup tanpa selisih.'
            : 'Shift ditutup. Selisih ${formatRupiah(closed.differenceRupiah ?? 0)}.',
      );
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Shift kas belum dapat ditutup.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _createRegister() async {
    final session = ref.read(sessionControllerProvider).asData?.value;
    final tenantId = session?.tenantId;
    final branchId = session?.branchIds.isEmpty ?? true
        ? null
        : session!.branchIds.first;
    final code = _registerCode.text.trim();
    if (tenantId == null || branchId == null || code.isEmpty) {
      _say('Kode laci kas belum valid.');
      return;
    }
    setState(() => _busy = true);
    try {
      await ref
          .read(cashRepositoryProvider)
          .createRegister(tenantId: tenantId, branchId: branchId, code: code);
      if (!mounted) {
        return;
      }
      ref.invalidate(cashRegistersProvider);
      _say('Laci kas dibuat.');
    } on ApiException catch (error) {
      _say(_errorMessage(error));
    } catch (_) {
      _say('Laci kas belum dapat dibuat.');
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
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
      appBar: AppBar(title: const Text('Kas')),
      body: SafeArea(
        child: ResponsiveCenter(
          child: ListView(
            key: const Key('cash-screen'),
            padding: EdgeInsets.fromLTRB(padding, 24, padding, 32),
            children: [
              Text(
                'Shift kas',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 4),
              Text(
                'Kas awal, penjualan tunai, dan kas fisik dihitung per shift.',
                style: Theme.of(context).textTheme.bodyMedium,
              ),
              if (_busy) ...[
                const SizedBox(height: 12),
                const LinearProgressIndicator(),
              ],
              const SizedBox(height: 16),
              ref
                  .watch(cashRegistersProvider)
                  .when(
                    loading: () => const Padding(
                      padding: EdgeInsets.symmetric(vertical: 48),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                    error: (error, stackTrace) => _ErrorPanel(
                      message: error is ApiException
                          ? _errorMessage(error)
                          : 'Data kas belum dapat dimuat.',
                      onRetry: () => ref.invalidate(cashRegistersProvider),
                    ),
                    data: (registers) => registers.isEmpty
                        ? _emptyRegisters(context)
                        : _registers(context, registers),
                  ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _emptyRegisters(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Belum ada laci kas',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 4),
            const Text('Buat laci kas untuk membuka shift tunai.'),
            const SizedBox(height: 12),
            TextField(
              controller: _registerCode,
              textCapitalization: TextCapitalization.characters,
              decoration: const InputDecoration(labelText: 'Kode laci'),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : _createRegister,
              child: const Text('Buat laci kas'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _registers(BuildContext context, List<CashRegisterSummary> registers) {
    final selected = registers.firstWhere(
      (register) => register.id == _selectedRegisterId,
      orElse: () => registers.first,
    );
    _selectedRegisterId = selected.id;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (registers.length > 1)
          DropdownButtonFormField<String>(
            initialValue: selected.id,
            decoration: const InputDecoration(labelText: 'Laci kas'),
            items: registers
                .map(
                  (register) => DropdownMenuItem(
                    value: register.id,
                    child: Text(register.code),
                  ),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (value) => setState(() => _selectedRegisterId = value),
          ),
        if (registers.length == 1)
          Text(
            'Laci ${selected.code}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
        const SizedBox(height: 12),
        if (selected.currentSession?.isOpen ?? false)
          _sessionCard(context, selected, selected.currentSession!)
        else
          _openCard(context, selected),
      ],
    );
  }

  Widget _openCard(BuildContext context, CashRegisterSummary register) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const StatusChip(
              label: 'Shift tertutup',
              icon: Icons.lock_outline,
              tone: StatusTone.warning,
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('cash-opening'),
              controller: _openingAmount,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(labelText: 'Kas awal'),
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _busy ? null : () => _open(register),
              child: const Text('Buka shift'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sessionCard(
    BuildContext context,
    CashRegisterSummary register,
    CashSessionSummary session,
  ) {
    final counted = int.tryParse(_countedAmount.text.trim());
    final difference = counted == null
        ? null
        : counted - session.expectedRupiah;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Expanded(
                  child: StatusChip(
                    label: 'Shift terbuka',
                    icon: Icons.lock_open_outlined,
                    tone: StatusTone.success,
                  ),
                ),
                if (session.openedAt != null)
                  Text(
                    formatDateTime(session.openedAt!),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
              ],
            ),
            const SizedBox(height: 16),
            _ValueRow(label: 'Kas awal', amount: session.openingRupiah),
            _ValueRow(label: 'Mutasi tunai', amount: session.ledgerRupiah),
            _ValueRow(
              label: 'Seharusnya',
              amount: session.expectedRupiah,
              strong: true,
            ),
            const Divider(height: 24),
            TextField(
              key: const Key('cash-counted'),
              controller: _countedAmount,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(
                labelText: 'Kas fisik dihitung',
              ),
            ),
            if (difference != null) ...[
              const SizedBox(height: 8),
              Text(
                difference == 0
                    ? 'Tidak ada selisih.'
                    : 'Selisih ${formatRupiah(difference)}.',
                style: TextStyle(
                  color: difference == 0
                      ? AppTheme.textSecondary
                      : Theme.of(context).colorScheme.error,
                ),
              ),
            ],
            const SizedBox(height: 12),
            TextField(
              controller: _reason,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'Alasan selisih (wajib jika ada selisih)',
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: _busy ? null : () => _close(session),
              child: const Text('Tutup shift'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ValueRow extends StatelessWidget {
  const _ValueRow({
    required this.label,
    required this.amount,
    this.strong = false,
  });

  final String label;
  final int amount;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final style = Theme.of(context).textTheme.bodyLarge?.copyWith(
      fontWeight: strong ? FontWeight.w700 : FontWeight.w400,
      color: strong ? AppTheme.text : AppTheme.textSecondary,
    );
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(formatRupiah(amount), style: style),
        ],
      ),
    );
  }
}

class _ErrorPanel extends StatelessWidget {
  const _ErrorPanel({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(message),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba lagi'),
          ),
        ],
      ),
    );
  }
}
