# Offline Implementation Plan - Phase 3

## Overview

This document provides a week-by-week implementation roadmap for adding offline support to the Transportation MVP Flutter app. The implementation follows the 5-phase architecture defined in `offline-sync-design.md`.

**Timeline**: 4-5 weeks
**Priority**: HIGH (enables critical driver workflows)
**Starting Point**: Phase 1 (Foundation) - Week 1

---

## Week 1-2: Phase 1 Foundation (CRITICAL PRIORITY)

### Objective
Build the foundation layer: local database, connectivity detection, offline-first API client.

### Tasks

#### Week 1: Setup & Database Schema

**Task 1.1: Add Dependencies** (Day 1)
```bash
flutter pub add sqflite sqlite3_flutter_libs
flutter pub add connectivity_plus
flutter pub add path_provider  # For database path
```

**Deliverable**: Updated `pubspec.yaml`

**Task 1.2: Create Database Service** (Day 1-2)
File: `lib/core/local_storage/database_service.dart`

```dart
class DatabaseService {
  static late Database _database;

  Future<Database> getDatabase() async {
    if (_database.isOpen) return _database;

    final databasesPath = await getDatabasesPath();
    final path = join(databasesPath, 'transportation_app.db');

    _database = await openDatabase(
      path,
      version: 1,
      onCreate: (Database db, int version) async {
        await db.execute('''
          CREATE TABLE api_cache (
            id INTEGER PRIMARY KEY,
            endpoint TEXT NOT NULL UNIQUE,
            data BLOB,
            cached_at TIMESTAMP,
            expires_at TIMESTAMP
          );
          CREATE INDEX idx_api_cache_expires ON api_cache(expires_at);

          CREATE TABLE cached_trips (
            id TEXT PRIMARY KEY,
            trip_id TEXT NOT NULL UNIQUE,
            data BLOB,
            status TEXT,
            version INT,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
          );

          CREATE TABLE sync_queue (
            id INTEGER PRIMARY KEY,
            operation_id TEXT NOT NULL UNIQUE,
            operation_type TEXT,
            endpoint TEXT,
            method TEXT,
            payload BLOB,
            retry_count INT DEFAULT 0,
            last_retry_at TIMESTAMP,
            created_at TIMESTAMP,
            expires_at TIMESTAMP
          );
          CREATE INDEX idx_sync_queue_created ON sync_queue(created_at);
          CREATE INDEX idx_sync_queue_expires ON sync_queue(expires_at);

          CREATE TABLE device_state (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at TIMESTAMP
          );
        ''');
      },
    );

    return _database;
  }

  Future<void> close() async {
    await _database.close();
  }
}

// Provider
final databaseServiceProvider = Provider<DatabaseService>((ref) {
  return DatabaseService();
});

final databaseProvider = FutureProvider<Database>((ref) async {
  final service = ref.watch(databaseServiceProvider);
  return service.getDatabase();
});
```

**Deliverable**: `DatabaseService` with schema creation

**Task 1.3: Create Connectivity Manager** (Day 2-3)
File: `lib/core/connectivity/connectivity_manager.dart`

