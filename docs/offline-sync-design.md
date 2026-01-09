# Offline Support & Sync Strategy for Transportation MVP

## Executive Summary

The Transportation MVP Flutter app needs robust offline support to handle:
1. **Poor/No Connectivity**: Driver operates in areas with spotty coverage
2. **Long Trip Durations**: Multi-hour trips without consistent connectivity
3. **Sync on Recovery**: Automatic sync when connection is re-established
4. **Data Integrity**: No loss of trip data, waste logs, or completion statuses
5. **Conflict Resolution**: Handle changes on backend during offline period

**Implementation Approach**: Hybrid local-first + queue-based sync

---

## Current State Analysis

### What's Already There ✅
- `shared_preferences` - Simple key-value storage (auth tokens, preferences)
- `flutter_secure_storage` - Secure storage for sensitive data (API keys)
- `geolocator` - GPS tracking (works offline)
- `flutter_riverpod` - State management (can work with local data)
- `dio` - HTTP client (with interceptors for retry logic)

### What's Missing ❌
- **Local Database**: No SQLite/Hive for complex data storage
- **Sync Manager**: No queue for offline operations
- **Connectivity Detection**: No network state monitoring
- **Conflict Resolution**: No handling of concurrent changes
- **Data Versioning**: No tracking of data staleness
- **Offline UI Indicators**: No visual feedback for offline state

---

## Architecture Design

### 1. Local Storage Layer

#### Tech Stack
- **Primary**: `sqflite` (SQLite) - Reliable, built-in backup support, Android/iOS native
- **Alternative**: `drift` (wrapper around sqflite) - Type-safe, generates code
- **Cache Layer**: `hive` (optional) - Faster read/write for hot data

#### Database Schema (Mirrors Backend)

```sql
-- Cached API responses
CREATE TABLE api_cache (
    id INTEGER PRIMARY KEY,
    endpoint TEXT NOT NULL UNIQUE,
    data BLOB,
    version INT,
    cached_at TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX(expires_at)
);

-- Trips (cached from /trips/{id})
CREATE TABLE cached_trips (
    id TEXT PRIMARY KEY,
    trip_id TEXT NOT NULL UNIQUE,
    data BLOB,
    status TEXT, -- pending, in_progress, completed, synced
    local_updates BLOB,
    last_synced_at TIMESTAMP,
    version INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Destinations (per trip)
CREATE TABLE cached_destinations (
    id TEXT PRIMARY KEY,
    trip_id TEXT,
    destination_id TEXT,
    data BLOB,
    status TEXT, -- pending, arrived, completed, failed
    updated_at TIMESTAMP,
    FOREIGN KEY(trip_id) REFERENCES cached_trips(trip_id)
);

-- Destination items (per destination)
CREATE TABLE cached_destination_items (
    id TEXT PRIMARY KEY,
    destination_id TEXT,
    data BLOB,
    quantity_delivered INT,
    discrepancy_reason TEXT,
    updated_at TIMESTAMP,
    FOREIGN KEY(destination_id) REFERENCES cached_destinations(destination_id)
);

-- Waste collection data
CREATE TABLE cached_waste_collections (
    id TEXT PRIMARY KEY,
    trip_id TEXT,
    shop_id TEXT,
    data BLOB,
    items BLOB,
    collected_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY(trip_id) REFERENCES cached_trips(trip_id)
);

-- Pending operations queue
CREATE TABLE sync_queue (
    id INTEGER PRIMARY KEY,
    operation_id TEXT NOT NULL UNIQUE,
    operation_type TEXT, -- start_trip, arrive_destination, complete_destination, waste_collected
    endpoint TEXT,
    method TEXT, -- POST, PATCH, PUT
    payload BLOB,
    retry_count INT DEFAULT 0,
    last_retry_at TIMESTAMP,
    created_at TIMESTAMP,
    expires_at TIMESTAMP,
    INDEX(created_at),
    INDEX(expires_at)
);

-- Device state
CREATE TABLE device_state (
    key TEXT PRIMARY KEY,
    value TEXT,
    updated_at TIMESTAMP
);
```

