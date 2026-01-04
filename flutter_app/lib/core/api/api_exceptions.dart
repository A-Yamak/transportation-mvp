/// Base API Exception
class ApiException implements Exception {
  final String message;
  final String? code;
  final Map<String, dynamic>? errors;

  const ApiException({
    required this.message,
    this.code,
    this.errors,
  });

  /// Network error (no connection)
  factory ApiException.network() => const ApiException(
    message: 'خطأ في الاتصال. تحقق من اتصالك بالإنترنت.',
    code: 'NETWORK_ERROR',
  );

  /// Timeout error
  factory ApiException.timeout() => const ApiException(
    message: 'انتهت مهلة الاتصال. حاول مرة أخرى.',
    code: 'TIMEOUT',
  );

  /// Unauthorized (401)
  factory ApiException.unauthorized([String? message]) => ApiException(
    message: message ?? 'جلستك انتهت. الرجاء تسجيل الدخول مرة أخرى.',
    code: 'UNAUTHORIZED',
  );

  /// Not found (404)
  factory ApiException.notFound([String? message]) => ApiException(
    message: message ?? 'المورد غير موجود.',
    code: 'NOT_FOUND',
  );

  /// Validation error (422)
  factory ApiException.validation(String? message, Map<String, dynamic>? errors) => ApiException(
    message: message ?? 'خطأ في البيانات المدخلة.',
    code: 'VALIDATION_ERROR',
    errors: errors,
  );

  /// Server error (5xx)
  factory ApiException.server([String? message]) => ApiException(
    message: message ?? 'حدث خطأ في الخادم. حاول لاحقاً.',
    code: 'SERVER_ERROR',
  );

  /// Unknown error
  factory ApiException.unknown([String? message]) => ApiException(
    message: message ?? 'حدث خطأ غير متوقع.',
    code: 'UNKNOWN_ERROR',
  );

  @override
  String toString() => message;
}