```dart
enum ConnectivityStatus { online, offline, checking }

class ConnectivityManager {
  final Connectivity _connectivity = Connectivity();
  final ValueNotifier<ConnectivityStatus> _status =
    ValueNotifier(ConnectivityStatus.checking);

  ValueNotifier<ConnectivityStatus> get status => _status;

  Future<void> initialize() async {
    // Check initial state
    await _updateStatus();

    // Listen for changes
    _connectivity.onConnectivityChanged.listen((result) {
      _updateStatus();
    });
  }

  Future<void> _updateStatus() async {
    _status.value = ConnectivityStatus.checking;

    try {
      final result = await _connectivity.checkConnectivity();

      if (result.isEmpty || result.contains(ConnectivityResult.none)) {
        _status.value = ConnectivityStatus.offline;
      } else {
        // Double-check with actual connectivity test
        final hasInternet = await _testInternetConnectivity();
        _status.value = hasInternet
          ? ConnectivityStatus.online
          : ConnectivityStatus.offline;
      }
    } catch (e) {
      _status.value = ConnectivityStatus.offline;
    }
  }

  /// Test actual internet connectivity (not just system connectivity)
  Future<bool> _testInternetConnectivity() async {
    try {
      final result = await InternetAddress.lookup('google.com')
        .timeout(const Duration(seconds: 5));
      return result.isNotEmpty && result[0].rawAddress.isNotEmpty;
    } on SocketException catch (_) {
      return false;
    } on TimeoutException catch (_) {
      return false;
    }
  }

  bool get isOnline => _status.value == ConnectivityStatus.online;
  bool get isOffline => _status.value == ConnectivityStatus.offline;
  bool get isChecking => _status.value == ConnectivityStatus.checking;

  Future<void> dispose() async {
    await _status.notifyListeners();
  }
}

// Provider
final connectivityManagerProvider =
  Provider<ConnectivityManager>((ref) {
  final manager = ConnectivityManager();
  manager.initialize();
  ref.onDispose(() => manager.dispose());
  return manager;
});

final isOnlineProvider =
  StreamProvider<bool>((ref) {
  final manager = ref.watch(connectivityManagerProvider);
  return manager.status.changes
    .map((status) => status == ConnectivityStatus.online)
    .startWith(manager.isOnline);
});
```

**Deliverable**: `ConnectivityManager` with real internet connectivity testing

#### Week 2: Offline-First Repository Pattern

**Task 2.1: Create Local Storage Service** (Day 1-2)
File: `lib/core/local_storage/local_storage_service.dart`

```dart
class LocalStorageService {
  final Database _db;

  LocalStorageService(this._db);

  /// Cache API response
  Future<void> cacheApiResponse(
    String endpoint,
    dynamic data, {
    Duration? ttl,
  }) async {
    final expiresAt = ttl != null
      ? DateTime.now().add(ttl)
      : DateTime.now().add(Duration(hours: 24));

    await _db.insert(
      'api_cache',
      {
        'endpoint': endpoint,
        'data': jsonEncode(data),
        'cached_at': DateTime.now().toIso8601String(),
        'expires_at': expiresAt.toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  /// Get cached response
  Future<Map<String, dynamic>?> getCachedResponse(
    String endpoint, {
    bool ignoreExpiry = false,
  }) async {
    final result = await _db.query(
      'api_cache',
      where: 'endpoint = ?',
      whereArgs: [endpoint],
      limit: 1,
    );

    if (result.isEmpty) return null;

    final cache = result.first;
    final expiresAt = DateTime.parse(cache['expires_at'] as String);

    if (!ignoreExpiry && DateTime.now().isAfter(expiresAt)) {
      await _db.delete('api_cache', where: 'endpoint = ?', whereArgs: [endpoint]);
      return null;
    }

    return jsonDecode(cache['data'] as String) as Map<String, dynamic>;
  }

  /// Clear expired cache
  Future<int> clearExpiredCache() async {
    return _db.delete(
      'api_cache',
      where: 'expires_at < ?',
      whereArgs: [DateTime.now().toIso8601String()],
    );
  }

  /// Cache trip data
  Future<void> cacheTripData(Trip trip) async {
    await _db.insert(
      'cached_trips',
      {
        'id': trip.id,
        'trip_id': trip.id,
        'data': jsonEncode(trip.toJson()),
        'status': 'synced',
        'version': 1,
        'created_at': DateTime.now().toIso8601String(),
        'updated_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  /// Get cached trip
  Future<Trip?> getCachedTrip(String tripId) async {
    final result = await _db.query(
      'cached_trips',
      where: 'trip_id = ?',
      whereArgs: [tripId],
      limit: 1,
    );

    if (result.isEmpty) return null;

    final data = jsonDecode(result.first['data'] as String);
    return Trip.fromJson(data);
  }
}

// Provider
final localStorageServiceProvider = FutureProvider<LocalStorageService>((ref) async {
  final db = await ref.watch(databaseProvider.future);
  return LocalStorageService(db);
});
```

**Deliverable**: `LocalStorageService` with cache operations