#### Key Considerations
- **Versioning**: Track data versions for conflict detection
- **TTL**: Cache entries expire after X hours
- **Atomic Operations**: Use transactions for multi-table updates
- **Indexes**: On frequently queried fields (trip_id, status, created_at)

---

### 2. Connectivity & Sync Manager

#### Connectivity Detection Service

```dart
// packages/connectivity_plus provides native connectivity changes
// Monitors: WiFi, Mobile, None

final connectivityProvider = StreamProvider<ConnectivityStatus>((ref) {
  // Enum: online, offline, connecting
  // Emits state changes when connectivity changes
});

class ConnectivityManager {
  /// Check actual connectivity (doesn't just rely on system state)
  Future<bool> hasInternetConnection() {
    // Ping a reliable endpoint (e.g., 8.8.8.8 or your API)
    // System says online ≠ actually can reach internet
  }

  /// Subscribe to connectivity changes
  Stream<bool> onConnectivityChanged() {}

  /// Mark as explicitly offline (user toggle or app option)
  void setOfflineMode(bool offline) {}
}
```

#### Sync Manager Service

```dart
class SyncManager {
  /// Monitor sync queue and retry failed operations
  Future<void> startAutoSync() {
    // Every 30 seconds: check if online
    // If online: process sync queue
    // Track sync progress and failures
  }

  /// Manually trigger sync
  Future<SyncResult> syncNow() {
    // 1. Check connectivity
    // 2. Get pending operations from queue
    // 3. Group by priority (trip_start > destination_arrive > waste_collection)
    // 4. Send to backend in order
    // 5. Update local data on success
    // 6. Return results (success_count, failed_count, errors)
  }

  /// Enqueue operation for later sync
  Future<void> enqueueOperation(SyncOperation op) {
    // Add to sync_queue table
    // Auto-dispatch if online, else wait
  }

  /// Retry failed operations
  Future<SyncResult> retryFailed({
    Duration maxAge = const Duration(hours: 24),
    int maxRetries = 5,
  }) {
    // Find operations with retry_count < maxRetries
    // Retry with exponential backoff
    // Remove if max retries exceeded
  }

  /// Clear old sync queue entries (>24 hours old)
  Future<int> cleanupOldOperations() {}
}
```

---

### 3. Offline-First Data Flow

#### Current Architecture (Online-First)
```
UI → Provider → Repository → API Client → Backend
```

#### New Architecture (Offline-First)
```
UI → Provider → Repository →
  ├─ Check if online?
  ├─ Yes: API Client → Backend
  │       ├─ Store in cache
  │       └─ Update local DB
  └─ No: Local DB
       ├─ Return cached data
       └─ Queue operation for sync
```

#### Repository Pattern Update

```dart
abstract class BaseRepository {
  /// Get data with fallback to cache
  Future<T> fetch<T>(
    String endpoint, {
    Duration cacheDuration = const Duration(hours: 1),
    T? cacheData,
  }) async {
    try {
      // Try online first
      final data = await _apiClient.get(endpoint);
      await _localDb.cache(endpoint, data, DateTime.now().add(cacheDuration));
      return data;
    } on NetworkException catch (_) {
      // Fallback to cache
      final cached = await _localDb.getCache(endpoint);
      if (cached != null) {
        return cached;
      }
      // If no cache, return stale data or throw
      rethrow;
    }
  }

  /// Submit operation (sync or queue)
  Future<void> submitOperation(String endpoint, dynamic data) async {
    final op = SyncOperation(
      id: generateId(),
      endpoint: endpoint,
      method: 'POST',
      payload: data,
      timestamp: DateTime.now(),
    );

    if (await _connectivity.isOnline) {
      // Send immediately
      await _apiClient.post(endpoint, data: data);
      // Update local DB
    } else {
      // Queue for later
      await _syncManager.enqueue(op);
      // Optimistic update in local DB
    }
  }
}
```

---

### 4. Specific Feature: Offline Trip Completion

#### Scenario
1. Driver completes destination while offline
2. App saves to local DB + queues operation
3. App shows "Syncing..." with pending indicator
4. Driver regains connectivity
5. App automatically syncs completion
6. Backend processes it
7. UI updates to "Synced"

