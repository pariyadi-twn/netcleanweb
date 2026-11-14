import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/session/session_controller.dart';
import 'core/ui/theme.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/home/presentation/home_screen.dart';

class NetCleanApp extends ConsumerWidget {
  const NetCleanApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final session = ref.watch(sessionControllerProvider);

    return MaterialApp(
      title: 'NetClean Staff',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      home: session.when(
        loading: () =>
            const Scaffold(body: Center(child: CircularProgressIndicator())),
        error: (error, stackTrace) => const LoginScreen(),
        data: (value) =>
            value == null ? const LoginScreen() : HomeScreen(session: value),
      ),
    );
  }
}