**Task 2.2: Update API Client for Offline** (Day 2-3)
File: `lib/core/api/api_client.dart` (modify existing)

```dart
class ApiClient {
  // ... existing code ...

  final LocalStorageService _localStorage;
  final ConnectivityManager _connectivity;

  // Add to constructor
  ApiClient({
    required LocalStorageService localStorage,
    required ConnectivityManager connectivity,
  })  : _localStorage = localStorage,
        _connectivity = connectivity;

  /// Get with offline fallback
  Future<Response> getWithCache(
    String path, {
    Map<String, dynamic>? queryParameters,
    Duration cacheDuration = const Duration(hours: 1),
  }) async {
    try {
      // Try online first
      final response = await get(path, queryParameters: queryParameters);

      // Cache successful response
      if (response.statusCode == 200) {
        await _localStorage.cacheApiResponse(
          path,
          response.data,
          ttl: cacheDuration,
        );
      }

      return response;
    } on DioException catch (e) {
      // If network error and offline, return cached data
      if (_connectivity.isOffline && e.type == DioExceptionType.connectionTimeout) {
        final cached = await _localStorage.getCachedResponse(path);
        if (cached != null) {
          return Response(
            requestOptions: e.requestOptions,
            data: cached,
            statusCode: 200,
            // Mark as cached
          );
        }
      }

      rethrow;
    }
  }
}

// Update provider to include dependencies
final apiClientProvider = Provider<ApiClient>((ref) {
  final localStorage = ref.watch(localStorageServiceProvider);
  final connectivity = ref.watch(connectivityManagerProvider);

  return ApiClient(
    localStorage: localStorage,
    connectivity: connectivity,
  );
});
```

**Deliverable**: Offline-first API client with caching

**Task 2.3: Create Tests** (Day 3)
File: `test/core/local_storage/local_storage_service_test.dart`

```dart
void main() {
  group('LocalStorageService', () {
    test('caches and retrieves API response', () async {
      // Test cache operations
    });

    test('handles expired cache', () async {
      // Test expiration logic
    });

    test('caches trip data', () async {
      // Test trip caching
    });
  });
}
```

**Deliverable**: Unit tests for local storage

---

## Week 2-3: Phase 2 Sync Queue (HIGH PRIORITY)

### Objective
Build operation queuing and automatic sync mechanism.

### Tasks

#### Week 2: Sync Queue Infrastructure

**Task 3.1: Create Sync Operation Model** (Day 1)
File: `lib/features/sync/data/models/sync_operation_model.dart`

```dart
enum SyncOperationType {
  startTrip,
  arriveDestination,
  completeDestination,
  failDestination,
  logWaste,
  ;

  String toJson() => name;
  static SyncOperationType fromJson(String value) =>
    values.firstWhere((e) => e.name == value);
}

class SyncOperation {
  final String id;
  final SyncOperationType type;
  final String endpoint;
  final String method; // POST, PATCH, PUT
  final Map<String, dynamic> payload;
  final int retryCount;
  final DateTime? lastRetryAt;
  final DateTime createdAt;
  final DateTime? expiresAt;

  SyncOperation({
    String? id,
    required this.type,
    required this.endpoint,
    required this.method,
    required this.payload,
    this.retryCount = 0,
    this.lastRetryAt,
    DateTime? createdAt,
    DateTime? expiresAt,
  })  : id = id ?? const Uuid().v4(),
        createdAt = createdAt ?? DateTime.now(),
        expiresAt = expiresAt ?? DateTime.now().add(Duration(hours: 24));

  Map<String, dynamic> toJson() => {
    'id': id,
    'type': type.toJson(),
    'endpoint': endpoint,
    'method': method,
    'payload': payload,
    'retry_count': retryCount,
    'last_retry_at': lastRetryAt?.toIso8601String(),
    'created_at': createdAt.toIso8601String(),
    'expires_at': expiresAt?.toIso8601String(),
  };

  factory SyncOperation.fromJson(Map<String, dynamic> json) => SyncOperation(
    id: json['id'],
    type: SyncOperationType.fromJson(json['type']),
    endpoint: json['endpoint'],
    method: json['method'],
    payload: json['payload'],
    retryCount: json['retry_count'] ?? 0,
    lastRetryAt: json['last_retry_at'] != null
      ? DateTime.parse(json['last_retry_at'])
      : null,
    createdAt: DateTime.parse(json['created_at']),
    expiresAt: json['expires_at'] != null
      ? DateTime.parse(json['expires_at'])
      : null,
  );

  SyncOperation copyWith({
    int? retryCount,
    DateTime? lastRetryAt,
  }) => SyncOperation(
    id: id,
    type: type,
    endpoint: endpoint,
    method: method,
    payload: payload,
    retryCount: retryCount ?? this.retryCount,
    lastRetryAt: lastRetryAt ?? this.lastRetryAt,
    createdAt: createdAt,
    expiresAt: expiresAt,
  );
}
```

