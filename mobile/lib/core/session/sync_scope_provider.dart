import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../sync/command_queue.dart';
import 'session_controller.dart';

final syncScopeProvider = Provider<SyncScope?>((ref) {
  final session = ref.watch(sessionControllerProvider).asData?.value;
  if (session == null ||
      session.tenantId == null ||
      session.membershipId == null) {
    return null;
  }
  return SyncScope(tenantId: session.tenantId!, userId: session.membershipId!);
});
