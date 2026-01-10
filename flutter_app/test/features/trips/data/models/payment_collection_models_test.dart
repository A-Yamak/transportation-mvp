import 'package:flutter_test/flutter_test.dart';
import 'package:transportation_app/features/trips/data/models/daily_reconciliation_model.dart';
import 'package:transportation_app/features/trips/data/models/payment_collection_model.dart';
import 'package:transportation_app/features/trips/data/models/payment_method_enum.dart';
import 'package:transportation_app/features/trips/data/models/payment_status_enum.dart';
import 'package:transportation_app/features/trips/data/models/shop_breakdown_model.dart';
import 'package:transportation_app/features/trips/data/models/shortage_reason_enum.dart';
import 'package:transportation_app/features/trips/data/models/tupperware_balance_model.dart';
import 'package:transportation_app/features/trips/data/models/tupperware_movement_model.dart';

void main() {
  group('PaymentMethodEnum Tests', () {
    test('cash payment method', () {
      expect(PaymentMethod.cash.label, 'Cash');
      expect(PaymentMethod.cash.labelAr, 'نقد');
      expect(PaymentMethod.cash.toApiString(), 'cash');
      expect(PaymentMethod.cash.requiresReference, false);
    });

    test('cliqNow payment method', () {
      expect(PaymentMethod.cliqNow.label, 'CliQ Now');
      expect(PaymentMethod.cliqNow.labelAr, 'كليق الآن');
      expect(PaymentMethod.cliqNow.toApiString(), 'cliq_now');
      expect(PaymentMethod.cliqNow.requiresReference, true);
    });

    test('cliqLater payment method', () {
      expect(PaymentMethod.cliqLater.label, 'CliQ Later');
      expect(PaymentMethod.cliqLater.labelAr, 'كليق لاحقاً');
      expect(PaymentMethod.cliqLater.toApiString(), 'cliq_later');
      expect(PaymentMethod.cliqLater.requiresReference, true);
    });

    test('fromString parses API strings correctly', () {
      expect(PaymentMethod.fromString('cash'), PaymentMethod.cash);
      expect(PaymentMethod.fromString('cliq_now'), PaymentMethod.cliqNow);
      expect(PaymentMethod.fromString('cliq_later'), PaymentMethod.cliqLater);
      expect(PaymentMethod.fromString('CASH'), PaymentMethod.cash);
      expect(PaymentMethod.fromString('invalid'), PaymentMethod.cash); // defaults to cash
    });
  });

  group('ShortageReasonEnum Tests', () {
    test('all shortage reasons have labels', () {
      expect(ShortageReason.customerAbsent.label, 'Customer Absent');
      expect(ShortageReason.insufficientFunds.label, 'Insufficient Funds');
      expect(ShortageReason.customerRefused.label, 'Customer Refused');
      expect(ShortageReason.partialDelivery.label, 'Partial Delivery');
      expect(ShortageReason.deliveryError.label, 'Delivery Error');
      expect(ShortageReason.other.label, 'Other');
    });

    test('fromString parses API strings correctly', () {
      expect(ShortageReason.fromString('customer_absent'),
          ShortageReason.customerAbsent);
      expect(ShortageReason.fromString('insufficient_funds'),
          ShortageReason.insufficientFunds);
      expect(
          ShortageReason.fromString('invalid'), ShortageReason.other); // defaults
    });
  });

  group('PaymentStatusEnum Tests', () {
    test('payment status labels', () {
      expect(PaymentStatus.pending.label, 'Pending');
      expect(PaymentStatus.partial.label, 'Partial');
      expect(PaymentStatus.full.label, 'Full');
    });

    test('fromString parses API strings', () {
      expect(PaymentStatus.fromString('pending'), PaymentStatus.pending);
      expect(PaymentStatus.fromString('partial'), PaymentStatus.partial);
      expect(PaymentStatus.fromString('full'), PaymentStatus.full);
    });

    test('toApiString converts to API format', () {
      expect(PaymentStatus.pending.toApiString(), 'pending');
      expect(PaymentStatus.partial.toApiString(), 'partial');
      expect(PaymentStatus.full.toApiString(), 'full');
    });
  });

  group('PaymentCollectionModel Tests', () {
    test('creates full payment collection', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 1000.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.full,
      );

      expect(model.isFullyCollected, true);
      expect(model.hasShortage, false);
      expect(model.amountExpected, 1000.0);
      expect(model.amountCollected, 1000.0);
    });

    test('creates partial payment collection with shortage', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-2',
        amountExpected: 1000.0,
        amountCollected: 750.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
        shortageAmount: 250.0,
        shortagePercentage: 25.0,
        shortageReason: ShortageReason.customerRefused,
      );

      expect(model.isFullyCollected, false);
      expect(model.hasShortage, true);
      expect(model.shortageAmount, 250.0);
      expect(model.shortageReason, ShortageReason.customerRefused);
    });

    test('fromJson parses API response', () {
      final json = {
        'id': 'pay-123',
        'destination_id': 'dest-1',
        'amount_expected': 1000.0,
        'amount_collected': 900.0,
        'payment_method': 'cash',
        'payment_status': 'partial',
        'shortage_amount': 100.0,
        'shortage_percentage': 10.0,
        'created_at': '2026-01-10T12:00:00Z',
      };

      final model = PaymentCollectionModel.fromJson(json);

      expect(model.id, 'pay-123');
      expect(model.destinationId, 'dest-1');
      expect(model.amountExpected, 1000.0);
      expect(model.amountCollected, 900.0);
      expect(model.paymentMethod, PaymentMethod.cash);
      expect(model.shortageAmount, 100.0);
    });

    test('toJson converts to API format', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 750.0,
        paymentMethod: PaymentMethod.cliqNow,
        paymentStatus: PaymentStatus.partial,
        cliqReference: 'REF-123',
        shortageReason: ShortageReason.insufficientFunds,
        notes: 'Customer low on funds',
      );

      final json = model.toJson();

      expect(json['destination_id'], 'dest-1');
      expect(json['amount_collected'], 750.0);
      expect(json['payment_method'], 'cliq_now');
      expect(json['cliq_reference'], 'REF-123');
      expect(json['shortage_reason'], 'insufficient_funds');
      expect(json['notes'], 'Customer low on funds');
    });

    test('copyWith creates modified copy', () {
      final original = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 900.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
      );

      final updated = original.copyWith(
        amountCollected: 1000.0,
        paymentStatus: PaymentStatus.full,
      );

      expect(updated.amountCollected, 1000.0);
      expect(updated.paymentStatus, PaymentStatus.full);
      expect(updated.amountExpected, 1000.0); // unchanged
      expect(updated.destinationId, 'dest-1'); // unchanged
    });

    test('mock factory creates test instance', () {
      final model = PaymentCollectionModel.mock(
        amountExpected: 500.0,
        amountCollected: 500.0,
        paymentMethod: PaymentMethod.cliqNow,
      );

      expect(model.amountExpected, 500.0);
      expect(model.amountCollected, 500.0);
      expect(model.paymentMethod, PaymentMethod.cliqNow);
      expect(model.isFullyCollected, true);
    });

    test('cliqNow payment requires reference', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 1000.0,
        paymentMethod: PaymentMethod.cliqNow,
        paymentStatus: PaymentStatus.full,
        cliqReference: 'REF-123',
      );

      expect(model.paymentMethod.requiresReference, true);
      expect(model.cliqReference, 'REF-123');
    });
  });

  group('TupperwareMovementModel Tests', () {
    test('creates tupperware movement', () {
      final model = TupperwareMovementModel(
        destinationId: 'dest-1',
        shopId: 'SHOP-001',
        productType: 'boxes',
        quantityPickedup: 20,
      );

      expect(model.destinationId, 'dest-1');
      expect(model.shopId, 'SHOP-001');
      expect(model.productType, 'boxes');
      expect(model.quantityPickedup, 20);
    });

    test('fromJson parses API response', () {
      final json = {
        'id': 'move-123',
        'destination_id': 'dest-1',
        'shop_id': 'SHOP-001',
        'product_type': 'boxes',
        'quantity_pickedup': 15,
        'moved_at': '2026-01-10T14:00:00Z',
        'created_at': '2026-01-10T14:00:00Z',
      };

      final model = TupperwareMovementModel.fromJson(json);

      expect(model.id, 'move-123');
      expect(model.quantityPickedup, 15);
      expect(model.productType, 'boxes');
    });

    test('toJson converts to API format', () {
      final model = TupperwareMovementModel(
        destinationId: 'dest-1',
        shopId: 'SHOP-001',
        productType: 'trays',
        quantityPickedup: 10,
      );

      final json = model.toJson();

      expect(json['destination_id'], 'dest-1');
      expect(json['shop_id'], 'SHOP-001');
      expect(json['product_type'], 'trays');
      expect(json['quantity_pickedup'], 10);
    });
  });

  group('TupperwareBalanceModel Tests', () {
    test('normal balance with green status', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 20,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.0,
      );

      expect(model.balanceStatus, 'Normal');
      expect(model.isCritical, false);
      expect(model.isWarning, false);
      expect(model.depositOwed, 100.0); // 20 × 5
    });

    test('warning balance with yellow status', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 35,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.0,
      );

      expect(model.balanceStatus, 'Warning');
      expect(model.isWarning, true);
      expect(model.isCritical, false);
    });

    test('critical balance with red status', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 55,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.0,
      );

      expect(model.balanceStatus, 'Critical');
      expect(model.isCritical, true);
    });

    test('canPickup validates quantity', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 20,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.0,
      );

      expect(model.canPickup(10), true);
      expect(model.canPickup(20), true);
      expect(model.canPickup(21), false);
      expect(model.canPickup(-1), false);
    });

    test('getBalanceAfterPickup calculates correctly', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 20,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.0,
      );

      expect(model.getBalanceAfterPickup(5), 15);
      expect(model.getBalanceAfterPickup(20), 0);
      expect(model.getBalanceAfterPickup(25), 20); // invalid, returns current
    });

    test('depositOwed calculation', () {
      final model = TupperwareBalanceModel(
        productType: 'boxes',
        currentBalance: 10,
        thresholdWarning: 30,
        thresholdCritical: 50,
        depositPerUnit: 5.5,
      );

      expect(model.depositOwed, 55.0); // 10 × 5.5
    });
  });

  group('ShopBreakdownModel Tests', () {
    test('creates fully collected shop breakdown', () {
      final model = ShopBreakdownModel(
        shopId: 'SHOP-001',
        shopName: 'Ahmad Shop',
        amountExpected: 1000.0,
        amountCollected: 1000.0,
        primaryPaymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.full,
      );

      expect(model.isFullyCollected, true);
      expect(model.collectionRate, 100.0);
      expect(model.shortagePercentage, 0.0);
    });

    test('creates partially collected shop breakdown', () {
      final model = ShopBreakdownModel(
        shopId: 'SHOP-002',
        shopName: 'Omar Store',
        amountExpected: 2000.0,
        amountCollected: 1500.0,
        primaryPaymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
        shortageAmount: 500.0,
      );

      expect(model.isPartiallyCollected, true);
      expect(model.collectionRate, 75.0);
      expect(model.shortagePercentage, 25.0);
      expect(model.hasShortage, true);
    });

    test('fromJson parses API response', () {
      final json = {
        'shop_id': 'SHOP-001',
        'shop_name': 'Test Shop',
        'amount_expected': 1000.0,
        'amount_collected': 900.0,
        'primary_payment_method': 'cash',
        'payment_status': 'partial',
        'created_at': '2026-01-10T12:00:00Z',
      };

      final model = ShopBreakdownModel.fromJson(json);

      expect(model.shopId, 'SHOP-001');
      expect(model.shopName, 'Test Shop');
      expect(model.collectionRate, 90.0);
      expect(model.hasShortage, true);
      expect(model.shortageAmount, 100.0);
    });

    test('zero expected amount', () {
      final model = ShopBreakdownModel(
        shopId: 'SHOP-999',
        shopName: 'Zero Shop',
        amountExpected: 0.0,
        amountCollected: 0.0,
        primaryPaymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.pending,
      );

      expect(model.collectionRate, 0.0);
      expect(model.shortagePercentage, 0.0);
    });
  });

  group('DailyReconciliationModel Tests', () {
    test('creates daily reconciliation', () {
      final breakdown = [
        ShopBreakdownModel.mock(
          shopId: 'SHOP-001',
          shopName: 'Shop A',
          amountExpected: 1500.0,
          amountCollected: 1500.0,
        ),
      ];

      final model = DailyReconciliationModel(
        reconciliationDate: DateTime(2026, 1, 10),
        totalExpected: 5000.0,
        totalCollected: 4800.0,
        totalCash: 3000.0,
        totalCliq: 1800.0,
        tripsCompleted: 5,
        deliveriesCompleted: 15,
        totalKmDriven: 45.5,
        shopBreakdown: breakdown,
      );

      expect(model.reconciliationDate, DateTime(2026, 1, 10));
      expect(model.collectionRate, 96.0); // 4800/5000 = 96%
      expect(model.totalShortage, 200.0);
      expect(model.shortagePercentage, 4.0);
    });

    test('collection percentage calculations', () {
      final model = DailyReconciliationModel.mock(
        totalExpected: 10000.0,
        totalCollected: 6000.0,
        totalCash: 4000.0,
        totalCliq: 2000.0,
      );

      expect(model.collectionRate, 60.0);
      expect(model.cashPercentage, 66.67);
      expect(model.cliqPercentage, 33.33);
    });

    test('fromJson parses API response', () {
      final json = {
        'id': 'recon-123',
        'reconciliation_date': '2026-01-10',
        'total_expected': 5000.0,
        'total_collected': 4800.0,
        'total_cash': 3000.0,
        'total_cliq': 1800.0,
        'trips_completed': 5,
        'deliveries_completed': 15,
        'total_km_driven': 45.5,
        'status': 'pending',
        'shop_breakdown': [
          {
            'shop_id': 'SHOP-001',
            'shop_name': 'Shop A',
            'amount_expected': 1500.0,
            'amount_collected': 1500.0,
            'primary_payment_method': 'cash',
            'payment_status': 'full',
          }
        ],
        'created_at': '2026-01-10T12:00:00Z',
      };

      final model = DailyReconciliationModel.fromJson(json);

      expect(model.id, 'recon-123');
      expect(model.totalCollected, 4800.0);
      expect(model.status, ReconciliationStatus.pending);
      expect(model.shopBreakdown.length, 1);
    });

    test('shop collection statistics', () {
      final breakdown = [
        ShopBreakdownModel.mock(
          shopId: 'SHOP-001',
          amountCollected: 1000.0,
          paymentStatus: PaymentStatus.full,
        ),
        ShopBreakdownModel.mock(
          shopId: 'SHOP-002',
          amountCollected: 500.0,
          paymentStatus: PaymentStatus.partial,
        ),
        ShopBreakdownModel.mock(
          shopId: 'SHOP-003',
          amountCollected: 0.0,
          paymentStatus: PaymentStatus.pending,
        ),
      ];

      final model = DailyReconciliationModel(
        reconciliationDate: DateTime.now(),
        totalExpected: 3000.0,
        totalCollected: 1500.0,
        totalCash: 1500.0,
        totalCliq: 0.0,
        tripsCompleted: 3,
        deliveriesCompleted: 3,
        totalKmDriven: 30.0,
        shopBreakdown: breakdown,
      );

      expect(model.shopsFullyCollected, 1);
      expect(model.shopsPartiallyCollected, 1);
      expect(model.shopsNotCollected, 1);
    });

    test('copyWith creates modified copy', () {
      final original = DailyReconciliationModel.mock();

      final updated = original.copyWith(
        status: ReconciliationStatus.submitted,
        notes: 'All collected',
      );

      expect(updated.status, ReconciliationStatus.submitted);
      expect(updated.notes, 'All collected');
      expect(updated.totalExpected, original.totalExpected);
    });

    test('mock factory creates test instance', () {
      final model = DailyReconciliationModel.mock(
        totalExpected: 10000.0,
        totalCollected: 9000.0,
      );

      expect(model.totalExpected, 10000.0);
      expect(model.totalCollected, 9000.0);
      expect(model.shopBreakdown.length, 3);
    });
  });

  group('Edge Cases and Validations', () {
    test('payment collection with cliq requires reference', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 1000.0,
        paymentMethod: PaymentMethod.cliqNow,
        paymentStatus: PaymentStatus.full,
        cliqReference: 'ABC123',
      );

      expect(model.cliqReference, 'ABC123');
      expect(model.paymentMethod.requiresReference, true);
    });

    test('shortage reason required for partial payment', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1000.0,
        amountCollected: 500.0,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
        shortageReason: ShortageReason.customerAbsent,
        shortageAmount: 500.0,
      );

      expect(model.shortageReason, ShortageReason.customerAbsent);
      expect(model.hasShortage, true);
    });

    test('reconciliation with zero collection', () {
      final model = DailyReconciliationModel(
        reconciliationDate: DateTime.now(),
        totalExpected: 5000.0,
        totalCollected: 0.0,
        totalCash: 0.0,
        totalCliq: 0.0,
        tripsCompleted: 0,
        deliveriesCompleted: 0,
        totalKmDriven: 0.0,
        shopBreakdown: [],
      );

      expect(model.collectionRate, 0.0);
      expect(model.isFullyCollected, false);
      expect(model.totalShortage, 5000.0);
    });

    test('decimal precision in amounts', () {
      final model = PaymentCollectionModel(
        destinationId: 'dest-1',
        amountExpected: 1234.56,
        amountCollected: 987.33,
        paymentMethod: PaymentMethod.cash,
        paymentStatus: PaymentStatus.partial,
        shortageAmount: 247.23,
        shortagePercentage: 20.04,
      );

      expect(model.amountExpected, 1234.56);
      expect(model.amountCollected, 987.33);
      expect(model.shortageAmount, 247.23);
    });
  });
}