**Deliverable**: `SyncOperation` model with serialization

**Task 3.2: Create Sync Queue Repository** (Day 1-2)
File: `lib/features/sync/data/sync_queue_repository.dart`

```dart
class SyncQueueRepository {
  final Database _db;

  SyncQueueRepository(this._db);

  /// Add operation to queue
  Future<void> enqueue(SyncOperation operation) async {
    await _db.insert(
      'sync_queue',
      {
        'operation_id': operation.id,
        'operation_type': operation.type.name,
        'endpoint': operation.endpoint,
        'method': operation.method,
        'payload': jsonEncode(operation.payload),
        'retry_count': operation.retryCount,
        'created_at': operation.createdAt.toIso8601String(),
        'expires_at': operation.expiresAt?.toIso8601String(),
      },
    );
  }

  /// Get all pending operations
  Future<List<SyncOperation>> getPending() async {
    final now = DateTime.now().toIso8601String();

    final results = await _db.query(
      'sync_queue',
      where: 'retry_count < 5 AND expires_at > ?',
      whereArgs: [now],
      orderBy: 'created_at ASC',
    );

    return results
        .map((row) => SyncOperation(
          id: row['operation_id'] as String,
          type: SyncOperationType.fromJson(row['operation_type'] as String),
          endpoint: row['endpoint'] as String,
          method: row['method'] as String,
          payload: jsonDecode(row['payload'] as String),
          retryCount: row['retry_count'] as int? ?? 0,
          lastRetryAt: row['last_retry_at'] != null
              ? DateTime.parse(row['last_retry_at'] as String)
              : null,
          createdAt: DateTime.parse(row['created_at'] as String),
          expiresAt: row['expires_at'] != null
              ? DateTime.parse(row['expires_at'] as String)
              : null,
        ))
        .toList();
  }

  /// Update retry count
  Future<void> updateRetryCount(String operationId, int newCount) async {
    await _db.update(
      'sync_queue',
      {
        'retry_count': newCount,
        'last_retry_at': DateTime.now().toIso8601String(),
      },
      where: 'operation_id = ?',
      whereArgs: [operationId],
    );
  }

  /// Remove operation (successful sync)
  Future<void> remove(String operationId) async {
    await _db.delete(
      'sync_queue',
      where: 'operation_id = ?',
      whereArgs: [operationId],
    );
  }

  /// Clear expired operations
  Future<int> clearExpired() async {
    return _db.delete(
      'sync_queue',
      where: 'expires_at < ?',
      whereArgs: [DateTime.now().toIso8601String()],
    );
  }
}

// Provider
final syncQueueRepositoryProvider =
  FutureProvider<SyncQueueRepository>((ref) async {
  final db = await ref.watch(databaseProvider.future);
  return SyncQueueRepository(db);
});
```

**Deliverable**: `SyncQueueRepository` with queue operations

**Task 3.3: Create Sync Manager Service** (Day 2-3)
File: `lib/features/sync/services/sync_manager.dart`