#### Implementation

**Trips Repository**
```dart
class TripsRepository extends BaseRepository {
  Future<Trip> completeDestination(Trip trip, Destination dest, List<DestinationItem> items) async {
    // 1. Update local DB immediately (optimistic)
    await _localDb.updateDestinationStatus(dest.id, 'completed', items);

    // 2. Create sync operation
    final operation = SyncOperation(
      type: 'complete_destination',
      endpoint: '/api/v1/driver/trips/${trip.id}/destinations/${dest.id}/complete',
      method: 'POST',
      payload: {
        'items': items.map((i) => {...}).toList(),
        'completed_at': DateTime.now().toIso8601String(),
      },
    );

    // 3. Try to sync immediately
    if (await _connectivity.isOnline) {
      try {
        final response = await _apiClient.post(operation.endpoint, data: operation.payload);
        await _localDb.markAsSynced(dest.id, 'synced');
        return Trip.fromJson(response);
      } on NetworkException {
        // Fall through to queue
      }
    }

    // 4. Queue for sync if offline
    await _syncManager.enqueue(operation);
    await _localDb.markAsSynced(dest.id, 'pending_sync');

    // 5. Return optimistically updated local data
    return _localDb.getTrip(trip.id);
  }
}
```

**Trips Provider**
```dart
// Track sync status per destination
final destinationSyncStatusProvider = StateProvider<Map<String, SyncStatus>>((ref) {
  return {};
});

// Auto-update when sync completes
ref.listen(syncManagerProvider, (_, next) {
  next.onSyncComplete.listen((result) {
    ref.read(destinationSyncStatusProvider.notifier).update(
      (state) => {...state, ...result.syncedIds},
    );
  });
});
```

**UI Widget**
```dart
Widget build(BuildContext context, WidgetRef ref) {
  final syncStatus = ref.watch(destinationSyncStatusProvider);
  final isDestinationSynced = syncStatus['dest-123'] == SyncStatus.synced;

  return ListTile(
    title: Text('Destination'),
    trailing: isDestinationSynced
      ? Icon(Icons.check_circle, color: Colors.green)
      : Icon(Icons.schedule, color: Colors.orange) // Pending sync
  );
}
```

---

### 5. Conflict Resolution Strategy

#### Conflict Scenarios

**Scenario 1: Driver marks trip complete, then unmarks (offline)**
- Operation 1: Complete trip
- Operation 2: Uncomplete trip (cancel)
- Solution: Keep only final state in queue

**Scenario 2: Backend updates trip while driver is offline**
- Driver sees locally-cached trip with outdated info
- Solution: Version tracking + refresh on sync

**Scenario 3: Two drivers assigned same trip (edge case)**
- Solution: Accept backend's truth on sync, show conflict UI

#### Implementation

```dart
class SyncConflictResolver {
  /// Detect if local state conflicts with backend
  Future<ConflictDetection?> detectConflict(
    String entityId,
    LocalData local,
    RemoteData remote,
  ) async {
    if (local.version != remote.version) {
      // Versions differ
      if (local.lastModified > remote.lastModified) {
        return ConflictDetection(
          type: ConflictType.localNewer,
          resolution: 'send_local', // Driver's changes win
        );
      } else {
        return ConflictDetection(
          type: ConflictType.remoteNewer,
          resolution: 'accept_remote', // Backend changes win
        );
      }
    }
    return null; // No conflict
  }

  /// Merge conflicting states
  Future<MergedData> mergeStates(
    LocalData local,
    RemoteData remote,
  ) async {
    // For trip completion:
    // - If local.status == completed and remote.status == pending
    // - → Keep local completion, but apply any remote updates to other fields

    // For waste collection:
    // - Same item collected offline and online?
    // - → Merge waste quantities (max waste)
  }
}
```

---

### 6. Specific Implementation: Trip Management

#### What Works Offline

