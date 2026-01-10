import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_config.dart';
import 'api_exceptions.dart';

/// API Client provider
final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient();
});

/// Secure storage provider
final secureStorageProvider = Provider<FlutterSecureStorage>((ref) {
  return const FlutterSecureStorage();
});

/// HTTP API Client with authentication handling
class ApiClient {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.receiveTimeout,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.addAll([
      _AuthInterceptor(_storage, _dio),
      _LoggingInterceptor(),
    ]);
  }

  /// GET request
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.get<T>(
        path,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// POST request
  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// PATCH request
  Future<Response<T>> patch<T>(
    String path, {
    dynamic data,
    Options? options,
  }) async {
    try {
      return await _dio.patch<T>(
        path,
        data: data,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// DELETE request
  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Options? options,
  }) async {
    try {
      return await _dio.delete<T>(
        path,
        data: data,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Convert DioException to ApiException
  ApiException _handleError(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException.timeout();
      case DioExceptionType.connectionError:
        return ApiException.network();
      case DioExceptionType.badResponse:
        final statusCode = e.response?.statusCode ?? 500;
        final data = e.response?.data;
        final message = data is Map ? data['message'] : null;

        if (statusCode == 401) {
          return ApiException.unauthorized(message);
        } else if (statusCode == 422) {
          final errors = data is Map ? data['errors'] : null;
          return ApiException.validation(message, errors);
        } else if (statusCode == 404) {
          return ApiException.notFound(message);
        } else if (statusCode >= 500) {
          return ApiException.server(message);
        }
        return ApiException.unknown(message);
      default:
        return ApiException.unknown(e.message);
    }
  }
}

/// Auth interceptor - adds token and handles refresh
class _AuthInterceptor extends Interceptor {
  final FlutterSecureStorage _storage;
  final Dio _dio;

  _AuthInterceptor(this._storage, this._dio);

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    // Skip auth for login/register/forgot-password endpoints
    if (options.path.contains('/auth/login') ||
        options.path.contains('/auth/register') ||
        options.path.contains('/auth/forgot-password')) {
      return handler.next(options);
    }

    final token = await _storage.read(key: 'access_token');
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }

    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      // Try to refresh token
      final refreshToken = await _storage.read(key: 'refresh_token');
      if (refreshToken != null) {
        try {
          final response = await _dio.post(
            '/api/v1/auth/refresh',
            data: {'refresh_token': refreshToken},
          );

          if (response.statusCode == 200) {
            final newToken = response.data['access_token'];
            final newRefresh = response.data['refresh_token'];

            await _storage.write(key: 'access_token', value: newToken);
            await _storage.write(key: 'refresh_token', value: newRefresh);

            // Retry original request
            err.requestOptions.headers['Authorization'] = 'Bearer $newToken';
            final retryResponse = await _dio.fetch(err.requestOptions);
            return handler.resolve(retryResponse);
          }
        } catch (_) {
          // Refresh failed, clear tokens
          await _storage.delete(key: 'access_token');
          await _storage.delete(key: 'refresh_token');
        }
      }
    }
    handler.next(err);
  }
}

/// Logging interceptor for debugging
class _LoggingInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    print('→ ${options.method} ${options.path}');
    print('→ Headers: ${options.headers}');
    print('→ Data: ${options.data}');
    handler.next(options);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    print('← ${response.statusCode} ${response.requestOptions.path}');
    handler.next(response);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    print('✗ ${err.response?.statusCode} ${err.requestOptions.path}: ${err.message}');
    print('✗ Response body: ${err.response?.data}');
    handler.next(err);
  }
}