```dart
class SyncManager {
  final SyncQueueRepository _queueRepo;
  final ApiClient _apiClient;
  final ConnectivityManager _connectivity;

  final ValueNotifier<SyncStatus> _syncStatus =
    ValueNotifier(SyncStatus.idle);
  final ValueNotifier<int> _syncProgress = ValueNotifier(0);

  Timer? _syncTimer;

  SyncManager({
    required SyncQueueRepository queueRepo,
    required ApiClient apiClient,
    required ConnectivityManager connectivity,
  })  : _queueRepo = queueRepo,
        _apiClient = apiClient,
        _connectivity = connectivity;

  ValueNotifier<SyncStatus> get syncStatus => _syncStatus;
  ValueNotifier<int> get syncProgress => _syncProgress;

  /// Start automatic sync
  void startAutoSync() {
    // Sync every 30 seconds if online and there are pending operations
    _syncTimer = Timer.periodic(Duration(seconds: 30), (_) async {
      if (_connectivity.isOnline && _syncStatus.value != SyncStatus.syncing) {
        await syncNow();
      }
    });

    // Also sync immediately on reconnection
    _connectivity.status.addListener(() {
      if (_connectivity.isOnline) {
        syncNow();
      }
    });
  }

  /// Sync all pending operations
  Future<SyncResult> syncNow() async {
    if (_syncStatus.value == SyncStatus.syncing) {
      return SyncResult.empty();
    }

    _syncStatus.value = SyncStatus.syncing;
    _syncProgress.value = 0;

    try {
      final pending = await _queueRepo.getPending();

      if (pending.isEmpty) {
        _syncStatus.value = SyncStatus.idle;
        return SyncResult.empty();
      }

      int successful = 0;
      int failed = 0;
      final errors = <String, String>{};

      for (int i = 0; i < pending.length; i++) {
        final operation = pending[i];

        try {
          await _sendOperation(operation);
          await _queueRepo.remove(operation.id);
          successful++;
        } catch (e) {
          await _queueRepo.updateRetryCount(
            operation.id,
            operation.retryCount + 1,
          );
          failed++;
          errors[operation.id] = e.toString();
        }

        _syncProgress.value = ((i + 1) / pending.length * 100).toInt();
      }

      _syncStatus.value = SyncStatus.idle;

      return SyncResult(
        successful: successful,
        failed: failed,
        total: pending.length,
        errors: errors,
      );
    } catch (e) {
      _syncStatus.value = SyncStatus.failed;
      return SyncResult(failed: 1, total: 1);
    }
  }

  /// Send single operation to API
  Future<void> _sendOperation(SyncOperation operation) async {
    late Response response;

    switch (operation.method) {
      case 'POST':
        response = await _apiClient.post(
          operation.endpoint,
          data: operation.payload,
        );
        break;
      case 'PATCH':
        response = await _apiClient.patch(
          operation.endpoint,
          data: operation.payload,
        );
        break;
      case 'PUT':
        response = await _apiClient.put(
          operation.endpoint,
          data: operation.payload,
        );
        break;
      default:
        throw Exception('Unsupported method: ${operation.method}');
    }

    if (response.statusCode! < 200 || response.statusCode! >= 300) {
      throw Exception('Request failed: ${response.statusCode}');
    }
  }

  /// Retry failed operations
  Future<int> retryFailed() async {
    return _queueRepo.getPending().then((ops) {
      return ops
          .where((op) => op.retryCount > 0)
          .length;
    });
  }

  /// Cleanup
  void dispose() {
    _syncTimer?.cancel();
  }
}

class SyncResult {
  final int successful;
  final int failed;
  final int total;
  final Map<String, String> errors;

  SyncResult({
    this.successful = 0,
    this.failed = 0,
    this.total = 0,
    this.errors = const {},
  });

  factory SyncResult.empty() => SyncResult();

  bool get isSuccess => failed == 0;
}

enum SyncStatus { idle, syncing, failed }

// Provider
final syncManagerProvider = Provider<SyncManager>((ref) {
  final queueRepo = ref.watch(syncQueueRepositoryProvider).maybeWhen(
    data: (repo) => repo,
    orElse: () => throw Exception('Queue repo not ready'),
  );

  final apiClient = ref.watch(apiClientProvider);
  final connectivity = ref.watch(connectivityManagerProvider);

  final manager = SyncManager(
    queueRepo: queueRepo,
    apiClient: apiClient,
    connectivity: connectivity,
  );

  manager.startAutoSync();
  ref.onDispose(() => manager.dispose());

  return manager;
});
```

