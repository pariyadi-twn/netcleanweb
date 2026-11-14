import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/auth_session.dart';
import '../../../core/session/session_controller.dart';
import '../../../core/ui/responsive.dart';
import '../../../core/ui/session_context_bar.dart';
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
      appBar: AppBar(
        title: const Text('NetClean Staff'),
        actions: [
          IconButton(
            tooltip: 'Keluar',
            onPressed: () =>
                ref.read(sessionControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final content = _content(context, constraints.maxWidth);

            if (!useRail) {
              return content;
            }

            return Row(
              children: [
                NavigationRail(
                  key: const Key('home-navigation-rail'),
                  selectedIndex: _selectedIndex,
                  onDestinationSelected: (index) =>
                      setState(() => _selectedIndex = index),
                  labelType: NavigationRailLabelType.all,
                  destinations: const [
                    NavigationRailDestination(
                      icon: Icon(Icons.dashboard_outlined),
                      label: Text('Beranda'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.people_outline),
                      label: Text('Pelanggan'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.receipt_long_outlined),
                      label: Text('Pesanan'),
                    ),
                    NavigationRailDestination(
                      icon: Icon(Icons.sync_outlined),
                      label: Text('Sinkron'),
                    ),
                  ],
                ),
                const VerticalDivider(width: 1),
                Expanded(child: content),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: useRail
          ? null
          : NavigationBar(
              key: const Key('home-bottom-navigation'),
              selectedIndex: _selectedIndex,
              onDestinationSelected: (index) =>
                  setState(() => _selectedIndex = index),
              destinations: const [
                NavigationDestination(
                  icon: Icon(Icons.dashboard_outlined),
                  label: 'Beranda',
                ),
                NavigationDestination(
                  icon: Icon(Icons.people_outline),
                  label: 'Pelanggan',
                ),
                NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined),
                  label: 'Pesanan',
                ),
                NavigationDestination(
                  icon: Icon(Icons.sync_outlined),
                  label: 'Sinkron',
                ),
              ],
            ),
    );
  }

  Widget _content(BuildContext context, double availableWidth) {
    if (_selectedIndex == 3) {
      return const SyncScreen();
    }

    final columns = Responsive.gridColumns(availableWidth);
    final padding = Responsive.horizontalPadding(
      MediaQuery.sizeOf(context).width,
    );

    return ResponsiveCenter(
      child: ListView(
        padding: EdgeInsets.symmetric(horizontal: padding, vertical: 24),
        children: [
          Text(
            'Sesi staf aktif',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 8),
          Text('Token berakhir: ${widget.session.expiresAt.toLocal()}'),
          const SizedBox(height: 16),
          SessionContextBar(session: widget.session),
          const SizedBox(height: 24),
          GridView.count(
            crossAxisCount: columns,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 16,
            mainAxisSpacing: 16,
            childAspectRatio: columns == 1 ? 2.8 : 1.9,
            children: const [
              _HomeCard(
                icon: Icons.cloud_off_outlined,
                title: 'Cache terlindungi',
                body: 'Cache tenant/user dienkripsi dan dibatasi empat jam.',
              ),
              _HomeCard(
                icon: Icons.badge_outlined,
                title: 'Konteks perangkat',
                body:
                    'Token disimpan di secure storage dan dipisahkan per instalasi.',
              ),
              _HomeCard(
                icon: Icons.sync_outlined,
                title: 'Sinkronisasi',
                body: 'Draft lokal dan replay command siap dipantau.',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HomeCard extends StatelessWidget {
  const _HomeCard({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 6),
            Expanded(child: Text(body, overflow: TextOverflow.fade)),
          ],
        ),
      ),
    );
  }
}
