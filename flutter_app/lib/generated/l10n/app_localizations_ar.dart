// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appTitle => 'تطبيق السائق';

  @override
  String get login => 'تسجيل الدخول';

  @override
  String get logout => 'تسجيل الخروج';

  @override
  String get email => 'البريد الإلكتروني';

  @override
  String get password => 'كلمة المرور';

  @override
  String get forgotPassword => 'نسيت كلمة المرور؟';

  @override
  String get loginButton => 'دخول';

  @override
  String get loggingIn => 'جاري تسجيل الدخول...';

  @override
  String get todaysTrips => 'رحلات اليوم';

  @override
  String get noTripsToday => 'لا توجد رحلات اليوم';

  @override
  String get trip => 'رحلة';

  @override
  String get trips => 'رحلات';

  @override
  String get destinations => 'وجهات';

  @override
  String get destination => 'وجهة';

  @override
  String get startTrip => 'ابدأ الرحلة';

  @override
  String get continueTrip => 'متابعة';

  @override
  String get endTrip => 'إنهاء الرحلة';

  @override
  String get tripStarted => 'بدأت الرحلة';

  @override
  String get tripCompleted => 'اكتملت الرحلة';

  @override
  String get navigate => 'التوجيه';

  @override
  String get markArrived => 'تم الوصول';

  @override
  String get markComplete => 'تم التسليم';

  @override
  String get markFailed => 'فشل التسليم';

  @override
  String get arrived => 'تم الوصول';

  @override
  String get completed => 'مكتمل';

  @override
  String get failed => 'فشل';

  @override
  String get pending => 'قيد الانتظار';

  @override
  String get inProgress => 'جاري';

  @override
  String get notStarted => 'لم تبدأ';

  @override
  String get cancelled => 'ملغاة';

  @override
  String get kilometers => 'كم';

  @override
  String get km => 'كم';

  @override
  String get estimatedKm => 'المسافة المقدرة';

  @override
  String get actualKm => 'المسافة الفعلية';

  @override
  String get failureReasonNotHome => 'العميل غير موجود';

  @override
  String get failureReasonRefused => 'رفض الاستلام';

  @override
  String get failureReasonWrongAddress => 'عنوان خاطئ';

  @override
  String get failureReasonInaccessible => 'موقع غير قابل للوصول';

  @override
  String get failureReasonOther => 'سبب آخر';

  @override
  String get selectFailureReason => 'اختر سبب الفشل';

  @override
  String get additionalNotes => 'ملاحظات إضافية';

  @override
  String get recipientName => 'اسم المستلم';

  @override
  String get deliveryNotes => 'ملاحظات التسليم';

  @override
  String get confirmDelivery => 'تأكيد التسليم';

  @override
  String get settings => 'الإعدادات';

  @override
  String get language => 'اللغة';

  @override
  String get arabic => 'العربية';

  @override
  String get english => 'English';

  @override
  String get error => 'خطأ';

  @override
  String get tryAgain => 'حاول مرة أخرى';

  @override
  String get networkError => 'خطأ في الاتصال';

  @override
  String get unknownError => 'حدث خطأ غير متوقع';

  @override
  String get invalidCredentials => 'بيانات الدخول غير صحيحة';

  @override
  String get loading => 'جاري التحميل...';

  @override
  String get pullToRefresh => 'اسحب للتحديث';

  @override
  String get totalTrips => 'إجمالي الرحلات';

  @override
  String get completedTrips => 'الرحلات المكتملة';

  @override
  String get totalKmDriven => 'إجمالي الكيلومترات';

  @override
  String get welcome => 'مرحباً';

  @override
  String get goodMorning => 'صباح الخير';

  @override
  String get goodAfternoon => 'مساء الخير';

  @override
  String get goodEvening => 'مساء الخير';

  @override
  String get arrivedSuccess => 'تم التمييز كوصول';

  @override
  String get completedSuccess => 'تم التسليم!';

  @override
  String navigateTo(Object address) {
    return 'انتقل إلى: $address';
  }

  @override
  String get tripId => 'معرف الرحلة';
}
