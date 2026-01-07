import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// The title of the application
  ///
  /// In ar, this message translates to:
  /// **'تطبيق السائق'**
  String get appTitle;

  /// No description provided for @login.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الدخول'**
  String get login;

  /// No description provided for @logout.
  ///
  /// In ar, this message translates to:
  /// **'تسجيل الخروج'**
  String get logout;

  /// No description provided for @email.
  ///
  /// In ar, this message translates to:
  /// **'البريد الإلكتروني'**
  String get email;

  /// No description provided for @password.
  ///
  /// In ar, this message translates to:
  /// **'كلمة المرور'**
  String get password;

  /// No description provided for @forgotPassword.
  ///
  /// In ar, this message translates to:
  /// **'نسيت كلمة المرور؟'**
  String get forgotPassword;

  /// No description provided for @loginButton.
  ///
  /// In ar, this message translates to:
  /// **'دخول'**
  String get loginButton;

  /// No description provided for @loggingIn.
  ///
  /// In ar, this message translates to:
  /// **'جاري تسجيل الدخول...'**
  String get loggingIn;

  /// No description provided for @todaysTrips.
  ///
  /// In ar, this message translates to:
  /// **'رحلات اليوم'**
  String get todaysTrips;

  /// No description provided for @noTripsToday.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد رحلات اليوم'**
  String get noTripsToday;

  /// No description provided for @trip.
  ///
  /// In ar, this message translates to:
  /// **'رحلة'**
  String get trip;

  /// No description provided for @trips.
  ///
  /// In ar, this message translates to:
  /// **'رحلات'**
  String get trips;

  /// No description provided for @destinations.
  ///
  /// In ar, this message translates to:
  /// **'وجهات'**
  String get destinations;

  /// No description provided for @destination.
  ///
  /// In ar, this message translates to:
  /// **'وجهة'**
  String get destination;

  /// No description provided for @startTrip.
  ///
  /// In ar, this message translates to:
  /// **'ابدأ الرحلة'**
  String get startTrip;

  /// No description provided for @continueTrip.
  ///
  /// In ar, this message translates to:
  /// **'متابعة'**
  String get continueTrip;

  /// No description provided for @endTrip.
  ///
  /// In ar, this message translates to:
  /// **'إنهاء الرحلة'**
  String get endTrip;

  /// No description provided for @tripStarted.
  ///
  /// In ar, this message translates to:
  /// **'بدأت الرحلة'**
  String get tripStarted;

  /// No description provided for @tripCompleted.
  ///
  /// In ar, this message translates to:
  /// **'اكتملت الرحلة'**
  String get tripCompleted;

  /// No description provided for @navigate.
  ///
  /// In ar, this message translates to:
  /// **'التوجيه'**
  String get navigate;

  /// No description provided for @markArrived.
  ///
  /// In ar, this message translates to:
  /// **'تم الوصول'**
  String get markArrived;

  /// No description provided for @markComplete.
  ///
  /// In ar, this message translates to:
  /// **'تم التسليم'**
  String get markComplete;

  /// No description provided for @markFailed.
  ///
  /// In ar, this message translates to:
  /// **'فشل التسليم'**
  String get markFailed;

  /// No description provided for @arrived.
  ///
  /// In ar, this message translates to:
  /// **'تم الوصول'**
  String get arrived;

  /// No description provided for @completed.
  ///
  /// In ar, this message translates to:
  /// **'مكتمل'**
  String get completed;

  /// No description provided for @failed.
  ///
  /// In ar, this message translates to:
  /// **'فشل'**
  String get failed;

  /// No description provided for @pending.
  ///
  /// In ar, this message translates to:
  /// **'قيد الانتظار'**
  String get pending;

  /// No description provided for @inProgress.
  ///
  /// In ar, this message translates to:
  /// **'جاري'**
  String get inProgress;

  /// No description provided for @notStarted.
  ///
  /// In ar, this message translates to:
  /// **'لم تبدأ'**
  String get notStarted;

  /// No description provided for @cancelled.
  ///
  /// In ar, this message translates to:
  /// **'ملغاة'**
  String get cancelled;

  /// No description provided for @kilometers.
  ///
  /// In ar, this message translates to:
  /// **'كم'**
  String get kilometers;

  /// No description provided for @km.
  ///
  /// In ar, this message translates to:
  /// **'كم'**
  String get km;

  /// No description provided for @estimatedKm.
  ///
  /// In ar, this message translates to:
  /// **'المسافة المقدرة'**
  String get estimatedKm;

  /// No description provided for @actualKm.
  ///
  /// In ar, this message translates to:
  /// **'المسافة الفعلية'**
  String get actualKm;

  /// No description provided for @failureReasonNotHome.
  ///
  /// In ar, this message translates to:
  /// **'العميل غير موجود'**
  String get failureReasonNotHome;

  /// No description provided for @failureReasonRefused.
  ///
  /// In ar, this message translates to:
  /// **'رفض الاستلام'**
  String get failureReasonRefused;

  /// No description provided for @failureReasonWrongAddress.
  ///
  /// In ar, this message translates to:
  /// **'عنوان خاطئ'**
  String get failureReasonWrongAddress;

  /// No description provided for @failureReasonInaccessible.
  ///
  /// In ar, this message translates to:
  /// **'موقع غير قابل للوصول'**
  String get failureReasonInaccessible;

  /// No description provided for @failureReasonOther.
  ///
  /// In ar, this message translates to:
  /// **'سبب آخر'**
  String get failureReasonOther;

  /// No description provided for @selectFailureReason.
  ///
  /// In ar, this message translates to:
  /// **'اختر سبب الفشل'**
  String get selectFailureReason;

  /// No description provided for @additionalNotes.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات إضافية'**
  String get additionalNotes;

  /// No description provided for @recipientName.
  ///
  /// In ar, this message translates to:
  /// **'اسم المستلم'**
  String get recipientName;

  /// No description provided for @deliveryNotes.
  ///
  /// In ar, this message translates to:
  /// **'ملاحظات التسليم'**
  String get deliveryNotes;

  /// No description provided for @confirmDelivery.
  ///
  /// In ar, this message translates to:
  /// **'تأكيد التسليم'**
  String get confirmDelivery;

  /// No description provided for @settings.
  ///
  /// In ar, this message translates to:
  /// **'الإعدادات'**
  String get settings;

  /// No description provided for @language.
  ///
  /// In ar, this message translates to:
  /// **'اللغة'**
  String get language;

  /// No description provided for @arabic.
  ///
  /// In ar, this message translates to:
  /// **'العربية'**
  String get arabic;

  /// No description provided for @english.
  ///
  /// In ar, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @error.
  ///
  /// In ar, this message translates to:
  /// **'خطأ'**
  String get error;

  /// No description provided for @tryAgain.
  ///
  /// In ar, this message translates to:
  /// **'حاول مرة أخرى'**
  String get tryAgain;

  /// No description provided for @networkError.
  ///
  /// In ar, this message translates to:
  /// **'خطأ في الاتصال'**
  String get networkError;

  /// No description provided for @unknownError.
  ///
  /// In ar, this message translates to:
  /// **'حدث خطأ غير متوقع'**
  String get unknownError;

  /// No description provided for @invalidCredentials.
  ///
  /// In ar, this message translates to:
  /// **'بيانات الدخول غير صحيحة'**
  String get invalidCredentials;

  /// No description provided for @loading.
  ///
  /// In ar, this message translates to:
  /// **'جاري التحميل...'**
  String get loading;

  /// No description provided for @pullToRefresh.
  ///
  /// In ar, this message translates to:
  /// **'اسحب للتحديث'**
  String get pullToRefresh;

  /// No description provided for @totalTrips.
  ///
  /// In ar, this message translates to:
  /// **'إجمالي الرحلات'**
  String get totalTrips;

  /// No description provided for @completedTrips.
  ///
  /// In ar, this message translates to:
  /// **'الرحلات المكتملة'**
  String get completedTrips;

  /// No description provided for @totalKmDriven.
  ///
  /// In ar, this message translates to:
  /// **'إجمالي الكيلومترات'**
  String get totalKmDriven;

  /// No description provided for @welcome.
  ///
  /// In ar, this message translates to:
  /// **'مرحباً'**
  String get welcome;

  /// No description provided for @goodMorning.
  ///
  /// In ar, this message translates to:
  /// **'صباح الخير'**
  String get goodMorning;

  /// No description provided for @goodAfternoon.
  ///
  /// In ar, this message translates to:
  /// **'مساء الخير'**
  String get goodAfternoon;

  /// No description provided for @goodEvening.
  ///
  /// In ar, this message translates to:
  /// **'مساء الخير'**
  String get goodEvening;

  /// No description provided for @arrivedSuccess.
  ///
  /// In ar, this message translates to:
  /// **'تم التمييز كوصول'**
  String get arrivedSuccess;

  /// No description provided for @completedSuccess.
  ///
  /// In ar, this message translates to:
  /// **'تم التسليم!'**
  String get completedSuccess;

  /// No description provided for @navigateTo.
  ///
  /// In ar, this message translates to:
  /// **'انتقل إلى: {address}'**
  String navigateTo(Object address);

  /// No description provided for @tripId.
  ///
  /// In ar, this message translates to:
  /// **'معرف الرحلة'**
  String get tripId;

  /// No description provided for @profile.
  ///
  /// In ar, this message translates to:
  /// **'الملف الشخصي'**
  String get profile;

  /// No description provided for @statistics.
  ///
  /// In ar, this message translates to:
  /// **'الإحصائيات'**
  String get statistics;

  /// No description provided for @today.
  ///
  /// In ar, this message translates to:
  /// **'اليوم'**
  String get today;

  /// No description provided for @thisMonth.
  ///
  /// In ar, this message translates to:
  /// **'هذا الشهر'**
  String get thisMonth;

  /// No description provided for @allTime.
  ///
  /// In ar, this message translates to:
  /// **'الإجمالي'**
  String get allTime;

  /// No description provided for @deliveries.
  ///
  /// In ar, this message translates to:
  /// **'التوصيلات'**
  String get deliveries;

  /// No description provided for @distance.
  ///
  /// In ar, this message translates to:
  /// **'المسافة'**
  String get distance;

  /// No description provided for @tripHistory.
  ///
  /// In ar, this message translates to:
  /// **'سجل الرحلات'**
  String get tripHistory;

  /// No description provided for @noTripHistory.
  ///
  /// In ar, this message translates to:
  /// **'لا يوجد سجل رحلات'**
  String get noTripHistory;

  /// No description provided for @allStatuses.
  ///
  /// In ar, this message translates to:
  /// **'جميع الحالات'**
  String get allStatuses;

  /// No description provided for @duration.
  ///
  /// In ar, this message translates to:
  /// **'المدة'**
  String get duration;

  /// No description provided for @retry.
  ///
  /// In ar, this message translates to:
  /// **'إعادة المحاولة'**
  String get retry;

  /// No description provided for @odometer.
  ///
  /// In ar, this message translates to:
  /// **'عداد المسافات'**
  String get odometer;

  /// No description provided for @currentOdometer.
  ///
  /// In ar, this message translates to:
  /// **'القراءة الحالية'**
  String get currentOdometer;

  /// No description provided for @acquisitionKm.
  ///
  /// In ar, this message translates to:
  /// **'كم عند الاستلام'**
  String get acquisitionKm;

  /// No description provided for @appTrackedKm.
  ///
  /// In ar, this message translates to:
  /// **'عبر التطبيق'**
  String get appTrackedKm;

  /// No description provided for @trackedViaApp.
  ///
  /// In ar, this message translates to:
  /// **'مسجل عبر التطبيق'**
  String get trackedViaApp;

  /// No description provided for @noVehicleAssigned.
  ///
  /// In ar, this message translates to:
  /// **'لا توجد مركبة مخصصة'**
  String get noVehicleAssigned;

  /// No description provided for @fuelEfficiency.
  ///
  /// In ar, this message translates to:
  /// **'استهلاك الوقود'**
  String get fuelEfficiency;

  /// No description provided for @tankCapacity.
  ///
  /// In ar, this message translates to:
  /// **'الخزان'**
  String get tankCapacity;

  /// No description provided for @fullTankRange.
  ///
  /// In ar, this message translates to:
  /// **'المدى'**
  String get fullTankRange;

  /// No description provided for @kmPerLiter.
  ///
  /// In ar, this message translates to:
  /// **'كم/لتر'**
  String get kmPerLiter;

  /// No description provided for @pricePerKm.
  ///
  /// In ar, this message translates to:
  /// **'سعر/كم'**
  String get pricePerKm;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
