// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'app_database.dart';

// ignore_for_file: type=lint
class $CachedRecordsTable extends CachedRecords
    with TableInfo<$CachedRecordsTable, CachedRecord> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedRecordsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _tenantIdMeta = const VerificationMeta(
    'tenantId',
  );
  @override
  late final GeneratedColumn<String> tenantId = GeneratedColumn<String>(
    'tenant_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _kindMeta = const VerificationMeta('kind');
  @override
  late final GeneratedColumn<String> kind = GeneratedColumn<String>(
    'kind',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _remoteIdMeta = const VerificationMeta(
    'remoteId',
  );
  @override
  late final GeneratedColumn<String> remoteId = GeneratedColumn<String>(
    'remote_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _cipherTextMeta = const VerificationMeta(
    'cipherText',
  );
  @override
  late final GeneratedColumn<String> cipherText = GeneratedColumn<String>(
    'cipher_text',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _nonceMeta = const VerificationMeta('nonce');
  @override
  late final GeneratedColumn<String> nonce = GeneratedColumn<String>(
    'nonce',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _macMeta = const VerificationMeta('mac');
  @override
  late final GeneratedColumn<String> mac = GeneratedColumn<String>(
    'mac',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _cachedAtMeta = const VerificationMeta(
    'cachedAt',
  );
  @override
  late final GeneratedColumn<DateTime> cachedAt = GeneratedColumn<DateTime>(
    'cached_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _lastAccessedAtMeta = const VerificationMeta(
    'lastAccessedAt',
  );
  @override
  late final GeneratedColumn<DateTime> lastAccessedAt =
      GeneratedColumn<DateTime>(
        'last_accessed_at',
        aliasedName,
        true,
        type: DriftSqlType.dateTime,
        requiredDuringInsert: false,
      );
  @override
  List<GeneratedColumn> get $columns => [
    tenantId,
    userId,
    kind,
    remoteId,
    cipherText,
    nonce,
    mac,
    cachedAt,
    lastAccessedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_records';
  @override
  VerificationContext validateIntegrity(
    Insertable<CachedRecord> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('tenant_id')) {
      context.handle(
        _tenantIdMeta,
        tenantId.isAcceptableOrUnknown(data['tenant_id']!, _tenantIdMeta),
      );
    } else if (isInserting) {
      context.missing(_tenantIdMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('kind')) {
      context.handle(
        _kindMeta,
        kind.isAcceptableOrUnknown(data['kind']!, _kindMeta),
      );
    } else if (isInserting) {
      context.missing(_kindMeta);
    }
    if (data.containsKey('remote_id')) {
      context.handle(
        _remoteIdMeta,
        remoteId.isAcceptableOrUnknown(data['remote_id']!, _remoteIdMeta),
      );
    } else if (isInserting) {
      context.missing(_remoteIdMeta);
    }
    if (data.containsKey('cipher_text')) {
      context.handle(
        _cipherTextMeta,
        cipherText.isAcceptableOrUnknown(data['cipher_text']!, _cipherTextMeta),
      );
    } else if (isInserting) {
      context.missing(_cipherTextMeta);
    }
    if (data.containsKey('nonce')) {
      context.handle(
        _nonceMeta,
        nonce.isAcceptableOrUnknown(data['nonce']!, _nonceMeta),
      );
    } else if (isInserting) {
      context.missing(_nonceMeta);
    }
    if (data.containsKey('mac')) {
      context.handle(
        _macMeta,
        mac.isAcceptableOrUnknown(data['mac']!, _macMeta),
      );
    } else if (isInserting) {
      context.missing(_macMeta);
    }
    if (data.containsKey('cached_at')) {
      context.handle(
        _cachedAtMeta,
        cachedAt.isAcceptableOrUnknown(data['cached_at']!, _cachedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_cachedAtMeta);
    }
    if (data.containsKey('last_accessed_at')) {
      context.handle(
        _lastAccessedAtMeta,
        lastAccessedAt.isAcceptableOrUnknown(
          data['last_accessed_at']!,
          _lastAccessedAtMeta,
        ),
      );
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {tenantId, userId, kind, remoteId};
  @override
  CachedRecord map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedRecord(
      tenantId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}tenant_id'],
      )!,
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}user_id'],
      )!,
      kind: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}kind'],
      )!,
      remoteId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}remote_id'],
      )!,
      cipherText: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}cipher_text'],
      )!,
      nonce: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nonce'],
      )!,
      mac: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mac'],
      )!,
      cachedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}cached_at'],
      )!,
      lastAccessedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}last_accessed_at'],
      ),
    );
  }

  @override
  $CachedRecordsTable createAlias(String alias) {
    return $CachedRecordsTable(attachedDatabase, alias);
  }
}

class CachedRecord extends DataClass implements Insertable<CachedRecord> {
  final String tenantId;
  final String userId;
  final String kind;
  final String remoteId;
  final String cipherText;
  final String nonce;
  final String mac;
  final DateTime cachedAt;
  final DateTime? lastAccessedAt;
  const CachedRecord({
    required this.tenantId,
    required this.userId,
    required this.kind,
    required this.remoteId,
    required this.cipherText,
    required this.nonce,
    required this.mac,
    required this.cachedAt,
    this.lastAccessedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['tenant_id'] = Variable<String>(tenantId);
    map['user_id'] = Variable<String>(userId);
    map['kind'] = Variable<String>(kind);
    map['remote_id'] = Variable<String>(remoteId);
    map['cipher_text'] = Variable<String>(cipherText);
    map['nonce'] = Variable<String>(nonce);
    map['mac'] = Variable<String>(mac);
    map['cached_at'] = Variable<DateTime>(cachedAt);
    if (!nullToAbsent || lastAccessedAt != null) {
      map['last_accessed_at'] = Variable<DateTime>(lastAccessedAt);
    }
    return map;
  }

  CachedRecordsCompanion toCompanion(bool nullToAbsent) {
    return CachedRecordsCompanion(
      tenantId: Value(tenantId),
      userId: Value(userId),
      kind: Value(kind),
      remoteId: Value(remoteId),
      cipherText: Value(cipherText),
      nonce: Value(nonce),
      mac: Value(mac),
      cachedAt: Value(cachedAt),
      lastAccessedAt: lastAccessedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastAccessedAt),
    );
  }

