import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_session.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/brand.dart';
import '../../../core/ui/formatting.dart';
import '../../../core/ui/page_header.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/session_context_bar.dart';
import '../../../core/ui/theme.dart';
import '../../cash/presentation/cash_screen.dart';
import '../../customers/presentation/customers_screen.dart';
import '../../operations/presentation/operations_screen.dart';
import '../../orders/presentation/scan_screen.dart';
import '../../orders/presentation/new_order_screen.dart';
import '../../sync/presentation/sync_screen.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key, required this.session});

  final AuthSession session;

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final useRail = Responsive.usesNavigationRail(width);

    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        titleSpacing: 20,
        title: const NetCleanLockup(markSize: 34),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 14),
            child: IconButton(
              tooltip: 'Keluar',
              style: IconButton.styleFrom(
                backgroundColor: AppTheme.surface,
                side: const BorderSide(color: AppTheme.divider),
              ),
              onPressed: () =>
                  ref.read(sessionControllerProvider.notifier).logout(),
              icon: const Icon(Icons.logout_rounded, size: 20),
            ),
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: AppTheme.canvasGradient),
        child: SafeArea(
          top: false,
          child: LayoutBuilder(
            builder: (context, constraints) {
              final content = _content(context, constraints.maxWidth);

              if (!useRail) {
                return content;
              }

              return Row(
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 0, 16),
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppTheme.surface,
                        borderRadius: BorderRadius.circular(26),
                        border: Border.all(color: AppTheme.divider),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(26),
                        child: NavigationRail(
                          key: const Key('home-navigation-rail'),
                          selectedIndex: _selectedIndex,
                          onDestinationSelected: (index) =>
                              setState(() => _selectedIndex = index),
                          labelType: NavigationRailLabelType.all,
                          destinations: const [
                            NavigationRailDestination(
                              icon: Icon(Icons.grid_view_rounded),
                              selectedIcon: Icon(Icons.grid_view_rounded),
                              label: Text('Beranda'),
                            ),
                            NavigationRailDestination(
                              icon: Icon(Icons.people_outline_rounded),
                              selectedIcon: Icon(Icons.people_rounded),
                              label: Text('Pelanggan'),
                            ),
                            NavigationRailDestination(
                              icon: Icon(Icons.receipt_long_outlined),
                              selectedIcon: Icon(Icons.receipt_long_rounded),
                              label: Text('Pesanan'),
                            ),
                            NavigationRailDestination(
                              icon: Icon(Icons.sync_rounded),
                              selectedIcon: Icon(Icons.sync_rounded),
                              label: Text('Sinkron'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: content),
                ],
              );
            },
          ),
        ),
      ),
      bottomNavigationBar: useRail
          ? null
          : SafeArea(
              top: false,
              minimum: const EdgeInsets.fromLTRB(14, 0, 14, 12),
              child: Container(
                decoration: BoxDecoration(
                  color: AppTheme.surface,
                  borderRadius: BorderRadius.circular(26),
                  border: Border.all(color: AppTheme.divider),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primaryDeep.withValues(alpha: 0.08),
                      blurRadius: 28,
                      offset: const Offset(0, 12),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(26),
                  child: NavigationBar(
                    key: const Key('home-bottom-navigation'),
                    selectedIndex: _selectedIndex,
                    onDestinationSelected: (index) =>
                        setState(() => _selectedIndex = index),
                    destinations: const [
                      NavigationDestination(
                        icon: Icon(Icons.grid_view_rounded),
                        label: 'Beranda',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.people_outline_rounded),
                        label: 'Pelanggan',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.receipt_long_outlined),
                        label: 'Pesanan',
                      ),
                      NavigationDestination(
                        icon: Icon(Icons.sync_rounded),
                        label: 'Sinkron',
                      ),
                    ],
                  ),
                ),
              ),
            ),
    );
  }

  Widget _content(BuildContext context, double availableWidth) {
    if (_selectedIndex == 1) {
      return const CustomersScreen();
    }
    if (_selectedIndex == 2) {
      return const NewOrderScreen();
    }
    if (_selectedIndex == 3) {
      return const SyncScreen();
    }

    final padding = Responsive.horizontalPadding(
      MediaQuery.sizeOf(context).width,
    );
    final actionColumns = availableWidth >= 900 ? 4 : 2;
    final actionWidth = (availableWidth - (padding * 2) - 12) / actionColumns;

    return ResponsiveCenter(
      child: ListView(
        padding: EdgeInsets.fromLTRB(padding, 18, padding, 36),
        children: [
          const AppPageHeader(
            eyebrow: 'Ruang kerja',
            title: 'Halo, tim NetClean',
            subtitle: 'Semua yang dibutuhkan untuk melayani hari ini.',
          ),
          const SizedBox(height: 18),
          _HeroPanel(session: widget.session),
          const SizedBox(height: 26),
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 12,
            runSpacing: 4,
            children: [
              Text('Aksi cepat', style: Theme.of(context).textTheme.titleLarge),
              Text(
                'Pilih alur kerja',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              SizedBox(
                width: actionWidth,
                child: _QuickAction(
                  icon: Icons.add_shopping_cart_rounded,
                  title: 'Pesanan',
                  caption: 'Buat order baru',
                  onTap: () => setState(() => _selectedIndex = 2),
                ),
              ),
              SizedBox(
                width: actionWidth,
                child: _QuickAction(
                  icon: Icons.point_of_sale_rounded,
                  title: 'Kas',
                  caption: 'Shift & pembayaran',
                  color: AppTheme.primaryDeep,
                  backgroundColor: const Color(0xFFE8F4EF),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (context) => const CashScreen(),
                    ),
                  ),
                ),
              ),
              SizedBox(
                width: actionWidth,
                child: _QuickAction(
                  icon: Icons.precision_manufacturing_rounded,
                  title: 'Produksi',
                  caption: 'Task, QC & handover',
                  color: const Color(0xFF8A5A00),
                  backgroundColor: const Color(0xFFFFF5E0),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (context) => const OperationsScreen(),
                    ),
                  ),
                ),
              ),
              SizedBox(
                width: actionWidth,
                child: _QuickAction(
                  icon: Icons.qr_code_scanner_rounded,
                  title: 'Scan nota',
                  caption: 'Cari & buka order',
                  color: const Color(0xFF0E5F8A),
                  backgroundColor: const Color(0xFFE8F4FB),
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (context) => const ScanScreen(),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 26),
          Wrap(
            alignment: WrapAlignment.spaceBetween,
            crossAxisAlignment: WrapCrossAlignment.center,
            spacing: 12,
            runSpacing: 4,
            children: [
              Text(
                'Konteks kamu',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              TextButton(
                onPressed: () => setState(() => _selectedIndex = 3),
                child: const Text('Lihat sinkron'),
              ),
            ],
          ),
          const SizedBox(height: 6),
          SessionContextBar(session: widget.session),
          const SizedBox(height: 16),
          const _DevicePanel(),
        ],
      ),
    );
  }
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel({required this.session});

  final AuthSession session;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: const BoxDecoration(gradient: AppTheme.heroGradient),
        child: Stack(
          children: [
            Positioned(
              right: -46,
              top: -62,
              child: _GlowCircle(
                size: 190,
                color: AppTheme.accent.withValues(alpha: 0.16),
              ),
            ),
            Positioned(
              left: -48,
              bottom: -84,
              child: _GlowCircle(
                size: 190,
                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(22),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: AppTheme.accent,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(
                          Icons.local_laundry_service_rounded,
                          color: AppTheme.primaryDeep,
                          size: 22,
                        ),
                      ),
                      const Spacer(),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.09),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.1),
                          ),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            _StatusDot(),
                            SizedBox(width: 6),
                            Text(
                              'SESI AKTIF',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),
                  Text(
                    'Satu alur,\ntanpa ribet.',
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                      fontSize: 30,
                      height: 1.05,
                      letterSpacing: -0.9,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Pesanan, produksi, dan kas tetap rapi dalam satu alur kerja.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.68),
                    ),
                  ),
                  const SizedBox(height: 22),
                  Wrap(
                    spacing: 18,
                    runSpacing: 12,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          _HeroStat(
                            label: 'CABANG',
                            value: '${session.branchIds.length}',
                          ),
                          Container(
                            width: 1,
                            height: 32,
                            margin: const EdgeInsets.symmetric(horizontal: 18),
                            color: Colors.white.withValues(alpha: 0.13),
                          ),
                          _HeroStat(
                            label: 'LOKASI',
                            value: '${session.locationIds.length}',
                          ),
                        ],
                      ),
                      Text(
                        formatDateTime(session.expiresAt),
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.5),
                          fontSize: 10,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
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

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.45),
            fontSize: 9,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 19,
            fontWeight: FontWeight.w800,
            height: 1,
          ),
        ),
      ],
    );
  }
}