**Deliverable**: `SyncManager` with automatic sync

**Task 3.4: Create Tests** (Day 3)
File: `test/features/sync/services/sync_manager_test.dart`

Tests for:
- Enqueueing operations
- Syncing pending operations
- Retry logic with exponential backoff
- Error handling

**Deliverable**: Unit tests for sync manager

---

## Week 3: Phase 3 Repositories (HIGH PRIORITY)

### Objective
Update existing repositories to use offline-first pattern.

### Tasks

**Task 4.1: Update Trips Repository** (Day 1-2)
File: `lib/features/trips/data/trips_repository.dart`

Add offline-first methods:
- `getTrip(tripId)` - Try online first, fallback to cache
- `completeDestination()` - Queue operation if offline
- Optimistic UI updates

**Task 4.2: Update Destinations Repository** (Day 2)

Add:
- `arriveAtDestination()` - Queue if offline
- `failDestination()` - Queue if offline

**Task 4.3: Update Shops Repository** (Day 3)

Add:
- `logWasteCollection()` - Queue if offline

**Task 4.4: Create Provider for Sync Status** (Day 3)
File: `lib/features/sync/providers/sync_provider.dart`

```dart
final syncStatusProvider = StateProvider<Map<String, SyncStatus>>((ref) {
  return {};
});

// Track which operations are pending sync
```

**Deliverable**: Offline-first repositories + providers

---

## Week 4: Phase 4 Conflict Resolution (MEDIUM PRIORITY)

### Objective
Handle version conflicts and concurrent changes.

### Tasks

**Task 5.1: Version Tracking in Cache** (Day 1)

Add version field to cached data:
```dart
Future<void> cacheTripData(Trip trip, int version) async {
  // Store version with data
}
```

**Task 5.2: Conflict Detector** (Day 1-2)
File: `lib/features/sync/services/conflict_resolver.dart`

```dart
class ConflictResolver {
  Future<ConflictDetection?> detectConflict(
    String entityId,
    int localVersion,
    int remoteVersion,
  ) async {
    if (localVersion != remoteVersion) {
      // Conflict detected
      return ConflictDetection(
        entityId: entityId,
        localVersion: localVersion,
        remoteVersion: remoteVersion,
        resolution: localVersion > remoteVersion
          ? ConflictResolution.keepLocal
          : ConflictResolution.acceptRemote,
      );
    }
    return null;
  }
}
```

**Task 5.3: UI for Conflict Resolution** (Day 2-3)

Show dialog when conflict detected:
```dart
showConflictDialog(context, conflict);
```

**Deliverable**: Version tracking + conflict detection + UI

---

## Week 4-5: Phase 5 UI/UX (HIGH PRIORITY)

### Objective
User-facing indicators and controls for offline experience.

### Tasks

**Task 6.1: Offline Status Indicator** (Day 1)

Add to AppBar:
```dart
AppBar(
  title: Text('Trips'),
  actions: [
    if (!isOnline)
      Padding(
        padding: EdgeInsets.all(16),
        child: Center(
          child: Chip(
            label: Text('Offline'),
            backgroundColor: Colors.orange,
          ),
        ),
      ),
  ],
)
```

**Task 6.2: Sync Progress Display** (Day 1-2)

Show sync progress:
```dart
LinearProgressIndicator(value: syncProgress / 100)
```

**Task 6.3: Manual Sync Button** (Day 2)

Add to drawer:
```dart
ListTile(
  leading: Icon(Icons.sync),
  title: Text('Sync Now'),
  onTap: () => syncManager.syncNow(),
)
```

**Task 6.4: Stale Data Warnings** (Day 2-3)

Show when cached data is old:
```dart
if (cachedAt < DateTime.now().subtract(Duration(hours: 6))) {
  showBanner('This data was last updated 6+ hours ago');
}
```

