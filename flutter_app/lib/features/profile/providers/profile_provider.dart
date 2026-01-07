import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/profile_repository.dart';
import '../data/models/driver_profile_model.dart';
import '../data/models/driver_stats_model.dart';
import '../data/models/trip_history_model.dart';

/// Driver Profile Provider
final driverProfileProvider = FutureProvider<DriverProfileModel>((ref) async {
  final repository = ref.watch(profileRepositoryProvider);
  return repository.getProfile();
});

/// Driver Stats Provider
final driverStatsProvider = FutureProvider<DriverStatsModel>((ref) async {
  final repository = ref.watch(profileRepositoryProvider);
  return repository.getStats();
});

/// Trip History Provider with pagination support
final tripHistoryProvider = StateNotifierProvider<TripHistoryNotifier, TripHistoryState>((ref) {
  return TripHistoryNotifier(ref.watch(profileRepositoryProvider));
});

/// State for trip history
class TripHistoryState {
  final List<TripHistoryModel> trips;
  final bool isLoading;
  final bool hasMore;
  final int currentPage;
  final String? error;
  final String? statusFilter;
  final String? fromDate;
  final String? toDate;

  const TripHistoryState({
    this.trips = const [],
    this.isLoading = false,
    this.hasMore = true,
    this.currentPage = 0,
    this.error,
    this.statusFilter,
    this.fromDate,
    this.toDate,
  });

  TripHistoryState copyWith({
    List<TripHistoryModel>? trips,
    bool? isLoading,
    bool? hasMore,
    int? currentPage,
    String? error,
    String? statusFilter,
    String? fromDate,
    String? toDate,
  }) {
    return TripHistoryState(
      trips: trips ?? this.trips,
      isLoading: isLoading ?? this.isLoading,
      hasMore: hasMore ?? this.hasMore,
      currentPage: currentPage ?? this.currentPage,
      error: error,
      statusFilter: statusFilter ?? this.statusFilter,
      fromDate: fromDate ?? this.fromDate,
      toDate: toDate ?? this.toDate,
    );
  }
}

/// Notifier for trip history with pagination
class TripHistoryNotifier extends StateNotifier<TripHistoryState> {
  final ProfileRepository _repository;

  TripHistoryNotifier(this._repository) : super(const TripHistoryState());

  /// Load first page of trips
  Future<void> loadTrips({String? status, String? from, String? to}) async {
    state = state.copyWith(
      isLoading: true,
      trips: [],
      currentPage: 0,
      hasMore: true,
      statusFilter: status,
      fromDate: from,
      toDate: to,
    );

    try {
      final response = await _repository.getTripHistory(
        page: 1,
        status: status,
        from: from,
        to: to,
      );

      state = state.copyWith(
        trips: response.trips,
        isLoading: false,
        hasMore: response.hasMore,
        currentPage: 1,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  /// Load next page of trips
  Future<void> loadMore() async {
    if (state.isLoading || !state.hasMore) return;

    state = state.copyWith(isLoading: true);

    try {
      final response = await _repository.getTripHistory(
        page: state.currentPage + 1,
        status: state.statusFilter,
        from: state.fromDate,
        to: state.toDate,
      );

      state = state.copyWith(
        trips: [...state.trips, ...response.trips],
        isLoading: false,
        hasMore: response.hasMore,
        currentPage: state.currentPage + 1,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }

  /// Refresh trips
  Future<void> refresh() async {
    await loadTrips(
      status: state.statusFilter,
      from: state.fromDate,
      to: state.toDate,
    );
  }
}