  factory CachedRecord.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedRecord(
      tenantId: serializer.fromJson<String>(json['tenantId']),
      userId: serializer.fromJson<String>(json['userId']),
      kind: serializer.fromJson<String>(json['kind']),
      remoteId: serializer.fromJson<String>(json['remoteId']),
      cipherText: serializer.fromJson<String>(json['cipherText']),
      nonce: serializer.fromJson<String>(json['nonce']),
      mac: serializer.fromJson<String>(json['mac']),
      cachedAt: serializer.fromJson<DateTime>(json['cachedAt']),
      lastAccessedAt: serializer.fromJson<DateTime?>(json['lastAccessedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'tenantId': serializer.toJson<String>(tenantId),
      'userId': serializer.toJson<String>(userId),
      'kind': serializer.toJson<String>(kind),
      'remoteId': serializer.toJson<String>(remoteId),
      'cipherText': serializer.toJson<String>(cipherText),
      'nonce': serializer.toJson<String>(nonce),
      'mac': serializer.toJson<String>(mac),
      'cachedAt': serializer.toJson<DateTime>(cachedAt),
      'lastAccessedAt': serializer.toJson<DateTime?>(lastAccessedAt),
    };
  }

  CachedRecord copyWith({
    String? tenantId,
    String? userId,
    String? kind,
    String? remoteId,
    String? cipherText,
    String? nonce,
    String? mac,
    DateTime? cachedAt,
    Value<DateTime?> lastAccessedAt = const Value.absent(),
  }) => CachedRecord(
    tenantId: tenantId ?? this.tenantId,
    userId: userId ?? this.userId,
    kind: kind ?? this.kind,
    remoteId: remoteId ?? this.remoteId,
    cipherText: cipherText ?? this.cipherText,
    nonce: nonce ?? this.nonce,
    mac: mac ?? this.mac,
    cachedAt: cachedAt ?? this.cachedAt,
    lastAccessedAt: lastAccessedAt.present
        ? lastAccessedAt.value
        : this.lastAccessedAt,
  );
  CachedRecord copyWithCompanion(CachedRecordsCompanion data) {
    return CachedRecord(
      tenantId: data.tenantId.present ? data.tenantId.value : this.tenantId,
      userId: data.userId.present ? data.userId.value : this.userId,
      kind: data.kind.present ? data.kind.value : this.kind,
      remoteId: data.remoteId.present ? data.remoteId.value : this.remoteId,
      cipherText: data.cipherText.present
          ? data.cipherText.value
          : this.cipherText,
      nonce: data.nonce.present ? data.nonce.value : this.nonce,
      mac: data.mac.present ? data.mac.value : this.mac,
      cachedAt: data.cachedAt.present ? data.cachedAt.value : this.cachedAt,
      lastAccessedAt: data.lastAccessedAt.present
          ? data.lastAccessedAt.value
          : this.lastAccessedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedRecord(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('kind: $kind, ')
          ..write('remoteId: $remoteId, ')
          ..write('cipherText: $cipherText, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('cachedAt: $cachedAt, ')
          ..write('lastAccessedAt: $lastAccessedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    tenantId,
    userId,
    kind,
    remoteId,
    cipherText,
    nonce,
    mac,
    cachedAt,
    lastAccessedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedRecord &&
          other.tenantId == this.tenantId &&
          other.userId == this.userId &&
          other.kind == this.kind &&
          other.remoteId == this.remoteId &&
          other.cipherText == this.cipherText &&
          other.nonce == this.nonce &&
          other.mac == this.mac &&
          other.cachedAt == this.cachedAt &&
          other.lastAccessedAt == this.lastAccessedAt);
}

class CachedRecordsCompanion extends UpdateCompanion<CachedRecord> {
  final Value<String> tenantId;
  final Value<String> userId;
  final Value<String> kind;
  final Value<String> remoteId;
  final Value<String> cipherText;
  final Value<String> nonce;
  final Value<String> mac;
  final Value<DateTime> cachedAt;
  final Value<DateTime?> lastAccessedAt;
  final Value<int> rowid;
  const CachedRecordsCompanion({
    this.tenantId = const Value.absent(),
    this.userId = const Value.absent(),
    this.kind = const Value.absent(),
    this.remoteId = const Value.absent(),
    this.cipherText = const Value.absent(),
    this.nonce = const Value.absent(),
    this.mac = const Value.absent(),
    this.cachedAt = const Value.absent(),
    this.lastAccessedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedRecordsCompanion.insert({
    required String tenantId,
    required String userId,
    required String kind,
    required String remoteId,
    required String cipherText,
    required String nonce,
    required String mac,
    required DateTime cachedAt,
    this.lastAccessedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : tenantId = Value(tenantId),
       userId = Value(userId),
       kind = Value(kind),
       remoteId = Value(remoteId),
       cipherText = Value(cipherText),
       nonce = Value(nonce),
       mac = Value(mac),
       cachedAt = Value(cachedAt);
  static Insertable<CachedRecord> custom({
    Expression<String>? tenantId,
    Expression<String>? userId,
    Expression<String>? kind,
    Expression<String>? remoteId,
    Expression<String>? cipherText,
    Expression<String>? nonce,
    Expression<String>? mac,
    Expression<DateTime>? cachedAt,
    Expression<DateTime>? lastAccessedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (tenantId != null) 'tenant_id': tenantId,
      if (userId != null) 'user_id': userId,
      if (kind != null) 'kind': kind,
      if (remoteId != null) 'remote_id': remoteId,
      if (cipherText != null) 'cipher_text': cipherText,
      if (nonce != null) 'nonce': nonce,
      if (mac != null) 'mac': mac,
      if (cachedAt != null) 'cached_at': cachedAt,
      if (lastAccessedAt != null) 'last_accessed_at': lastAccessedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedRecordsCompanion copyWith({
    Value<String>? tenantId,
    Value<String>? userId,
    Value<String>? kind,
    Value<String>? remoteId,
    Value<String>? cipherText,
    Value<String>? nonce,
    Value<String>? mac,
    Value<DateTime>? cachedAt,
    Value<DateTime?>? lastAccessedAt,
    Value<int>? rowid,
  }) {
    return CachedRecordsCompanion(
      tenantId: tenantId ?? this.tenantId,
      userId: userId ?? this.userId,
      kind: kind ?? this.kind,
      remoteId: remoteId ?? this.remoteId,
      cipherText: cipherText ?? this.cipherText,
      nonce: nonce ?? this.nonce,
      mac: mac ?? this.mac,
      cachedAt: cachedAt ?? this.cachedAt,
      lastAccessedAt: lastAccessedAt ?? this.lastAccessedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (tenantId.present) {
      map['tenant_id'] = Variable<String>(tenantId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (kind.present) {
      map['kind'] = Variable<String>(kind.value);
    }
    if (remoteId.present) {
      map['remote_id'] = Variable<String>(remoteId.value);
    }
    if (cipherText.present) {
      map['cipher_text'] = Variable<String>(cipherText.value);
    }
    if (nonce.present) {
      map['nonce'] = Variable<String>(nonce.value);
    }
    if (mac.present) {
      map['mac'] = Variable<String>(mac.value);
    }
    if (cachedAt.present) {
      map['cached_at'] = Variable<DateTime>(cachedAt.value);
    }
    if (lastAccessedAt.present) {
      map['last_accessed_at'] = Variable<DateTime>(lastAccessedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedRecordsCompanion(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('kind: $kind, ')
          ..write('remoteId: $remoteId, ')
          ..write('cipherText: $cipherText, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('cachedAt: $cachedAt, ')
          ..write('lastAccessedAt: $lastAccessedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $DraftOrdersTable extends DraftOrders
    with TableInfo<$DraftOrdersTable, DraftOrder> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $DraftOrdersTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _tenantIdMeta = const VerificationMeta(
    'tenantId',
  );
  @override
  late final GeneratedColumn<String> tenantId = GeneratedColumn<String>(
    'tenant_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _draftIdMeta = const VerificationMeta(
    'draftId',
  );
  @override
  late final GeneratedColumn<String> draftId = GeneratedColumn<String>(
    'draft_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadCipherMeta = const VerificationMeta(
    'payloadCipher',
  );
  @override
  late final GeneratedColumn<String> payloadCipher = GeneratedColumn<String>(
    'payload_cipher',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _nonceMeta = const VerificationMeta('nonce');
  @override
  late final GeneratedColumn<String> nonce = GeneratedColumn<String>(
    'nonce',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _macMeta = const VerificationMeta('mac');
  @override
  late final GeneratedColumn<String> mac = GeneratedColumn<String>(
    'mac',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _versionMeta = const VerificationMeta(
    'version',
  );
  @override
  late final GeneratedColumn<int> version = GeneratedColumn<int>(
    'version',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(1),
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    tenantId,
    userId,
    draftId,
    payloadCipher,
    nonce,
    mac,
    version,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'draft_orders';
  @override
  VerificationContext validateIntegrity(
    Insertable<DraftOrder> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('tenant_id')) {
      context.handle(
        _tenantIdMeta,
        tenantId.isAcceptableOrUnknown(data['tenant_id']!, _tenantIdMeta),
      );
    } else if (isInserting) {
      context.missing(_tenantIdMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('draft_id')) {
      context.handle(
        _draftIdMeta,
        draftId.isAcceptableOrUnknown(data['draft_id']!, _draftIdMeta),
      );
    } else if (isInserting) {
      context.missing(_draftIdMeta);
    }
    if (data.containsKey('payload_cipher')) {
      context.handle(
        _payloadCipherMeta,
        payloadCipher.isAcceptableOrUnknown(
          data['payload_cipher']!,
          _payloadCipherMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_payloadCipherMeta);
    }
    if (data.containsKey('nonce')) {
      context.handle(
        _nonceMeta,
        nonce.isAcceptableOrUnknown(data['nonce']!, _nonceMeta),
      );
    } else if (isInserting) {
      context.missing(_nonceMeta);
    }
    if (data.containsKey('mac')) {
      context.handle(
        _macMeta,
        mac.isAcceptableOrUnknown(data['mac']!, _macMeta),
      );
    } else if (isInserting) {
      context.missing(_macMeta);
    }
    if (data.containsKey('version')) {
      context.handle(
        _versionMeta,
        version.isAcceptableOrUnknown(data['version']!, _versionMeta),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {tenantId, userId, draftId};
  @override
  DraftOrder map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return DraftOrder(
      tenantId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}tenant_id'],
      )!,
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}user_id'],
      )!,
      draftId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}draft_id'],
      )!,
      payloadCipher: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload_cipher'],
      )!,
      nonce: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nonce'],
      )!,
      mac: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mac'],
      )!,
      version: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}version'],
      )!,
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $DraftOrdersTable createAlias(String alias) {
    return $DraftOrdersTable(attachedDatabase, alias);
  }
}

class DraftOrder extends DataClass implements Insertable<DraftOrder> {
  final String tenantId;
  final String userId;
  final String draftId;
  final String payloadCipher;
  final String nonce;
  final String mac;
  final int version;
  final DateTime createdAt;
  final DateTime updatedAt;
  const DraftOrder({
    required this.tenantId,
    required this.userId,
    required this.draftId,
    required this.payloadCipher,
    required this.nonce,
    required this.mac,
    required this.version,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['tenant_id'] = Variable<String>(tenantId);
    map['user_id'] = Variable<String>(userId);
    map['draft_id'] = Variable<String>(draftId);
    map['payload_cipher'] = Variable<String>(payloadCipher);
    map['nonce'] = Variable<String>(nonce);
    map['mac'] = Variable<String>(mac);
    map['version'] = Variable<int>(version);
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  DraftOrdersCompanion toCompanion(bool nullToAbsent) {
    return DraftOrdersCompanion(
      tenantId: Value(tenantId),
      userId: Value(userId),
      draftId: Value(draftId),
      payloadCipher: Value(payloadCipher),
      nonce: Value(nonce),
      mac: Value(mac),
      version: Value(version),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory DraftOrder.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return DraftOrder(
      tenantId: serializer.fromJson<String>(json['tenantId']),
      userId: serializer.fromJson<String>(json['userId']),
      draftId: serializer.fromJson<String>(json['draftId']),
      payloadCipher: serializer.fromJson<String>(json['payloadCipher']),
      nonce: serializer.fromJson<String>(json['nonce']),
      mac: serializer.fromJson<String>(json['mac']),
      version: serializer.fromJson<int>(json['version']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'tenantId': serializer.toJson<String>(tenantId),
      'userId': serializer.toJson<String>(userId),
      'draftId': serializer.toJson<String>(draftId),
      'payloadCipher': serializer.toJson<String>(payloadCipher),
      'nonce': serializer.toJson<String>(nonce),
      'mac': serializer.toJson<String>(mac),
      'version': serializer.toJson<int>(version),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  DraftOrder copyWith({
    String? tenantId,
    String? userId,
    String? draftId,
    String? payloadCipher,
    String? nonce,
    String? mac,
    int? version,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => DraftOrder(
    tenantId: tenantId ?? this.tenantId,
    userId: userId ?? this.userId,
    draftId: draftId ?? this.draftId,
    payloadCipher: payloadCipher ?? this.payloadCipher,
    nonce: nonce ?? this.nonce,
    mac: mac ?? this.mac,
    version: version ?? this.version,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  DraftOrder copyWithCompanion(DraftOrdersCompanion data) {
    return DraftOrder(
      tenantId: data.tenantId.present ? data.tenantId.value : this.tenantId,
      userId: data.userId.present ? data.userId.value : this.userId,
      draftId: data.draftId.present ? data.draftId.value : this.draftId,
      payloadCipher: data.payloadCipher.present
          ? data.payloadCipher.value
          : this.payloadCipher,
      nonce: data.nonce.present ? data.nonce.value : this.nonce,
      mac: data.mac.present ? data.mac.value : this.mac,
      version: data.version.present ? data.version.value : this.version,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('DraftOrder(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('draftId: $draftId, ')
          ..write('payloadCipher: $payloadCipher, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('version: $version, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    tenantId,
    userId,
    draftId,
    payloadCipher,
    nonce,
    mac,
    version,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is DraftOrder &&
          other.tenantId == this.tenantId &&
          other.userId == this.userId &&
          other.draftId == this.draftId &&
          other.payloadCipher == this.payloadCipher &&
          other.nonce == this.nonce &&
          other.mac == this.mac &&
          other.version == this.version &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class DraftOrdersCompanion extends UpdateCompanion<DraftOrder> {
  final Value<String> tenantId;
  final Value<String> userId;
  final Value<String> draftId;
  final Value<String> payloadCipher;
  final Value<String> nonce;
  final Value<String> mac;
  final Value<int> version;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const DraftOrdersCompanion({
    this.tenantId = const Value.absent(),
    this.userId = const Value.absent(),
    this.draftId = const Value.absent(),
    this.payloadCipher = const Value.absent(),
    this.nonce = const Value.absent(),
    this.mac = const Value.absent(),
    this.version = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  DraftOrdersCompanion.insert({
    required String tenantId,
    required String userId,
    required String draftId,
    required String payloadCipher,
    required String nonce,
    required String mac,
    this.version = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : tenantId = Value(tenantId),
       userId = Value(userId),
       draftId = Value(draftId),
       payloadCipher = Value(payloadCipher),
       nonce = Value(nonce),
       mac = Value(mac),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<DraftOrder> custom({
    Expression<String>? tenantId,
    Expression<String>? userId,
    Expression<String>? draftId,
    Expression<String>? payloadCipher,
    Expression<String>? nonce,
    Expression<String>? mac,
    Expression<int>? version,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (tenantId != null) 'tenant_id': tenantId,
      if (userId != null) 'user_id': userId,
      if (draftId != null) 'draft_id': draftId,
      if (payloadCipher != null) 'payload_cipher': payloadCipher,
      if (nonce != null) 'nonce': nonce,
      if (mac != null) 'mac': mac,
      if (version != null) 'version': version,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  DraftOrdersCompanion copyWith({
    Value<String>? tenantId,
    Value<String>? userId,
    Value<String>? draftId,
    Value<String>? payloadCipher,
    Value<String>? nonce,
    Value<String>? mac,
    Value<int>? version,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return DraftOrdersCompanion(
      tenantId: tenantId ?? this.tenantId,
      userId: userId ?? this.userId,
      draftId: draftId ?? this.draftId,
      payloadCipher: payloadCipher ?? this.payloadCipher,
      nonce: nonce ?? this.nonce,
      mac: mac ?? this.mac,
      version: version ?? this.version,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (tenantId.present) {
      map['tenant_id'] = Variable<String>(tenantId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (draftId.present) {
      map['draft_id'] = Variable<String>(draftId.value);
    }
    if (payloadCipher.present) {
      map['payload_cipher'] = Variable<String>(payloadCipher.value);
    }
    if (nonce.present) {
      map['nonce'] = Variable<String>(nonce.value);
    }
    if (mac.present) {
      map['mac'] = Variable<String>(mac.value);
    }
    if (version.present) {
      map['version'] = Variable<int>(version.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('DraftOrdersCompanion(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('draftId: $draftId, ')
          ..write('payloadCipher: $payloadCipher, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('version: $version, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $PendingCommandsTable extends PendingCommands
    with TableInfo<$PendingCommandsTable, PendingCommand> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingCommandsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _tenantIdMeta = const VerificationMeta(
    'tenantId',
  );
  @override
  late final GeneratedColumn<String> tenantId = GeneratedColumn<String>(
    'tenant_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _clientCommandIdMeta = const VerificationMeta(
    'clientCommandId',
  );
  @override
  late final GeneratedColumn<String> clientCommandId = GeneratedColumn<String>(
    'client_command_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _endpointMeta = const VerificationMeta(
    'endpoint',
  );
  @override
  late final GeneratedColumn<String> endpoint = GeneratedColumn<String>(
    'endpoint',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _payloadCipherMeta = const VerificationMeta(
    'payloadCipher',
  );
  @override
  late final GeneratedColumn<String> payloadCipher = GeneratedColumn<String>(
    'payload_cipher',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _nonceMeta = const VerificationMeta('nonce');
  @override
  late final GeneratedColumn<String> nonce = GeneratedColumn<String>(
    'nonce',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _macMeta = const VerificationMeta('mac');
  @override
  late final GeneratedColumn<String> mac = GeneratedColumn<String>(
    'mac',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _dependenciesJsonMeta = const VerificationMeta(
    'dependenciesJson',
  );
  @override
  late final GeneratedColumn<String> dependenciesJson = GeneratedColumn<String>(
    'dependencies_json',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('[]'),
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextRetryAtMeta = const VerificationMeta(
    'nextRetryAt',
  );
  @override
  late final GeneratedColumn<DateTime> nextRetryAt = GeneratedColumn<DateTime>(
    'next_retry_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _errorCodeMeta = const VerificationMeta(
    'errorCode',
  );
  @override
  late final GeneratedColumn<String> errorCode = GeneratedColumn<String>(
    'error_code',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _responseCipherMeta = const VerificationMeta(
    'responseCipher',
  );
  @override
  late final GeneratedColumn<String> responseCipher = GeneratedColumn<String>(
    'response_cipher',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _responseNonceMeta = const VerificationMeta(
    'responseNonce',
  );
  @override
  late final GeneratedColumn<String> responseNonce = GeneratedColumn<String>(
    'response_nonce',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _responseMacMeta = const VerificationMeta(
    'responseMac',
  );
  @override
  late final GeneratedColumn<String> responseMac = GeneratedColumn<String>(
    'response_mac',
    aliasedName,
    true,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    tenantId,
    userId,
    clientCommandId,
    endpoint,
    payloadCipher,
    nonce,
    mac,
    dependenciesJson,
    status,
    attempts,
    nextRetryAt,
    errorCode,
    responseCipher,
    responseNonce,
    responseMac,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_commands';
  @override
  VerificationContext validateIntegrity(
    Insertable<PendingCommand> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('tenant_id')) {
      context.handle(
        _tenantIdMeta,
        tenantId.isAcceptableOrUnknown(data['tenant_id']!, _tenantIdMeta),
      );
    } else if (isInserting) {
      context.missing(_tenantIdMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('client_command_id')) {
      context.handle(
        _clientCommandIdMeta,
        clientCommandId.isAcceptableOrUnknown(
          data['client_command_id']!,
          _clientCommandIdMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_clientCommandIdMeta);
    }
    if (data.containsKey('endpoint')) {
      context.handle(
        _endpointMeta,
        endpoint.isAcceptableOrUnknown(data['endpoint']!, _endpointMeta),
      );
    } else if (isInserting) {
      context.missing(_endpointMeta);
    }
    if (data.containsKey('payload_cipher')) {
      context.handle(
        _payloadCipherMeta,
        payloadCipher.isAcceptableOrUnknown(
          data['payload_cipher']!,
          _payloadCipherMeta,
        ),
      );
    } else if (isInserting) {
      context.missing(_payloadCipherMeta);
    }
    if (data.containsKey('nonce')) {
      context.handle(
        _nonceMeta,
        nonce.isAcceptableOrUnknown(data['nonce']!, _nonceMeta),
      );
    } else if (isInserting) {
      context.missing(_nonceMeta);
    }
    if (data.containsKey('mac')) {
      context.handle(
        _macMeta,
        mac.isAcceptableOrUnknown(data['mac']!, _macMeta),
      );
    } else if (isInserting) {
      context.missing(_macMeta);
    }
    if (data.containsKey('dependencies_json')) {
      context.handle(
        _dependenciesJsonMeta,
        dependenciesJson.isAcceptableOrUnknown(
          data['dependencies_json']!,
          _dependenciesJsonMeta,
        ),
      );
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('next_retry_at')) {
      context.handle(
        _nextRetryAtMeta,
        nextRetryAt.isAcceptableOrUnknown(
          data['next_retry_at']!,
          _nextRetryAtMeta,
        ),
      );
    }
    if (data.containsKey('error_code')) {
      context.handle(
        _errorCodeMeta,
        errorCode.isAcceptableOrUnknown(data['error_code']!, _errorCodeMeta),
      );
    }
    if (data.containsKey('response_cipher')) {
      context.handle(
        _responseCipherMeta,
        responseCipher.isAcceptableOrUnknown(
          data['response_cipher']!,
          _responseCipherMeta,
        ),
      );
    }
    if (data.containsKey('response_nonce')) {
      context.handle(
        _responseNonceMeta,
        responseNonce.isAcceptableOrUnknown(
          data['response_nonce']!,
          _responseNonceMeta,
        ),
      );
    }
    if (data.containsKey('response_mac')) {
      context.handle(
        _responseMacMeta,
        responseMac.isAcceptableOrUnknown(
          data['response_mac']!,
          _responseMacMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {tenantId, userId, clientCommandId};
  @override
  PendingCommand map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingCommand(
      tenantId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}tenant_id'],
      )!,
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}user_id'],
      )!,
      clientCommandId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}client_command_id'],
      )!,
      endpoint: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}endpoint'],
      )!,
      payloadCipher: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}payload_cipher'],
      )!,
      nonce: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}nonce'],
      )!,
      mac: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mac'],
      )!,
      dependenciesJson: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}dependencies_json'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      nextRetryAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}next_retry_at'],
      ),
      errorCode: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}error_code'],
      ),
      responseCipher: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}response_cipher'],
      ),
      responseNonce: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}response_nonce'],
      ),
      responseMac: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}response_mac'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $PendingCommandsTable createAlias(String alias) {
    return $PendingCommandsTable(attachedDatabase, alias);
  }
}

class PendingCommand extends DataClass implements Insertable<PendingCommand> {
  final String tenantId;
  final String userId;
  final String clientCommandId;
  final String endpoint;
  final String payloadCipher;
  final String nonce;
  final String mac;
  final String dependenciesJson;
  final String status;
  final int attempts;
  final DateTime? nextRetryAt;
  final String? errorCode;
  final String? responseCipher;
  final String? responseNonce;
  final String? responseMac;
  final DateTime createdAt;
  final DateTime updatedAt;
  const PendingCommand({
    required this.tenantId,
    required this.userId,
    required this.clientCommandId,
    required this.endpoint,
    required this.payloadCipher,
    required this.nonce,
    required this.mac,
    required this.dependenciesJson,
    required this.status,
    required this.attempts,
    this.nextRetryAt,
    this.errorCode,
    this.responseCipher,
    this.responseNonce,
    this.responseMac,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['tenant_id'] = Variable<String>(tenantId);
    map['user_id'] = Variable<String>(userId);
    map['client_command_id'] = Variable<String>(clientCommandId);
    map['endpoint'] = Variable<String>(endpoint);
    map['payload_cipher'] = Variable<String>(payloadCipher);
    map['nonce'] = Variable<String>(nonce);
    map['mac'] = Variable<String>(mac);
    map['dependencies_json'] = Variable<String>(dependenciesJson);
    map['status'] = Variable<String>(status);
    map['attempts'] = Variable<int>(attempts);
    if (!nullToAbsent || nextRetryAt != null) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt);
    }
    if (!nullToAbsent || errorCode != null) {
      map['error_code'] = Variable<String>(errorCode);
    }
    if (!nullToAbsent || responseCipher != null) {
      map['response_cipher'] = Variable<String>(responseCipher);
    }
    if (!nullToAbsent || responseNonce != null) {
      map['response_nonce'] = Variable<String>(responseNonce);
    }
    if (!nullToAbsent || responseMac != null) {
      map['response_mac'] = Variable<String>(responseMac);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  PendingCommandsCompanion toCompanion(bool nullToAbsent) {
    return PendingCommandsCompanion(
      tenantId: Value(tenantId),
      userId: Value(userId),
      clientCommandId: Value(clientCommandId),
      endpoint: Value(endpoint),
      payloadCipher: Value(payloadCipher),
      nonce: Value(nonce),
      mac: Value(mac),
      dependenciesJson: Value(dependenciesJson),
      status: Value(status),
      attempts: Value(attempts),
      nextRetryAt: nextRetryAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextRetryAt),
      errorCode: errorCode == null && nullToAbsent
          ? const Value.absent()
          : Value(errorCode),
      responseCipher: responseCipher == null && nullToAbsent
          ? const Value.absent()
          : Value(responseCipher),
      responseNonce: responseNonce == null && nullToAbsent
          ? const Value.absent()
          : Value(responseNonce),
      responseMac: responseMac == null && nullToAbsent
          ? const Value.absent()
          : Value(responseMac),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory PendingCommand.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingCommand(
      tenantId: serializer.fromJson<String>(json['tenantId']),
      userId: serializer.fromJson<String>(json['userId']),
      clientCommandId: serializer.fromJson<String>(json['clientCommandId']),
      endpoint: serializer.fromJson<String>(json['endpoint']),
      payloadCipher: serializer.fromJson<String>(json['payloadCipher']),
      nonce: serializer.fromJson<String>(json['nonce']),
      mac: serializer.fromJson<String>(json['mac']),
      dependenciesJson: serializer.fromJson<String>(json['dependenciesJson']),
      status: serializer.fromJson<String>(json['status']),
      attempts: serializer.fromJson<int>(json['attempts']),
      nextRetryAt: serializer.fromJson<DateTime?>(json['nextRetryAt']),
      errorCode: serializer.fromJson<String?>(json['errorCode']),
      responseCipher: serializer.fromJson<String?>(json['responseCipher']),
      responseNonce: serializer.fromJson<String?>(json['responseNonce']),
      responseMac: serializer.fromJson<String?>(json['responseMac']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'tenantId': serializer.toJson<String>(tenantId),
      'userId': serializer.toJson<String>(userId),
      'clientCommandId': serializer.toJson<String>(clientCommandId),
      'endpoint': serializer.toJson<String>(endpoint),
      'payloadCipher': serializer.toJson<String>(payloadCipher),
      'nonce': serializer.toJson<String>(nonce),
      'mac': serializer.toJson<String>(mac),
      'dependenciesJson': serializer.toJson<String>(dependenciesJson),
      'status': serializer.toJson<String>(status),
      'attempts': serializer.toJson<int>(attempts),
      'nextRetryAt': serializer.toJson<DateTime?>(nextRetryAt),
      'errorCode': serializer.toJson<String?>(errorCode),
      'responseCipher': serializer.toJson<String?>(responseCipher),
      'responseNonce': serializer.toJson<String?>(responseNonce),
      'responseMac': serializer.toJson<String?>(responseMac),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  PendingCommand copyWith({
    String? tenantId,
    String? userId,
    String? clientCommandId,
    String? endpoint,
    String? payloadCipher,
    String? nonce,
    String? mac,
    String? dependenciesJson,
    String? status,
    int? attempts,
    Value<DateTime?> nextRetryAt = const Value.absent(),
    Value<String?> errorCode = const Value.absent(),
    Value<String?> responseCipher = const Value.absent(),
    Value<String?> responseNonce = const Value.absent(),
    Value<String?> responseMac = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => PendingCommand(
    tenantId: tenantId ?? this.tenantId,
    userId: userId ?? this.userId,
    clientCommandId: clientCommandId ?? this.clientCommandId,
    endpoint: endpoint ?? this.endpoint,
    payloadCipher: payloadCipher ?? this.payloadCipher,
    nonce: nonce ?? this.nonce,
    mac: mac ?? this.mac,
    dependenciesJson: dependenciesJson ?? this.dependenciesJson,
    status: status ?? this.status,
    attempts: attempts ?? this.attempts,
    nextRetryAt: nextRetryAt.present ? nextRetryAt.value : this.nextRetryAt,
    errorCode: errorCode.present ? errorCode.value : this.errorCode,
    responseCipher: responseCipher.present
        ? responseCipher.value
        : this.responseCipher,
    responseNonce: responseNonce.present
        ? responseNonce.value
        : this.responseNonce,
    responseMac: responseMac.present ? responseMac.value : this.responseMac,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  PendingCommand copyWithCompanion(PendingCommandsCompanion data) {
    return PendingCommand(
      tenantId: data.tenantId.present ? data.tenantId.value : this.tenantId,
      userId: data.userId.present ? data.userId.value : this.userId,
      clientCommandId: data.clientCommandId.present
          ? data.clientCommandId.value
          : this.clientCommandId,
      endpoint: data.endpoint.present ? data.endpoint.value : this.endpoint,
      payloadCipher: data.payloadCipher.present
          ? data.payloadCipher.value
          : this.payloadCipher,
      nonce: data.nonce.present ? data.nonce.value : this.nonce,
      mac: data.mac.present ? data.mac.value : this.mac,
      dependenciesJson: data.dependenciesJson.present
          ? data.dependenciesJson.value
          : this.dependenciesJson,
      status: data.status.present ? data.status.value : this.status,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      nextRetryAt: data.nextRetryAt.present
          ? data.nextRetryAt.value
          : this.nextRetryAt,
      errorCode: data.errorCode.present ? data.errorCode.value : this.errorCode,
      responseCipher: data.responseCipher.present
          ? data.responseCipher.value
          : this.responseCipher,
      responseNonce: data.responseNonce.present
          ? data.responseNonce.value
          : this.responseNonce,
      responseMac: data.responseMac.present
          ? data.responseMac.value
          : this.responseMac,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingCommand(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('clientCommandId: $clientCommandId, ')
          ..write('endpoint: $endpoint, ')
          ..write('payloadCipher: $payloadCipher, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('dependenciesJson: $dependenciesJson, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('errorCode: $errorCode, ')
          ..write('responseCipher: $responseCipher, ')
          ..write('responseNonce: $responseNonce, ')
          ..write('responseMac: $responseMac, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    tenantId,
    userId,
    clientCommandId,
    endpoint,
    payloadCipher,
    nonce,
    mac,
    dependenciesJson,
    status,
    attempts,
    nextRetryAt,
    errorCode,
    responseCipher,
    responseNonce,
    responseMac,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingCommand &&
          other.tenantId == this.tenantId &&
          other.userId == this.userId &&
          other.clientCommandId == this.clientCommandId &&
          other.endpoint == this.endpoint &&
          other.payloadCipher == this.payloadCipher &&
          other.nonce == this.nonce &&
          other.mac == this.mac &&
          other.dependenciesJson == this.dependenciesJson &&
          other.status == this.status &&
          other.attempts == this.attempts &&
          other.nextRetryAt == this.nextRetryAt &&
          other.errorCode == this.errorCode &&
          other.responseCipher == this.responseCipher &&
          other.responseNonce == this.responseNonce &&
          other.responseMac == this.responseMac &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class PendingCommandsCompanion extends UpdateCompanion<PendingCommand> {
  final Value<String> tenantId;
  final Value<String> userId;
  final Value<String> clientCommandId;
  final Value<String> endpoint;
  final Value<String> payloadCipher;
  final Value<String> nonce;
  final Value<String> mac;
  final Value<String> dependenciesJson;
  final Value<String> status;
  final Value<int> attempts;
  final Value<DateTime?> nextRetryAt;
  final Value<String?> errorCode;
  final Value<String?> responseCipher;
  final Value<String?> responseNonce;
  final Value<String?> responseMac;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const PendingCommandsCompanion({
    this.tenantId = const Value.absent(),
    this.userId = const Value.absent(),
    this.clientCommandId = const Value.absent(),
    this.endpoint = const Value.absent(),
    this.payloadCipher = const Value.absent(),
    this.nonce = const Value.absent(),
    this.mac = const Value.absent(),
    this.dependenciesJson = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.errorCode = const Value.absent(),
    this.responseCipher = const Value.absent(),
    this.responseNonce = const Value.absent(),
    this.responseMac = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PendingCommandsCompanion.insert({
    required String tenantId,
    required String userId,
    required String clientCommandId,
    required String endpoint,
    required String payloadCipher,
    required String nonce,
    required String mac,
    this.dependenciesJson = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.errorCode = const Value.absent(),
    this.responseCipher = const Value.absent(),
    this.responseNonce = const Value.absent(),
    this.responseMac = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : tenantId = Value(tenantId),
       userId = Value(userId),
       clientCommandId = Value(clientCommandId),
       endpoint = Value(endpoint),
       payloadCipher = Value(payloadCipher),
       nonce = Value(nonce),
       mac = Value(mac),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<PendingCommand> custom({
    Expression<String>? tenantId,
    Expression<String>? userId,
    Expression<String>? clientCommandId,
    Expression<String>? endpoint,
    Expression<String>? payloadCipher,
    Expression<String>? nonce,
    Expression<String>? mac,
    Expression<String>? dependenciesJson,
    Expression<String>? status,
    Expression<int>? attempts,
    Expression<DateTime>? nextRetryAt,
    Expression<String>? errorCode,
    Expression<String>? responseCipher,
    Expression<String>? responseNonce,
    Expression<String>? responseMac,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (tenantId != null) 'tenant_id': tenantId,
      if (userId != null) 'user_id': userId,
      if (clientCommandId != null) 'client_command_id': clientCommandId,
      if (endpoint != null) 'endpoint': endpoint,
      if (payloadCipher != null) 'payload_cipher': payloadCipher,
      if (nonce != null) 'nonce': nonce,
      if (mac != null) 'mac': mac,
      if (dependenciesJson != null) 'dependencies_json': dependenciesJson,
      if (status != null) 'status': status,
      if (attempts != null) 'attempts': attempts,
      if (nextRetryAt != null) 'next_retry_at': nextRetryAt,
      if (errorCode != null) 'error_code': errorCode,
      if (responseCipher != null) 'response_cipher': responseCipher,
      if (responseNonce != null) 'response_nonce': responseNonce,
      if (responseMac != null) 'response_mac': responseMac,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PendingCommandsCompanion copyWith({
    Value<String>? tenantId,
    Value<String>? userId,
    Value<String>? clientCommandId,
    Value<String>? endpoint,
    Value<String>? payloadCipher,
    Value<String>? nonce,
    Value<String>? mac,
    Value<String>? dependenciesJson,
    Value<String>? status,
    Value<int>? attempts,
    Value<DateTime?>? nextRetryAt,
    Value<String?>? errorCode,
    Value<String?>? responseCipher,
    Value<String?>? responseNonce,
    Value<String?>? responseMac,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return PendingCommandsCompanion(
      tenantId: tenantId ?? this.tenantId,
      userId: userId ?? this.userId,
      clientCommandId: clientCommandId ?? this.clientCommandId,
      endpoint: endpoint ?? this.endpoint,
      payloadCipher: payloadCipher ?? this.payloadCipher,
      nonce: nonce ?? this.nonce,
      mac: mac ?? this.mac,
      dependenciesJson: dependenciesJson ?? this.dependenciesJson,
      status: status ?? this.status,
      attempts: attempts ?? this.attempts,
      nextRetryAt: nextRetryAt ?? this.nextRetryAt,
      errorCode: errorCode ?? this.errorCode,
      responseCipher: responseCipher ?? this.responseCipher,
      responseNonce: responseNonce ?? this.responseNonce,
      responseMac: responseMac ?? this.responseMac,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (tenantId.present) {
      map['tenant_id'] = Variable<String>(tenantId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (clientCommandId.present) {
      map['client_command_id'] = Variable<String>(clientCommandId.value);
    }
    if (endpoint.present) {
      map['endpoint'] = Variable<String>(endpoint.value);
    }
    if (payloadCipher.present) {
      map['payload_cipher'] = Variable<String>(payloadCipher.value);
    }
    if (nonce.present) {
      map['nonce'] = Variable<String>(nonce.value);
    }
    if (mac.present) {
      map['mac'] = Variable<String>(mac.value);
    }
    if (dependenciesJson.present) {
      map['dependencies_json'] = Variable<String>(dependenciesJson.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (nextRetryAt.present) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt.value);
    }
    if (errorCode.present) {
      map['error_code'] = Variable<String>(errorCode.value);
    }
    if (responseCipher.present) {
      map['response_cipher'] = Variable<String>(responseCipher.value);
    }
    if (responseNonce.present) {
      map['response_nonce'] = Variable<String>(responseNonce.value);
    }
    if (responseMac.present) {
      map['response_mac'] = Variable<String>(responseMac.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingCommandsCompanion(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('clientCommandId: $clientCommandId, ')
          ..write('endpoint: $endpoint, ')
          ..write('payloadCipher: $payloadCipher, ')
          ..write('nonce: $nonce, ')
          ..write('mac: $mac, ')
          ..write('dependenciesJson: $dependenciesJson, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('errorCode: $errorCode, ')
          ..write('responseCipher: $responseCipher, ')
          ..write('responseNonce: $responseNonce, ')
          ..write('responseMac: $responseMac, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $PhotoUploadsTable extends PhotoUploads
    with TableInfo<$PhotoUploadsTable, PhotoUpload> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PhotoUploadsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _tenantIdMeta = const VerificationMeta(
    'tenantId',
  );
  @override
  late final GeneratedColumn<String> tenantId = GeneratedColumn<String>(
    'tenant_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
    'user_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _uploadIdMeta = const VerificationMeta(
    'uploadId',
  );
  @override
  late final GeneratedColumn<String> uploadId = GeneratedColumn<String>(
    'upload_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _draftIdMeta = const VerificationMeta(
    'draftId',
  );
  @override
  late final GeneratedColumn<String> draftId = GeneratedColumn<String>(
    'draft_id',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _localPathMeta = const VerificationMeta(
    'localPath',
  );
  @override
  late final GeneratedColumn<String> localPath = GeneratedColumn<String>(
    'local_path',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _mimeTypeMeta = const VerificationMeta(
    'mimeType',
  );
  @override
  late final GeneratedColumn<String> mimeType = GeneratedColumn<String>(
    'mime_type',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _checksumMeta = const VerificationMeta(
    'checksum',
  );
  @override
  late final GeneratedColumn<String> checksum = GeneratedColumn<String>(
    'checksum',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
    'status',
    aliasedName,
    false,
    type: DriftSqlType.string,
    requiredDuringInsert: false,
    defaultValue: const Constant('pending'),
  );
  static const VerificationMeta _attemptsMeta = const VerificationMeta(
    'attempts',
  );
  @override
  late final GeneratedColumn<int> attempts = GeneratedColumn<int>(
    'attempts',
    aliasedName,
    false,
    type: DriftSqlType.int,
    requiredDuringInsert: false,
    defaultValue: const Constant(0),
  );
  static const VerificationMeta _nextRetryAtMeta = const VerificationMeta(
    'nextRetryAt',
  );
  @override
  late final GeneratedColumn<DateTime> nextRetryAt = GeneratedColumn<DateTime>(
    'next_retry_at',
    aliasedName,
    true,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: false,
  );
  static const VerificationMeta _createdAtMeta = const VerificationMeta(
    'createdAt',
  );
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
    'created_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  static const VerificationMeta _updatedAtMeta = const VerificationMeta(
    'updatedAt',
  );
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
    'updated_at',
    aliasedName,
    false,
    type: DriftSqlType.dateTime,
    requiredDuringInsert: true,
  );
  @override
  List<GeneratedColumn> get $columns => [
    tenantId,
    userId,
    uploadId,
    draftId,
    localPath,
    mimeType,
    checksum,
    status,
    attempts,
    nextRetryAt,
    createdAt,
    updatedAt,
  ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'photo_uploads';
  @override
  VerificationContext validateIntegrity(
    Insertable<PhotoUpload> instance, {
    bool isInserting = false,
  }) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('tenant_id')) {
      context.handle(
        _tenantIdMeta,
        tenantId.isAcceptableOrUnknown(data['tenant_id']!, _tenantIdMeta),
      );
    } else if (isInserting) {
      context.missing(_tenantIdMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(
        _userIdMeta,
        userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta),
      );
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('upload_id')) {
      context.handle(
        _uploadIdMeta,
        uploadId.isAcceptableOrUnknown(data['upload_id']!, _uploadIdMeta),
      );
    } else if (isInserting) {
      context.missing(_uploadIdMeta);
    }
    if (data.containsKey('draft_id')) {
      context.handle(
        _draftIdMeta,
        draftId.isAcceptableOrUnknown(data['draft_id']!, _draftIdMeta),
      );
    } else if (isInserting) {
      context.missing(_draftIdMeta);
    }
    if (data.containsKey('local_path')) {
      context.handle(
        _localPathMeta,
        localPath.isAcceptableOrUnknown(data['local_path']!, _localPathMeta),
      );
    } else if (isInserting) {
      context.missing(_localPathMeta);
    }
    if (data.containsKey('mime_type')) {
      context.handle(
        _mimeTypeMeta,
        mimeType.isAcceptableOrUnknown(data['mime_type']!, _mimeTypeMeta),
      );
    } else if (isInserting) {
      context.missing(_mimeTypeMeta);
    }
    if (data.containsKey('checksum')) {
      context.handle(
        _checksumMeta,
        checksum.isAcceptableOrUnknown(data['checksum']!, _checksumMeta),
      );
    } else if (isInserting) {
      context.missing(_checksumMeta);
    }
    if (data.containsKey('status')) {
      context.handle(
        _statusMeta,
        status.isAcceptableOrUnknown(data['status']!, _statusMeta),
      );
    }
    if (data.containsKey('attempts')) {
      context.handle(
        _attemptsMeta,
        attempts.isAcceptableOrUnknown(data['attempts']!, _attemptsMeta),
      );
    }
    if (data.containsKey('next_retry_at')) {
      context.handle(
        _nextRetryAtMeta,
        nextRetryAt.isAcceptableOrUnknown(
          data['next_retry_at']!,
          _nextRetryAtMeta,
        ),
      );
    }
    if (data.containsKey('created_at')) {
      context.handle(
        _createdAtMeta,
        createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta),
      );
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(
        _updatedAtMeta,
        updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta),
      );
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {tenantId, userId, uploadId};
  @override
  PhotoUpload map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PhotoUpload(
      tenantId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}tenant_id'],
      )!,
      userId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}user_id'],
      )!,
      uploadId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}upload_id'],
      )!,
      draftId: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}draft_id'],
      )!,
      localPath: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}local_path'],
      )!,
      mimeType: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}mime_type'],
      )!,
      checksum: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}checksum'],
      )!,
      status: attachedDatabase.typeMapping.read(
        DriftSqlType.string,
        data['${effectivePrefix}status'],
      )!,
      attempts: attachedDatabase.typeMapping.read(
        DriftSqlType.int,
        data['${effectivePrefix}attempts'],
      )!,
      nextRetryAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}next_retry_at'],
      ),
      createdAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}created_at'],
      )!,
      updatedAt: attachedDatabase.typeMapping.read(
        DriftSqlType.dateTime,
        data['${effectivePrefix}updated_at'],
      )!,
    );
  }

  @override
  $PhotoUploadsTable createAlias(String alias) {
    return $PhotoUploadsTable(attachedDatabase, alias);
  }
}

class PhotoUpload extends DataClass implements Insertable<PhotoUpload> {
  final String tenantId;
  final String userId;
  final String uploadId;
  final String draftId;
  final String localPath;
  final String mimeType;
  final String checksum;
  final String status;
  final int attempts;
  final DateTime? nextRetryAt;
  final DateTime createdAt;
  final DateTime updatedAt;
  const PhotoUpload({
    required this.tenantId,
    required this.userId,
    required this.uploadId,
    required this.draftId,
    required this.localPath,
    required this.mimeType,
    required this.checksum,
    required this.status,
    required this.attempts,
    this.nextRetryAt,
    required this.createdAt,
    required this.updatedAt,
  });
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['tenant_id'] = Variable<String>(tenantId);
    map['user_id'] = Variable<String>(userId);
    map['upload_id'] = Variable<String>(uploadId);
    map['draft_id'] = Variable<String>(draftId);
    map['local_path'] = Variable<String>(localPath);
    map['mime_type'] = Variable<String>(mimeType);
    map['checksum'] = Variable<String>(checksum);
    map['status'] = Variable<String>(status);
    map['attempts'] = Variable<int>(attempts);
    if (!nullToAbsent || nextRetryAt != null) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt);
    }
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  PhotoUploadsCompanion toCompanion(bool nullToAbsent) {
    return PhotoUploadsCompanion(
      tenantId: Value(tenantId),
      userId: Value(userId),
      uploadId: Value(uploadId),
      draftId: Value(draftId),
      localPath: Value(localPath),
      mimeType: Value(mimeType),
      checksum: Value(checksum),
      status: Value(status),
      attempts: Value(attempts),
      nextRetryAt: nextRetryAt == null && nullToAbsent
          ? const Value.absent()
          : Value(nextRetryAt),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory PhotoUpload.fromJson(
    Map<String, dynamic> json, {
    ValueSerializer? serializer,
  }) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PhotoUpload(
      tenantId: serializer.fromJson<String>(json['tenantId']),
      userId: serializer.fromJson<String>(json['userId']),
      uploadId: serializer.fromJson<String>(json['uploadId']),
      draftId: serializer.fromJson<String>(json['draftId']),
      localPath: serializer.fromJson<String>(json['localPath']),
      mimeType: serializer.fromJson<String>(json['mimeType']),
      checksum: serializer.fromJson<String>(json['checksum']),
      status: serializer.fromJson<String>(json['status']),
      attempts: serializer.fromJson<int>(json['attempts']),
      nextRetryAt: serializer.fromJson<DateTime?>(json['nextRetryAt']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'tenantId': serializer.toJson<String>(tenantId),
      'userId': serializer.toJson<String>(userId),
      'uploadId': serializer.toJson<String>(uploadId),
      'draftId': serializer.toJson<String>(draftId),
      'localPath': serializer.toJson<String>(localPath),
      'mimeType': serializer.toJson<String>(mimeType),
      'checksum': serializer.toJson<String>(checksum),
      'status': serializer.toJson<String>(status),
      'attempts': serializer.toJson<int>(attempts),
      'nextRetryAt': serializer.toJson<DateTime?>(nextRetryAt),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  PhotoUpload copyWith({
    String? tenantId,
    String? userId,
    String? uploadId,
    String? draftId,
    String? localPath,
    String? mimeType,
    String? checksum,
    String? status,
    int? attempts,
    Value<DateTime?> nextRetryAt = const Value.absent(),
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => PhotoUpload(
    tenantId: tenantId ?? this.tenantId,
    userId: userId ?? this.userId,
    uploadId: uploadId ?? this.uploadId,
    draftId: draftId ?? this.draftId,
    localPath: localPath ?? this.localPath,
    mimeType: mimeType ?? this.mimeType,
    checksum: checksum ?? this.checksum,
    status: status ?? this.status,
    attempts: attempts ?? this.attempts,
    nextRetryAt: nextRetryAt.present ? nextRetryAt.value : this.nextRetryAt,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
  PhotoUpload copyWithCompanion(PhotoUploadsCompanion data) {
    return PhotoUpload(
      tenantId: data.tenantId.present ? data.tenantId.value : this.tenantId,
      userId: data.userId.present ? data.userId.value : this.userId,
      uploadId: data.uploadId.present ? data.uploadId.value : this.uploadId,
      draftId: data.draftId.present ? data.draftId.value : this.draftId,
      localPath: data.localPath.present ? data.localPath.value : this.localPath,
      mimeType: data.mimeType.present ? data.mimeType.value : this.mimeType,
      checksum: data.checksum.present ? data.checksum.value : this.checksum,
      status: data.status.present ? data.status.value : this.status,
      attempts: data.attempts.present ? data.attempts.value : this.attempts,
      nextRetryAt: data.nextRetryAt.present
          ? data.nextRetryAt.value
          : this.nextRetryAt,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PhotoUpload(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('uploadId: $uploadId, ')
          ..write('draftId: $draftId, ')
          ..write('localPath: $localPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('checksum: $checksum, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
    tenantId,
    userId,
    uploadId,
    draftId,
    localPath,
    mimeType,
    checksum,
    status,
    attempts,
    nextRetryAt,
    createdAt,
    updatedAt,
  );
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PhotoUpload &&
          other.tenantId == this.tenantId &&
          other.userId == this.userId &&
          other.uploadId == this.uploadId &&
          other.draftId == this.draftId &&
          other.localPath == this.localPath &&
          other.mimeType == this.mimeType &&
          other.checksum == this.checksum &&
          other.status == this.status &&
          other.attempts == this.attempts &&
          other.nextRetryAt == this.nextRetryAt &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class PhotoUploadsCompanion extends UpdateCompanion<PhotoUpload> {
  final Value<String> tenantId;
  final Value<String> userId;
  final Value<String> uploadId;
  final Value<String> draftId;
  final Value<String> localPath;
  final Value<String> mimeType;
  final Value<String> checksum;
  final Value<String> status;
  final Value<int> attempts;
  final Value<DateTime?> nextRetryAt;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const PhotoUploadsCompanion({
    this.tenantId = const Value.absent(),
    this.userId = const Value.absent(),
    this.uploadId = const Value.absent(),
    this.draftId = const Value.absent(),
    this.localPath = const Value.absent(),
    this.mimeType = const Value.absent(),
    this.checksum = const Value.absent(),
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PhotoUploadsCompanion.insert({
    required String tenantId,
    required String userId,
    required String uploadId,
    required String draftId,
    required String localPath,
    required String mimeType,
    required String checksum,
    this.status = const Value.absent(),
    this.attempts = const Value.absent(),
    this.nextRetryAt = const Value.absent(),
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  }) : tenantId = Value(tenantId),
       userId = Value(userId),
       uploadId = Value(uploadId),
       draftId = Value(draftId),
       localPath = Value(localPath),
       mimeType = Value(mimeType),
       checksum = Value(checksum),
       createdAt = Value(createdAt),
       updatedAt = Value(updatedAt);
  static Insertable<PhotoUpload> custom({
    Expression<String>? tenantId,
    Expression<String>? userId,
    Expression<String>? uploadId,
    Expression<String>? draftId,
    Expression<String>? localPath,
    Expression<String>? mimeType,
    Expression<String>? checksum,
    Expression<String>? status,
    Expression<int>? attempts,
    Expression<DateTime>? nextRetryAt,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (tenantId != null) 'tenant_id': tenantId,
      if (userId != null) 'user_id': userId,
      if (uploadId != null) 'upload_id': uploadId,
      if (draftId != null) 'draft_id': draftId,
      if (localPath != null) 'local_path': localPath,
      if (mimeType != null) 'mime_type': mimeType,
      if (checksum != null) 'checksum': checksum,
      if (status != null) 'status': status,
      if (attempts != null) 'attempts': attempts,
      if (nextRetryAt != null) 'next_retry_at': nextRetryAt,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PhotoUploadsCompanion copyWith({
    Value<String>? tenantId,
    Value<String>? userId,
    Value<String>? uploadId,
    Value<String>? draftId,
    Value<String>? localPath,
    Value<String>? mimeType,
    Value<String>? checksum,
    Value<String>? status,
    Value<int>? attempts,
    Value<DateTime?>? nextRetryAt,
    Value<DateTime>? createdAt,
    Value<DateTime>? updatedAt,
    Value<int>? rowid,
  }) {
    return PhotoUploadsCompanion(
      tenantId: tenantId ?? this.tenantId,
      userId: userId ?? this.userId,
      uploadId: uploadId ?? this.uploadId,
      draftId: draftId ?? this.draftId,
      localPath: localPath ?? this.localPath,
      mimeType: mimeType ?? this.mimeType,
      checksum: checksum ?? this.checksum,
      status: status ?? this.status,
      attempts: attempts ?? this.attempts,
      nextRetryAt: nextRetryAt ?? this.nextRetryAt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (tenantId.present) {
      map['tenant_id'] = Variable<String>(tenantId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (uploadId.present) {
      map['upload_id'] = Variable<String>(uploadId.value);
    }
    if (draftId.present) {
      map['draft_id'] = Variable<String>(draftId.value);
    }
    if (localPath.present) {
      map['local_path'] = Variable<String>(localPath.value);
    }
    if (mimeType.present) {
      map['mime_type'] = Variable<String>(mimeType.value);
    }
    if (checksum.present) {
      map['checksum'] = Variable<String>(checksum.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (attempts.present) {
      map['attempts'] = Variable<int>(attempts.value);
    }
    if (nextRetryAt.present) {
      map['next_retry_at'] = Variable<DateTime>(nextRetryAt.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PhotoUploadsCompanion(')
          ..write('tenantId: $tenantId, ')
          ..write('userId: $userId, ')
          ..write('uploadId: $uploadId, ')
          ..write('draftId: $draftId, ')
          ..write('localPath: $localPath, ')
          ..write('mimeType: $mimeType, ')
          ..write('checksum: $checksum, ')
          ..write('status: $status, ')
          ..write('attempts: $attempts, ')
          ..write('nextRetryAt: $nextRetryAt, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $CachedRecordsTable cachedRecords = $CachedRecordsTable(this);
  late final $DraftOrdersTable draftOrders = $DraftOrdersTable(this);
  late final $PendingCommandsTable pendingCommands = $PendingCommandsTable(
    this,
  );
  late final $PhotoUploadsTable photoUploads = $PhotoUploadsTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
    cachedRecords,
    draftOrders,
    pendingCommands,
    photoUploads,
  ];
}

typedef $$CachedRecordsTableCreateCompanionBuilder =
    CachedRecordsCompanion Function({
      required String tenantId,
      required String userId,
      required String kind,
      required String remoteId,
      required String cipherText,
      required String nonce,
      required String mac,
      required DateTime cachedAt,
      Value<DateTime?> lastAccessedAt,
      Value<int> rowid,
    });
typedef $$CachedRecordsTableUpdateCompanionBuilder =
    CachedRecordsCompanion Function({
      Value<String> tenantId,
      Value<String> userId,
      Value<String> kind,
      Value<String> remoteId,
      Value<String> cipherText,
      Value<String> nonce,
      Value<String> mac,
      Value<DateTime> cachedAt,
      Value<DateTime?> lastAccessedAt,
      Value<int> rowid,
    });

class $$CachedRecordsTableFilterComposer
    extends Composer<_$AppDatabase, $CachedRecordsTable> {
  $$CachedRecordsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get remoteId => $composableBuilder(
    column: $table.remoteId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get cipherText => $composableBuilder(
    column: $table.cipherText,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get cachedAt => $composableBuilder(
    column: $table.cachedAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get lastAccessedAt => $composableBuilder(
    column: $table.lastAccessedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$CachedRecordsTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedRecordsTable> {
  $$CachedRecordsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get kind => $composableBuilder(
    column: $table.kind,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get remoteId => $composableBuilder(
    column: $table.remoteId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get cipherText => $composableBuilder(
    column: $table.cipherText,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get cachedAt => $composableBuilder(
    column: $table.cachedAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get lastAccessedAt => $composableBuilder(
    column: $table.lastAccessedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$CachedRecordsTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedRecordsTable> {
  $$CachedRecordsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get tenantId =>
      $composableBuilder(column: $table.tenantId, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get kind =>
      $composableBuilder(column: $table.kind, builder: (column) => column);

  GeneratedColumn<String> get remoteId =>
      $composableBuilder(column: $table.remoteId, builder: (column) => column);

  GeneratedColumn<String> get cipherText => $composableBuilder(
    column: $table.cipherText,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nonce =>
      $composableBuilder(column: $table.nonce, builder: (column) => column);

  GeneratedColumn<String> get mac =>
      $composableBuilder(column: $table.mac, builder: (column) => column);

  GeneratedColumn<DateTime> get cachedAt =>
      $composableBuilder(column: $table.cachedAt, builder: (column) => column);

  GeneratedColumn<DateTime> get lastAccessedAt => $composableBuilder(
    column: $table.lastAccessedAt,
    builder: (column) => column,
  );
}

class $$CachedRecordsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $CachedRecordsTable,
          CachedRecord,
          $$CachedRecordsTableFilterComposer,
          $$CachedRecordsTableOrderingComposer,
          $$CachedRecordsTableAnnotationComposer,
          $$CachedRecordsTableCreateCompanionBuilder,
          $$CachedRecordsTableUpdateCompanionBuilder,
          (
            CachedRecord,
            BaseReferences<_$AppDatabase, $CachedRecordsTable, CachedRecord>,
          ),
          CachedRecord,
          PrefetchHooks Function()
        > {
  $$CachedRecordsTableTableManager(_$AppDatabase db, $CachedRecordsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedRecordsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedRecordsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedRecordsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> tenantId = const Value.absent(),
                Value<String> userId = const Value.absent(),
                Value<String> kind = const Value.absent(),
                Value<String> remoteId = const Value.absent(),
                Value<String> cipherText = const Value.absent(),
                Value<String> nonce = const Value.absent(),
                Value<String> mac = const Value.absent(),
                Value<DateTime> cachedAt = const Value.absent(),
                Value<DateTime?> lastAccessedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedRecordsCompanion(
                tenantId: tenantId,
                userId: userId,
                kind: kind,
                remoteId: remoteId,
                cipherText: cipherText,
                nonce: nonce,
                mac: mac,
                cachedAt: cachedAt,
                lastAccessedAt: lastAccessedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String tenantId,
                required String userId,
                required String kind,
                required String remoteId,
                required String cipherText,
                required String nonce,
                required String mac,
                required DateTime cachedAt,
                Value<DateTime?> lastAccessedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => CachedRecordsCompanion.insert(
                tenantId: tenantId,
                userId: userId,
                kind: kind,
                remoteId: remoteId,
                cipherText: cipherText,
                nonce: nonce,
                mac: mac,
                cachedAt: cachedAt,
                lastAccessedAt: lastAccessedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$CachedRecordsTable, CachedRecord>(table),
                  BaseReferences<
                    _$AppDatabase,
                    $CachedRecordsTable,
                    CachedRecord
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$CachedRecordsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $CachedRecordsTable,
      CachedRecord,
      $$CachedRecordsTableFilterComposer,
      $$CachedRecordsTableOrderingComposer,
      $$CachedRecordsTableAnnotationComposer,
      $$CachedRecordsTableCreateCompanionBuilder,
      $$CachedRecordsTableUpdateCompanionBuilder,
      (
        CachedRecord,
        BaseReferences<_$AppDatabase, $CachedRecordsTable, CachedRecord>,
      ),
      CachedRecord,
      PrefetchHooks Function()
    >;
typedef $$DraftOrdersTableCreateCompanionBuilder =
    DraftOrdersCompanion Function({
      required String tenantId,
      required String userId,
      required String draftId,
      required String payloadCipher,
      required String nonce,
      required String mac,
      Value<int> version,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$DraftOrdersTableUpdateCompanionBuilder =
    DraftOrdersCompanion Function({
      Value<String> tenantId,
      Value<String> userId,
      Value<String> draftId,
      Value<String> payloadCipher,
      Value<String> nonce,
      Value<String> mac,
      Value<int> version,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$DraftOrdersTableFilterComposer
    extends Composer<_$AppDatabase, $DraftOrdersTable> {
  $$DraftOrdersTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get draftId => $composableBuilder(
    column: $table.draftId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get version => $composableBuilder(
    column: $table.version,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$DraftOrdersTableOrderingComposer
    extends Composer<_$AppDatabase, $DraftOrdersTable> {
  $$DraftOrdersTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get draftId => $composableBuilder(
    column: $table.draftId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get version => $composableBuilder(
    column: $table.version,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$DraftOrdersTableAnnotationComposer
    extends Composer<_$AppDatabase, $DraftOrdersTable> {
  $$DraftOrdersTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get tenantId =>
      $composableBuilder(column: $table.tenantId, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get draftId =>
      $composableBuilder(column: $table.draftId, builder: (column) => column);

  GeneratedColumn<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nonce =>
      $composableBuilder(column: $table.nonce, builder: (column) => column);

  GeneratedColumn<String> get mac =>
      $composableBuilder(column: $table.mac, builder: (column) => column);

  GeneratedColumn<int> get version =>
      $composableBuilder(column: $table.version, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$DraftOrdersTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $DraftOrdersTable,
          DraftOrder,
          $$DraftOrdersTableFilterComposer,
          $$DraftOrdersTableOrderingComposer,
          $$DraftOrdersTableAnnotationComposer,
          $$DraftOrdersTableCreateCompanionBuilder,
          $$DraftOrdersTableUpdateCompanionBuilder,
          (
            DraftOrder,
            BaseReferences<_$AppDatabase, $DraftOrdersTable, DraftOrder>,
          ),
          DraftOrder,
          PrefetchHooks Function()
        > {
  $$DraftOrdersTableTableManager(_$AppDatabase db, $DraftOrdersTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$DraftOrdersTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$DraftOrdersTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$DraftOrdersTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> tenantId = const Value.absent(),
                Value<String> userId = const Value.absent(),
                Value<String> draftId = const Value.absent(),
                Value<String> payloadCipher = const Value.absent(),
                Value<String> nonce = const Value.absent(),
                Value<String> mac = const Value.absent(),
                Value<int> version = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => DraftOrdersCompanion(
                tenantId: tenantId,
                userId: userId,
                draftId: draftId,
                payloadCipher: payloadCipher,
                nonce: nonce,
                mac: mac,
                version: version,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String tenantId,
                required String userId,
                required String draftId,
                required String payloadCipher,
                required String nonce,
                required String mac,
                Value<int> version = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => DraftOrdersCompanion.insert(
                tenantId: tenantId,
                userId: userId,
                draftId: draftId,
                payloadCipher: payloadCipher,
                nonce: nonce,
                mac: mac,
                version: version,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$DraftOrdersTable, DraftOrder>(table),
                  BaseReferences<_$AppDatabase, $DraftOrdersTable, DraftOrder>(
                    db,
                    table,
                    e,
                  ),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$DraftOrdersTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $DraftOrdersTable,
      DraftOrder,
      $$DraftOrdersTableFilterComposer,
      $$DraftOrdersTableOrderingComposer,
      $$DraftOrdersTableAnnotationComposer,
      $$DraftOrdersTableCreateCompanionBuilder,
      $$DraftOrdersTableUpdateCompanionBuilder,
      (
        DraftOrder,
        BaseReferences<_$AppDatabase, $DraftOrdersTable, DraftOrder>,
      ),
      DraftOrder,
      PrefetchHooks Function()
    >;
typedef $$PendingCommandsTableCreateCompanionBuilder =
    PendingCommandsCompanion Function({
      required String tenantId,
      required String userId,
      required String clientCommandId,
      required String endpoint,
      required String payloadCipher,
      required String nonce,
      required String mac,
      Value<String> dependenciesJson,
      Value<String> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      Value<String?> errorCode,
      Value<String?> responseCipher,
      Value<String?> responseNonce,
      Value<String?> responseMac,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$PendingCommandsTableUpdateCompanionBuilder =
    PendingCommandsCompanion Function({
      Value<String> tenantId,
      Value<String> userId,
      Value<String> clientCommandId,
      Value<String> endpoint,
      Value<String> payloadCipher,
      Value<String> nonce,
      Value<String> mac,
      Value<String> dependenciesJson,
      Value<String> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      Value<String?> errorCode,
      Value<String?> responseCipher,
      Value<String?> responseNonce,
      Value<String?> responseMac,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$PendingCommandsTableFilterComposer
    extends Composer<_$AppDatabase, $PendingCommandsTable> {
  $$PendingCommandsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get clientCommandId => $composableBuilder(
    column: $table.clientCommandId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get dependenciesJson => $composableBuilder(
    column: $table.dependenciesJson,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get errorCode => $composableBuilder(
    column: $table.errorCode,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get responseCipher => $composableBuilder(
    column: $table.responseCipher,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get responseNonce => $composableBuilder(
    column: $table.responseNonce,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get responseMac => $composableBuilder(
    column: $table.responseMac,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PendingCommandsTableOrderingComposer
    extends Composer<_$AppDatabase, $PendingCommandsTable> {
  $$PendingCommandsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get clientCommandId => $composableBuilder(
    column: $table.clientCommandId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get endpoint => $composableBuilder(
    column: $table.endpoint,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get nonce => $composableBuilder(
    column: $table.nonce,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mac => $composableBuilder(
    column: $table.mac,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get dependenciesJson => $composableBuilder(
    column: $table.dependenciesJson,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get errorCode => $composableBuilder(
    column: $table.errorCode,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get responseCipher => $composableBuilder(
    column: $table.responseCipher,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get responseNonce => $composableBuilder(
    column: $table.responseNonce,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get responseMac => $composableBuilder(
    column: $table.responseMac,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PendingCommandsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PendingCommandsTable> {
  $$PendingCommandsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get tenantId =>
      $composableBuilder(column: $table.tenantId, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get clientCommandId => $composableBuilder(
    column: $table.clientCommandId,
    builder: (column) => column,
  );

  GeneratedColumn<String> get endpoint =>
      $composableBuilder(column: $table.endpoint, builder: (column) => column);

  GeneratedColumn<String> get payloadCipher => $composableBuilder(
    column: $table.payloadCipher,
    builder: (column) => column,
  );

  GeneratedColumn<String> get nonce =>
      $composableBuilder(column: $table.nonce, builder: (column) => column);

  GeneratedColumn<String> get mac =>
      $composableBuilder(column: $table.mac, builder: (column) => column);

  GeneratedColumn<String> get dependenciesJson => $composableBuilder(
    column: $table.dependenciesJson,
    builder: (column) => column,
  );

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => column,
  );

  GeneratedColumn<String> get errorCode =>
      $composableBuilder(column: $table.errorCode, builder: (column) => column);

  GeneratedColumn<String> get responseCipher => $composableBuilder(
    column: $table.responseCipher,
    builder: (column) => column,
  );

  GeneratedColumn<String> get responseNonce => $composableBuilder(
    column: $table.responseNonce,
    builder: (column) => column,
  );

  GeneratedColumn<String> get responseMac => $composableBuilder(
    column: $table.responseMac,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$PendingCommandsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PendingCommandsTable,
          PendingCommand,
          $$PendingCommandsTableFilterComposer,
          $$PendingCommandsTableOrderingComposer,
          $$PendingCommandsTableAnnotationComposer,
          $$PendingCommandsTableCreateCompanionBuilder,
          $$PendingCommandsTableUpdateCompanionBuilder,
          (
            PendingCommand,
            BaseReferences<
              _$AppDatabase,
              $PendingCommandsTable,
              PendingCommand
            >,
          ),
          PendingCommand,
          PrefetchHooks Function()
        > {
  $$PendingCommandsTableTableManager(
    _$AppDatabase db,
    $PendingCommandsTable table,
  ) : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingCommandsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingCommandsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PendingCommandsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> tenantId = const Value.absent(),
                Value<String> userId = const Value.absent(),
                Value<String> clientCommandId = const Value.absent(),
                Value<String> endpoint = const Value.absent(),
                Value<String> payloadCipher = const Value.absent(),
                Value<String> nonce = const Value.absent(),
                Value<String> mac = const Value.absent(),
                Value<String> dependenciesJson = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                Value<String?> errorCode = const Value.absent(),
                Value<String?> responseCipher = const Value.absent(),
                Value<String?> responseNonce = const Value.absent(),
                Value<String?> responseMac = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PendingCommandsCompanion(
                tenantId: tenantId,
                userId: userId,
                clientCommandId: clientCommandId,
                endpoint: endpoint,
                payloadCipher: payloadCipher,
                nonce: nonce,
                mac: mac,
                dependenciesJson: dependenciesJson,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                errorCode: errorCode,
                responseCipher: responseCipher,
                responseNonce: responseNonce,
                responseMac: responseMac,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String tenantId,
                required String userId,
                required String clientCommandId,
                required String endpoint,
                required String payloadCipher,
                required String nonce,
                required String mac,
                Value<String> dependenciesJson = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                Value<String?> errorCode = const Value.absent(),
                Value<String?> responseCipher = const Value.absent(),
                Value<String?> responseNonce = const Value.absent(),
                Value<String?> responseMac = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => PendingCommandsCompanion.insert(
                tenantId: tenantId,
                userId: userId,
                clientCommandId: clientCommandId,
                endpoint: endpoint,
                payloadCipher: payloadCipher,
                nonce: nonce,
                mac: mac,
                dependenciesJson: dependenciesJson,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                errorCode: errorCode,
                responseCipher: responseCipher,
                responseNonce: responseNonce,
                responseMac: responseMac,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$PendingCommandsTable, PendingCommand>(table),
                  BaseReferences<
                    _$AppDatabase,
                    $PendingCommandsTable,
                    PendingCommand
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PendingCommandsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PendingCommandsTable,
      PendingCommand,
      $$PendingCommandsTableFilterComposer,
      $$PendingCommandsTableOrderingComposer,
      $$PendingCommandsTableAnnotationComposer,
      $$PendingCommandsTableCreateCompanionBuilder,
      $$PendingCommandsTableUpdateCompanionBuilder,
      (
        PendingCommand,
        BaseReferences<_$AppDatabase, $PendingCommandsTable, PendingCommand>,
      ),
      PendingCommand,
      PrefetchHooks Function()
    >;
typedef $$PhotoUploadsTableCreateCompanionBuilder =
    PhotoUploadsCompanion Function({
      required String tenantId,
      required String userId,
      required String uploadId,
      required String draftId,
      required String localPath,
      required String mimeType,
      required String checksum,
      Value<String> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      required DateTime createdAt,
      required DateTime updatedAt,
      Value<int> rowid,
    });
typedef $$PhotoUploadsTableUpdateCompanionBuilder =
    PhotoUploadsCompanion Function({
      Value<String> tenantId,
      Value<String> userId,
      Value<String> uploadId,
      Value<String> draftId,
      Value<String> localPath,
      Value<String> mimeType,
      Value<String> checksum,
      Value<String> status,
      Value<int> attempts,
      Value<DateTime?> nextRetryAt,
      Value<DateTime> createdAt,
      Value<DateTime> updatedAt,
      Value<int> rowid,
    });

class $$PhotoUploadsTableFilterComposer
    extends Composer<_$AppDatabase, $PhotoUploadsTable> {
  $$PhotoUploadsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get uploadId => $composableBuilder(
    column: $table.uploadId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get draftId => $composableBuilder(
    column: $table.draftId,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get checksum => $composableBuilder(
    column: $table.checksum,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnFilters(column),
  );

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnFilters(column),
  );
}

class $$PhotoUploadsTableOrderingComposer
    extends Composer<_$AppDatabase, $PhotoUploadsTable> {
  $$PhotoUploadsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get tenantId => $composableBuilder(
    column: $table.tenantId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get userId => $composableBuilder(
    column: $table.userId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get uploadId => $composableBuilder(
    column: $table.uploadId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get draftId => $composableBuilder(
    column: $table.draftId,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get localPath => $composableBuilder(
    column: $table.localPath,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get mimeType => $composableBuilder(
    column: $table.mimeType,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get checksum => $composableBuilder(
    column: $table.checksum,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<String> get status => $composableBuilder(
    column: $table.status,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<int> get attempts => $composableBuilder(
    column: $table.attempts,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
    column: $table.createdAt,
    builder: (column) => ColumnOrderings(column),
  );

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
    column: $table.updatedAt,
    builder: (column) => ColumnOrderings(column),
  );
}

class $$PhotoUploadsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PhotoUploadsTable> {
  $$PhotoUploadsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get tenantId =>
      $composableBuilder(column: $table.tenantId, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get uploadId =>
      $composableBuilder(column: $table.uploadId, builder: (column) => column);

  GeneratedColumn<String> get draftId =>
      $composableBuilder(column: $table.draftId, builder: (column) => column);

  GeneratedColumn<String> get localPath =>
      $composableBuilder(column: $table.localPath, builder: (column) => column);

  GeneratedColumn<String> get mimeType =>
      $composableBuilder(column: $table.mimeType, builder: (column) => column);

  GeneratedColumn<String> get checksum =>
      $composableBuilder(column: $table.checksum, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<int> get attempts =>
      $composableBuilder(column: $table.attempts, builder: (column) => column);

  GeneratedColumn<DateTime> get nextRetryAt => $composableBuilder(
    column: $table.nextRetryAt,
    builder: (column) => column,
  );

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$PhotoUploadsTableTableManager
    extends
        RootTableManager<
          _$AppDatabase,
          $PhotoUploadsTable,
          PhotoUpload,
          $$PhotoUploadsTableFilterComposer,
          $$PhotoUploadsTableOrderingComposer,
          $$PhotoUploadsTableAnnotationComposer,
          $$PhotoUploadsTableCreateCompanionBuilder,
          $$PhotoUploadsTableUpdateCompanionBuilder,
          (
            PhotoUpload,
            BaseReferences<_$AppDatabase, $PhotoUploadsTable, PhotoUpload>,
          ),
          PhotoUpload,
          PrefetchHooks Function()
        > {
  $$PhotoUploadsTableTableManager(_$AppDatabase db, $PhotoUploadsTable table)
    : super(
        TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PhotoUploadsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PhotoUploadsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PhotoUploadsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback:
              ({
                Value<String> tenantId = const Value.absent(),
                Value<String> userId = const Value.absent(),
                Value<String> uploadId = const Value.absent(),
                Value<String> draftId = const Value.absent(),
                Value<String> localPath = const Value.absent(),
                Value<String> mimeType = const Value.absent(),
                Value<String> checksum = const Value.absent(),
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                Value<DateTime> createdAt = const Value.absent(),
                Value<DateTime> updatedAt = const Value.absent(),
                Value<int> rowid = const Value.absent(),
              }) => PhotoUploadsCompanion(
                tenantId: tenantId,
                userId: userId,
                uploadId: uploadId,
                draftId: draftId,
                localPath: localPath,
                mimeType: mimeType,
                checksum: checksum,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          createCompanionCallback:
              ({
                required String tenantId,
                required String userId,
                required String uploadId,
                required String draftId,
                required String localPath,
                required String mimeType,
                required String checksum,
                Value<String> status = const Value.absent(),
                Value<int> attempts = const Value.absent(),
                Value<DateTime?> nextRetryAt = const Value.absent(),
                required DateTime createdAt,
                required DateTime updatedAt,
                Value<int> rowid = const Value.absent(),
              }) => PhotoUploadsCompanion.insert(
                tenantId: tenantId,
                userId: userId,
                uploadId: uploadId,
                draftId: draftId,
                localPath: localPath,
                mimeType: mimeType,
                checksum: checksum,
                status: status,
                attempts: attempts,
                nextRetryAt: nextRetryAt,
                createdAt: createdAt,
                updatedAt: updatedAt,
                rowid: rowid,
              ),
          withReferenceMapper: (p0) => p0
              .map(
                (e) => (
                  e.readTable<$PhotoUploadsTable, PhotoUpload>(table),
                  BaseReferences<
                    _$AppDatabase,
                    $PhotoUploadsTable,
                    PhotoUpload
                  >(db, table, e),
                ),
              )
              .toList(),
          prefetchHooksCallback: null,
        ),
      );
}

typedef $$PhotoUploadsTableProcessedTableManager =
    ProcessedTableManager<
      _$AppDatabase,
      $PhotoUploadsTable,
      PhotoUpload,
      $$PhotoUploadsTableFilterComposer,
      $$PhotoUploadsTableOrderingComposer,
      $$PhotoUploadsTableAnnotationComposer,
      $$PhotoUploadsTableCreateCompanionBuilder,
      $$PhotoUploadsTableUpdateCompanionBuilder,
      (
        PhotoUpload,
        BaseReferences<_$AppDatabase, $PhotoUploadsTable, PhotoUpload>,
      ),
      PhotoUpload,
      PrefetchHooks Function()
    >;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$CachedRecordsTableTableManager get cachedRecords =>
      $$CachedRecordsTableTableManager(_db, _db.cachedRecords);
  $$DraftOrdersTableTableManager get draftOrders =>
      $$DraftOrdersTableTableManager(_db, _db.draftOrders);
  $$PendingCommandsTableTableManager get pendingCommands =>
      $$PendingCommandsTableTableManager(_db, _db.pendingCommands);
  $$PhotoUploadsTableTableManager get photoUploads =>
      $$PhotoUploadsTableTableManager(_db, _db.photoUploads);
}