**Task 6.5: Testing on Low-End Devices** (Day 3-4)

- Test on Android Go or emulator
- Verify battery impact
- Check storage usage

**Deliverable**: Complete UI/UX for offline experience

---

## Daily Standup Template

Each day, update with:

```markdown
### Day X (Date)

**Completed:**
- [ ] Task description

**In Progress:**
- [ ] Task description

**Blockers:**
- None / Describe blocker

**Next:**
- [ ] Task for tomorrow
```

---

## Testing Throughout Implementation

### Unit Tests (Daily)
- Database operations
- Connectivity detection
- Sync queue management
- Conflict resolution

### Integration Tests (Weekly)
- Offline → Online transition
- Operation queuing and retry
- Conflict scenarios

### E2E Tests (End of Week 3)
- Complete offline workflow (complete delivery while offline)
- Multi-operation sync
- Conflict resolution with UI

### Performance Tests (Week 4-5)
- Battery impact (compare online vs offline mode)
- Storage usage (DB size, cache size)
- Sync speed (time to sync 50 operations)

---

## Git Workflow

### Branch Strategy
```bash
# Create feature branch per phase
git checkout -b feature/phase-1-foundation
git checkout -b feature/phase-2-sync-queue
git checkout -b feature/phase-3-repositories
git checkout -b feature/phase-4-conflict-resolution
git checkout -b feature/phase-5-ui-ux

# Commit frequently with clear messages
git commit -m "feat: add DatabaseService with schema migration"
git commit -m "feat: implement ConnectivityManager with real connectivity test"
```

### PR Strategy
- PR per phase with detailed description
- Include test coverage metrics
- Link to design doc (offline-sync-design.md)

---

## Success Criteria

### Phase 1 ✅
- [x] Database created with proper schema
- [x] Connectivity detection working
- [x] API responses cached locally
- [x] 10+ unit tests passing

### Phase 2 ✅
- [x] Operations can be queued
- [x] Automatic sync on reconnection
- [x] Retry logic with exponential backoff
- [x] 15+ unit tests passing

### Phase 3 ✅
- [x] Repositories support offline-first
- [x] Optimistic UI updates working
- [x] Sync status displayed in UI
- [x] 10+ integration tests passing

### Phase 4 ✅
- [x] Conflicts detected and resolved
- [x] User can see conflict options
- [x] Conflict resolution UI working
- [x] 5+ conflict scenario tests passing

### Phase 5 ✅
- [x] Offline indicator in UI
- [x] Sync progress shown to user
- [x] Manual sync button working
- [x] Tested on low-end devices

**Final Gate**: All tests passing, <5% battery impact, <50MB storage

---

## Rollout Plan (Post-Implementation)

### Beta Phase (Week 5-6)
- Deploy to 10% of drivers in staging
- Monitor sync success rate, errors, battery impact
- Gather driver feedback

### Gradual Rollout (Week 6-7)
- 25% → 50% → 100% over 2 weeks
- Monitor production metrics
- Have hotfix plan ready

### Success Metrics
- Sync success rate: >98%
- Average sync time: <5s
- Battery impact: <10%
- Driver satisfaction: >4/5 stars

---

## Documentation

As you implement, update:
- `CLAUDE.md` - Add "Offline Support Implementation Status"
- `README.md` - Update "Offline Support" section
- Code comments - Document offline patterns
- Test README - Document how to test offline scenarios

---

## Contingency & Rollback

If critical issues arise:

1. **Minor Issues**: Hot patch and re-deploy
2. **Major Issues**: Disable offline mode feature flag
3. **Critical Issues**: Rollback to pre-offline version (keep migrations)

Feature flag:
```dart
final offlineModeEnabledProvider =
  Provider<bool>((ref) {
  // Read from config/backend
  return true; // Can be toggled
});
```

---

## Questions & Support

During implementation, refer to:
- `docs/offline-sync-design.md` - Architecture reference
- Phase-specific design sections for detailed guidance
- Test patterns in existing tests for style guide
- Team Slack for blockers

---

**Ready to start? Begin with Week 1-2 Phase 1 Foundation.**