class _QuickAction extends StatelessWidget {
  const _QuickAction({
    required this.icon,
    required this.title,
    required this.caption,
    required this.onTap,
    this.color = AppTheme.primary,
    this.backgroundColor = AppTheme.primarySoft,
  });

  final IconData icon;
  final String title;
  final String caption;
  final VoidCallback onTap;
  final Color color;
  final Color backgroundColor;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppTheme.surface,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: AppTheme.divider),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: backgroundColor,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(icon, color: color, size: 21),
                  ),
                  const Spacer(),
                  Icon(
                    Icons.north_east_rounded,
                    size: 16,
                    color: AppTheme.textSecondary.withValues(alpha: 0.6),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(title, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 2),
              Text(
                caption,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DevicePanel extends StatelessWidget {
  const _DevicePanel();

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            const _DeviceRow(
              icon: Icons.lock_outline_rounded,
              title: 'Data lokal terlindungi',
              subtitle: 'Cache terenkripsi dan token disimpan aman.',
            ),
            const Divider(height: 26),
            const _DeviceRow(
              icon: Icons.cloud_done_outlined,
              title: 'Draft & replay siap',
              subtitle: 'Pantau antrean dari menu Sinkron.',
            ),
            const Divider(height: 26),
            const _DeviceRow(
              icon: Icons.schedule_rounded,
              title: 'Sesi perangkat',
              subtitle: 'Akses mengikuti masa berlaku token.',
            ),
          ],
        ),
      ),
    );
  }
}

class _DeviceRow extends StatelessWidget {
  const _DeviceRow({
    required this.icon,
    required this.title,
    required this.subtitle,
  });

  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        AppIconTile(icon: icon, size: 40),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: Theme.of(context).textTheme.titleSmall),
              const SizedBox(height: 1),
              Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
      ],
    );
  }
}

class _StatusDot extends StatelessWidget {
  const _StatusDot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 6,
      height: 6,
      decoration: const BoxDecoration(
        color: AppTheme.accent,
        shape: BoxShape.circle,
      ),
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