| Feature | Offline Support | Notes |
|---------|-----------------|-------|
| View trips (today's) | ✅ Cached | Fetched when online, stored locally |
| Start trip | ✅ Queued | Timestamp recorded locally |
| Navigate to destination | ✅ Works | Uses cached GPS coordinates |
| Arrive at destination | ✅ Queued | Records local timestamp |
| Complete destination | ✅ Queued | Stores item quantities, reasons |
| Log waste collection | ✅ Queued | Stores waste data locally |
| View trip history | ✅ Partial | Shows only synced trips |

#### What Requires Online

| Feature | Online Required | Reason |
|---------|-----------------|--------|
| Create new trip | ✅ | Needs route optimization from backend |
| Delete trip | ✅ | Admin operation |
| Update shop info | ✅ | Needs backend validation |
| Real-time updates | ✅ | Push notifications from FCM |

---

### 7. Implementation Phases

#### Phase 1: Foundation (Week 1-2)
**Priority: Critical for core functionality**

- [ ] Add `sqflite` + `connectivity_plus` to pubspec.yaml
- [ ] Create local database schema (migrations)
- [ ] Build `LocalStorageService` abstraction
- [ ] Build `ConnectivityManager` with online/offline detection
- [ ] Modify `ApiClient` to use offline-first fallback
- [ ] Add retry logic to Dio interceptor

**Deliverables:**
- Local SQLite database with trips, destinations, waste
- Automatic cache of API responses
- Offline/online state detection
- Basic error handling for offline scenarios

#### Phase 2: Sync Queue (Week 2-3)
**Priority: High - enables offline trip completion**

- [ ] Create `SyncManager` service
- [ ] Build `SyncQueueRepository`
- [ ] Implement operation enqueueing
- [ ] Add sync worker (Timer-based or WorkManager)
- [ ] Build `SyncProgressProvider` for UI feedback
- [ ] Handle sync failures + retries

**Deliverables:**
- Offline operations queue (persistent)
- Automatic sync on connectivity recovery
- Retry logic with exponential backoff
- Progress tracking for UI

#### Phase 3: Repositories Update (Week 3)
**Priority: High - enables workflows**

- [ ] Update `TripsRepository` for offline-first
- [ ] Update `DestinationRepository`
- [ ] Update `ShopsRepository`
- [ ] Add optimistic updates to UI
- [ ] Add sync status indicators

**Deliverables:**
- All repositories support offline-first
- Optimistic UI updates
- Pending/synced indicators

#### Phase 4: Conflict Resolution (Week 4)
**Priority: Medium - handles edge cases**

- [ ] Implement version tracking in cache
- [ ] Build conflict detector
- [ ] Add conflict resolution UI
- [ ] Test multi-concurrent changes

**Deliverables:**
- Conflict detection for concurrent updates
- User-facing conflict resolution UI
- Comprehensive test scenarios

#### Phase 5: UI/UX (Week 4-5)
**Priority: High - user experience**

- [ ] Add offline status indicator (AppBar badge)
- [ ] Show sync progress (% complete)
- [ ] Show pending operations count
- [ ] Manual "Sync Now" button
- [ ] Stale data warnings
- [ ] Test on low-end devices

**Deliverables:**
- Clear offline/syncing UX
- Manual sync control
- Data freshness indicators

---

## Data Flow Examples

### Example 1: Complete Destination While Offline

```
User action: Tap "Confirm Delivery"
    ↓
[Check connectivity]
    ├─ Online → API call immediately (existing behavior)
    └─ Offline:
       ├─ Store in local DB: destinations[id].status = 'completed'
       ├─ Create SyncOperation:
       │  {
       │    type: 'complete_destination',
       │    endpoint: '/api/v1/driver/trips/{trip_id}/destinations/{dest_id}/complete',
       │    payload: {...}
       │  }
       ├─ Queue in sync_queue table
       ├─ Update UI: Show "Pending Sync" badge
       └─ Return immediately to driver

[Later: Connectivity restored]
    ↓
[SyncManager timer fires]
    ├─ Detect online
    ├─ Fetch pending operations
    ├─ Send to API (with retry logic)
    ├─ On success: Update local DB, clear sync_queue entry
    ├─ On failure: Increment retry_count, reschedule
    └─ Update UI: Remove badge, show synced
```

### Example 2: View Trip While Offline

```
User action: Open trip details
    ↓
[TripsRepository.getTrip(tripId)]
    ├─ Check connectivity
    ├─ Online:
    │  ├─ Fetch from API
    │  ├─ Store in local cache (destinations, items, etc.)
    │  └─ Return fresh data
    └─ Offline:
       ├─ Check cache (api_cache table)
       ├─ If cached and not expired:
       │  ├─ Return with "cached" flag
       │  └─ Show "Last updated: 2 hours ago"
       └─ If not cached or expired:
          ├─ Fetch from cached_trips table
          └─ Show "Offline - may be outdated"
```

### Example 3: Sync with Conflict

```
Scenario: Trip updated on backend while driver offline

Driver offline:
  - Marks destination complete, saves locally
  - version_local = 5, status = 'completed'

Backend meanwhile:
  - Trip reassigned, version_remote = 6, status = 'pending'

Driver reconnects & syncs:
    ↓
[SyncManager.syncNow()]
    ├─ Send: complete_destination operation
    ├─ Backend responds: Conflict (version mismatch)
    ├─ ConflictResolver.detectConflict():
    │  └─ version_local (5) < version_remote (6)
    ├─ Decision: Accept remote (backend's truth is authoritative)
    ├─ Refresh trip from backend (version_remote = 6)
    ├─ Show toast: "Trip was reassigned"
    └─ Update UI to show reassigned state
```

---

## Testing Strategy

### Unit Tests
- [ ] `LocalStorageService` - CRUD operations
- [ ] `SyncManager` - Queue operations, retries
- [ ] `ConflictResolver` - Conflict detection, resolution
- [ ] `ConnectivityManager` - State transitions

### Integration Tests
- [ ] Offline trip completion → Sync → Backend update
- [ ] Multi-device conflict resolution
- [ ] Sync failure recovery
- [ ] Cache expiration logic

### E2E Tests
- [ ] Complete offline workflow (no internet for 1 hour)
- [ ] Sporadic connectivity (frequent disconnects)
- [ ] Large payload sync (many items)

---

## Performance Considerations

| Concern | Strategy |
|---------|----------|
| Battery drain (GPS + sync) | Batch operations, smart retry timing |
| Storage usage (large cache) | TTL-based cleanup, configurable retention |
| Sync slowness (many pending) | Prioritize trip operations, batch API calls |
| Database locks | Use transactions carefully, avoid long locks |
| Memory (large datasets) | Pagination, lazy loading |

---

## Rollout Plan

1. **Phase 1** (Week 1): Foundation - basic offline caching, no disruption
2. **Phase 2** (Week 2): Beta - limited users test sync queue
3. **Phase 3** (Week 3): Gradual - deploy to 50% of drivers
4. **Phase 4** (Week 4): Full rollout with monitoring

**Monitoring:**
- Sync success rate (target: 99%+)
- Average sync time (target: <5s for <50 operations)
- User errors (conflicts, stale data issues)
- Battery impact (baseline vs offline-enabled)

---

## FAQ

**Q: What happens if driver loses internet for 24+ hours?**
A: Operations expire after 24 hours, no longer sync. Driver is notified.

**Q: Can drivers work fully offline?**
A: No, they need internet for: new trip assignments, real-time updates. But they can complete existing deliveries offline.

**Q: What if sync queue fills up?**
A: Old operations (>24h) are auto-deleted. Recent high-priority ops are kept.

**Q: What about data privacy (local storage)?**
A: Use `flutter_secure_storage` for sensitive fields (tokens), regular SQLite for operational data.

**Q: How do we test this without actually disconnecting internet?**
A: Mock `ConnectivityManager`, use fake API responses in tests.

---

## Success Criteria

- ✅ Drivers can complete deliveries with no internet
- ✅ All operations sync automatically on reconnection
- ✅ Sync success rate >98%
- ✅ No data loss or duplication
- ✅ <5s sync time for typical trip
- ✅ Battery impact <10% increase
- ✅ Stale data clearly marked in UI
