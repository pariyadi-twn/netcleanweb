import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'app_database.g.dart';

class DraftOrders extends Table {
  TextColumn get tenantId => text()();
  TextColumn get userId => text()();
  TextColumn get draftId => text()();
  TextColumn get payloadCipher => text()();
  TextColumn get nonce => text()();
  TextColumn get mac => text()();
  IntColumn get version => integer().withDefault(const Constant(1))();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {tenantId, userId, draftId};
}

class PendingCommands extends Table {
  TextColumn get tenantId => text()();
  TextColumn get userId => text()();
  TextColumn get clientCommandId => text()();
  TextColumn get endpoint => text()();
  TextColumn get payloadCipher => text()();
  TextColumn get nonce => text()();
  TextColumn get mac => text()();
  TextColumn get dependenciesJson => text().withDefault(const Constant('[]'))();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  DateTimeColumn get nextRetryAt => dateTime().nullable()();
  TextColumn get errorCode => text().nullable()();
  TextColumn get responseCipher => text().nullable()();
  TextColumn get responseNonce => text().nullable()();
  TextColumn get responseMac => text().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {tenantId, userId, clientCommandId};
}

class PhotoUploads extends Table {
  TextColumn get tenantId => text()();
  TextColumn get userId => text()();
  TextColumn get uploadId => text()();
  TextColumn get draftId => text()();
  TextColumn get localPath => text()();
  TextColumn get mimeType => text()();
  TextColumn get checksum => text()();
  TextColumn get status => text().withDefault(const Constant('pending'))();
  IntColumn get attempts => integer().withDefault(const Constant(0))();
  DateTimeColumn get nextRetryAt => dateTime().nullable()();
  DateTimeColumn get createdAt => dateTime()();
  DateTimeColumn get updatedAt => dateTime()();

  @override
  Set<Column<Object>> get primaryKey => {tenantId, userId, uploadId};
}

class CachedRecords extends Table {
  TextColumn get tenantId => text()();
  TextColumn get userId => text()();
  TextColumn get kind => text()();
  TextColumn get remoteId => text()();
  TextColumn get cipherText => text()();
  TextColumn get nonce => text()();
  TextColumn get mac => text()();
  DateTimeColumn get cachedAt => dateTime()();
  DateTimeColumn get lastAccessedAt => dateTime().nullable()();

  @override
  Set<Column<Object>> get primaryKey => {tenantId, userId, kind, remoteId};
}

@DriftDatabase(
  tables: [CachedRecords, DraftOrders, PendingCommands, PhotoUploads],
)
class AppDatabase extends _$AppDatabase {
  AppDatabase(super.executor);

  factory AppDatabase.open() => AppDatabase(_openConnection());

  @override
  int get schemaVersion => 3;

  @override
  MigrationStrategy get migration => MigrationStrategy(
    onCreate: (migrator) async {
      await migrator.createAll();
    },
    onUpgrade: (migrator, from, to) async {
      if (from < 2) {
        await migrator.addColumn(cachedRecords, cachedRecords.lastAccessedAt);
      }
    },
  );
}

LazyDatabase _openConnection() {
  return LazyDatabase(() async {
    final directory = await getApplicationDocumentsDirectory();
    return NativeDatabase.createInBackground(
      File(p.join(directory.path, 'netclean_staff.sqlite')),
    );
  });
}
