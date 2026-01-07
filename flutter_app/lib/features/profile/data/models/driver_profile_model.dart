/// Driver Profile Model
class DriverProfileModel {
  final String id;
  final String name;
  final String email;
  final String phone;
  final String licenseNumber;
  final String? profilePhotoUrl;
  final bool isActive;
  final VehicleModel? vehicle;

  const DriverProfileModel({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.licenseNumber,
    this.profilePhotoUrl,
    required this.isActive,
    this.vehicle,
  });

  factory DriverProfileModel.fromJson(Map<String, dynamic> json) {
    return DriverProfileModel(
      id: json['id'] as String,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String? ?? '',
      licenseNumber: json['license_number'] as String? ?? '',
      profilePhotoUrl: json['profile_photo_url'] as String?,
      isActive: json['is_active'] as bool? ?? true,
      vehicle: json['vehicle'] != null
          ? VehicleModel.fromJson(json['vehicle'] as Map<String, dynamic>)
          : null,
    );
  }
}

/// Vehicle Model with odometer tracking
class VehicleModel {
  final String id;
  final String make;
  final String model;
  final int year;
  final String fullName;
  final String licensePlate;
  final String? acquisitionDate;
  final double acquisitionKm;
  final double totalKmDriven;
  final double monthlyKmApp;
  final double appTrackedKm;

  const VehicleModel({
    required this.id,
    required this.make,
    required this.model,
    required this.year,
    required this.fullName,
    required this.licensePlate,
    this.acquisitionDate,
    required this.acquisitionKm,
    required this.totalKmDriven,
    required this.monthlyKmApp,
    required this.appTrackedKm,
  });

  factory VehicleModel.fromJson(Map<String, dynamic> json) {
    return VehicleModel(
      id: json['id'] as String,
      make: json['make'] as String,
      model: json['model'] as String,
      year: json['year'] as int,
      fullName: json['full_name'] as String,
      licensePlate: json['license_plate'] as String,
      acquisitionDate: json['acquisition_date'] as String?,
      acquisitionKm: (json['acquisition_km'] as num).toDouble(),
      totalKmDriven: (json['total_km_driven'] as num).toDouble(),
      monthlyKmApp: (json['monthly_km_app'] as num).toDouble(),
      appTrackedKm: (json['app_tracked_km'] as num).toDouble(),
    );
  }
}
