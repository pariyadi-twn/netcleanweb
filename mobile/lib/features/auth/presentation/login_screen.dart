import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/brand.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/theme.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  void _login() {
    final session = ref.read(sessionControllerProvider);
    if (session.isLoading) {
      return;
    }
    FocusScope.of(context).unfocus();
    ref
        .read(sessionControllerProvider.notifier)
        .login(email: _email.text.trim(), password: _password.text);
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionControllerProvider);
    final busy = session.isLoading;
    final width = MediaQuery.sizeOf(context).width;
    final tabletLayout = Responsive.windowClass(width) != WindowClass.compact;

    return Scaffold(
      backgroundColor: AppTheme.background,
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final form = _formCard(context, busy: busy);

              if (!tabletLayout) {
                return SingleChildScrollView(
                  padding: EdgeInsets.fromLTRB(
                    Responsive.horizontalPadding(width),
                    20,
                    Responsive.horizontalPadding(width),
                    28,
                  ),
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      minHeight: constraints.maxHeight - 48,
                    ),
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 460),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const _CompactBrand(),
                            const SizedBox(height: 18),
                            _heroPanel(context, compact: true),
                            const SizedBox(height: 16),
                            form,
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              }

              return SingleChildScrollView(
                padding: const EdgeInsets.all(32),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: constraints.maxHeight - 64,
                  ),
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 1120),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(child: _brandPanel(context)),
                          const SizedBox(width: 28),
                          ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 430),
                            child: form,
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _formCard(BuildContext context, {required bool busy}) {
    final session = ref.watch(sessionControllerProvider);

    return Container(
      key: const Key('login-form'),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: AppTheme.surface,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: AppTheme.divider),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryDeep.withValues(alpha: 0.06),
            blurRadius: 30,
            offset: const Offset(0, 16),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Masuk ke perangkat',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 7),
          Text(
            'Gunakan akun staf yang sudah terdaftar di tenant laundry kamu.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 24),
          TextField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            autocorrect: false,
            enableSuggestions: false,
            autofillHints: const [AutofillHints.email],
            decoration: const InputDecoration(
              labelText: 'Email',
              hintText: 'nama@laundry.id',
              prefixIcon: Icon(Icons.alternate_email_rounded),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _password,
            obscureText: _obscurePassword,
            textInputAction: TextInputAction.done,
            autocorrect: false,
            enableSuggestions: false,
            autofillHints: const [AutofillHints.password],
            onSubmitted: (_) => _login(),
            decoration: InputDecoration(
              labelText: 'Password',
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              suffixIcon: IconButton(
                tooltip: _obscurePassword
                    ? 'Tampilkan password'
                    : 'Sembunyikan password',
                onPressed: () =>
                    setState(() => _obscurePassword = !_obscurePassword),
                icon: Icon(
                  _obscurePassword
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                ),
              ),
            ),
          ),
          if (session.hasError) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.errorContainer,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.error_outline_rounded,
                    size: 19,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Text(
                      _errorMessage(session.error),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 18),
          FilledButton(
            onPressed: busy ? null : _login,
            child: busy
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.2,
                      color: Colors.white,
                    ),
                  )
                : const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text('Masuk'),
                      SizedBox(width: 8),
                      Icon(Icons.arrow_forward_rounded, size: 18),
                    ],
                  ),
          ),
          const SizedBox(height: 18),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.shield_outlined,
                size: 15,
                color: AppTheme.textSecondary,
              ),
              const SizedBox(width: 6),
              Flexible(
                child: Text(
                  'Sesi disimpan aman di perangkat ini.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _errorMessage(Object? error) {
    if (error is! ApiException) {
      return 'Login belum dapat diproses. Coba lagi.';
    }

    String? fieldMessage;
    for (final messages in error.fieldErrors.values) {
      if (messages.isNotEmpty) {
        fieldMessage = messages.first;
        break;
      }
    }

    final message = switch (error.code) {
      'UNAUTHENTICATED' => 'Email atau password tidak cocok.',
      'MFA_REQUIRED' => 'Akun ini memerlukan kode MFA.',
      'RATE_LIMITED' => 'Terlalu banyak percobaan. Tunggu sebentar.',
      'VALIDATION_FAILED' =>
        fieldMessage ?? 'Periksa kembali email dan password.',
      _ => fieldMessage ?? error.message,
    };
    final reference = error.correlationId;

    return reference == null || reference.isEmpty
        ? message
        : '$message\nRef: $reference';
  }

  Widget _heroPanel(BuildContext context, {required bool compact}) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: const BoxDecoration(gradient: AppTheme.heroGradient),
        child: Stack(
          children: [
            Positioned(
              right: -34,
              top: -50,
              child: _GlowCircle(
                size: compact ? 150 : 210,
                color: AppTheme.accent.withValues(alpha: 0.16),
              ),
            ),
            Positioned(
              left: -56,
              bottom: -76,
              child: _GlowCircle(
                size: compact ? 150 : 220,
                color: Colors.white.withValues(alpha: 0.07),
              ),
            ),
            Padding(
              padding: EdgeInsets.all(compact ? 20 : 36),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (!compact)
                    const NetCleanLockup(onDark: true)
                  else
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.12),
                            ),
                          ),
                          child: const Text(
                            'NETCLEAN STAFF',
                            style: TextStyle(
                              color: AppTheme.accent,
                              fontSize: 9.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 1.1,
                            ),
                          ),
                        ),
                        const Spacer(),
                        Icon(
                          Icons.local_laundry_service_outlined,
                          color: Colors.white.withValues(alpha: 0.65),
                          size: 26,
                        ),
                      ],
                    ),
                  if (!compact) const SizedBox(height: 56),
                  if (compact) const SizedBox(height: 20),
                  Text(
                    'Operasional laundry,\nlebih ringan.',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                      fontSize: compact ? 25 : 38,
                      height: 1.08,
                      letterSpacing: -0.9,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Pesanan, produksi, dan serah terima dalam satu alur yang rapi.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.68),
                      height: 1.55,
                    ),
                  ),
                  if (!compact) ...[
                    const SizedBox(height: 22),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: const [
                        _HeroPill(label: 'Kasir'),
                        _HeroPill(label: 'Produksi'),
                        _HeroPill(label: 'Serah terima'),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _brandPanel(BuildContext context) {
    return Container(
      key: const Key('login-brand-panel'),
      constraints: const BoxConstraints(minHeight: 590),
      decoration: BoxDecoration(
        gradient: AppTheme.heroGradient,
        borderRadius: BorderRadius.circular(34),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryDeep.withValues(alpha: 0.18),
            blurRadius: 44,
            offset: const Offset(0, 24),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(34),
        child: Stack(
          children: [
            Positioned(
              right: -80,
              top: -100,
              child: _GlowCircle(
                size: 300,
                color: AppTheme.accent.withValues(alpha: 0.15),
              ),
            ),
            Positioned(
              left: -100,
              bottom: -150,
              child: _GlowCircle(
                size: 340,
                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(42),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  const NetCleanLockup(onDark: true, markSize: 44),
                  const SizedBox(height: 68),
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      color: AppTheme.accent,
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: const Icon(
                      Icons.local_laundry_service_outlined,
                      color: AppTheme.primaryDeep,
                      size: 31,
                    ),
                  ),
                  const SizedBox(height: 26),
                  Text(
                    'Operasional laundry\ndalam satu genggaman.',
                    style: Theme.of(context).textTheme.displaySmall?.copyWith(
                      color: Colors.white,
                      fontSize: 39,
                      height: 1.08,
                      letterSpacing: -1.2,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Kasir, produksi, QC, dan serah terima tetap terhubung '
                    'dengan konteks tenant dan cabang yang jelas.',
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      color: Colors.white.withValues(alpha: 0.68),
                      height: 1.6,
                    ),
                  ),
                  const SizedBox(height: 38),
                  const _FeatureLine(
                    icon: Icons.receipt_long_outlined,
                    text: 'Nota dan pembayaran dalam satu alur',
                  ),
                  const SizedBox(height: 16),
                  const _FeatureLine(
                    icon: Icons.precision_manufacturing_outlined,
                    text: 'Produksi dan QC yang bisa dipantau',
                  ),
                  const SizedBox(height: 16),
                  const _FeatureLine(
                    icon: Icons.verified_user_outlined,
                    text: 'Akses aman sesuai peran staf',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactBrand extends StatelessWidget {
  const _CompactBrand();

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const NetCleanLockup(markSize: 40),
        const Spacer(),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: AppTheme.surface,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: AppTheme.divider),
          ),
          child: Text(
            'STAFF APP',
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: AppTheme.primary,
              fontSize: 9,
              letterSpacing: 1,
            ),
          ),
        ),
      ],
    );
  }
}

class _HeroPill extends StatelessWidget {
  const _HeroPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _FeatureLine extends StatelessWidget {
  const _FeatureLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.09),
            borderRadius: BorderRadius.circular(11),
            border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
          ),
          child: Icon(icon, color: AppTheme.accent, size: 17),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            text,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: Colors.white.withValues(alpha: 0.78),
            ),
          ),
        ),
      ],
    );
  }
}

class _GlowCircle extends StatelessWidget {
  const _GlowCircle({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}
