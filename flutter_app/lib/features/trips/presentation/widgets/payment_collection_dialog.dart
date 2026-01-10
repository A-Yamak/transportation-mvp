import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/destination_model.dart';
import '../../data/models/payment_method_enum.dart';
import '../../data/models/shortage_reason_enum.dart';
import '../../providers/payment_collection_provider.dart';

/// Dialog for collecting payment at a destination
/// Shows payment method selection, amount input, and shortage handling
class PaymentCollectionDialog extends ConsumerStatefulWidget {
  final String tripId;
  final String destinationId;
  final DestinationModel destination;
  final VoidCallback onSuccess;

  const PaymentCollectionDialog({
    Key? key,
    required this.tripId,
    required this.destinationId,
    required this.destination,
    required this.onSuccess,
  }) : super(key: key);

  /// Show payment collection dialog as bottom sheet
  static void show(
    BuildContext context, {
    required String tripId,
    required String destinationId,
    required DestinationModel destination,
    required VoidCallback onSuccess,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => PaymentCollectionDialog(
        tripId: tripId,
        destinationId: destinationId,
        destination: destination,
        onSuccess: onSuccess,
      ),
    );
  }

  @override
  ConsumerState<PaymentCollectionDialog> createState() =>
      _PaymentCollectionDialogState();
}

class _PaymentCollectionDialogState
    extends ConsumerState<PaymentCollectionDialog> {
  late TextEditingController _amountController;
  late TextEditingController _cliqRefController;
  late TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController();
    _cliqRefController = TextEditingController();
    _notesController = TextEditingController();

    // Initialize form with expected amount
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref
          .read(paymentCollectionFormProvider.notifier)
          .initialize(widget.destination.amountToCollect ?? 0.0);
      _amountController.text =
          (widget.destination.amountToCollect ?? 0.0).toStringAsFixed(2);
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    _cliqRefController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final formState = ref.watch(paymentCollectionFormProvider);
    final submissionState = ref.watch(paymentCollectionProvider);
    final isValid = ref.watch(paymentFormValidProvider);
    final cliqRequired = ref.watch(cliqReferenceRequiredProvider);
    final shortageRequired = ref.watch(shortageReasonRequiredProvider);

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
        left: 16,
        right: 16,
        top: 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header
            Text(
              'Collect Payment',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            SizedBox(height: 8),
            Text(
              '${widget.destination.address}',
              style: Theme.of(context).textTheme.bodySmall,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            SizedBox(height: 20),

            // Expected amount banner
            Container(
              padding: EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Expected Amount',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  Text(
                    'JOD ${formState.amountExpected.toStringAsFixed(2)}',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
            ),
            SizedBox(height: 20),

            // Payment method selection
            Text(
              'Payment Method',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            SizedBox(height: 12),
            SegmentedButton<PaymentMethod>(
              segments: const <ButtonSegment<PaymentMethod>>[
                ButtonSegment<PaymentMethod>(
                  value: PaymentMethod.cash,
                  label: Text('Cash'),
                ),
                ButtonSegment<PaymentMethod>(
                  value: PaymentMethod.cliqNow,
                  label: Text('CliQ Now'),
                ),
                ButtonSegment<PaymentMethod>(
                  value: PaymentMethod.cliqLater,
                  label: Text('CliQ Later'),
                ),
              ],
              selected: <PaymentMethod>{formState.paymentMethod},
              onSelectionChanged: (Set<PaymentMethod> newSelection) {
                ref
                    .read(paymentCollectionFormProvider.notifier)
                    .setPaymentMethod(newSelection.first);
              },
            ),
            SizedBox(height: 20),

            // Amount input
            Text(
              'Amount Collected',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            SizedBox(height: 8),
            TextField(
              controller: _amountController,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                hintText: '0.00',
                prefixText: 'JOD ',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              onChanged: (value) {
                try {
                  final amount = double.parse(value);
                  ref
                      .read(paymentCollectionFormProvider.notifier)
                      .setAmountCollected(amount);
                } catch (e) {
                  // Invalid input, ignore
                }
              },
            ),
            SizedBox(height: 12),

            // Shortage indicator
            if (formState.hasShortage)
              Container(
                padding: EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.1),
                  border: Border.all(color: Colors.orange),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Shortage',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    Text(
                      'JOD ${formState.shortageAmount?.toStringAsFixed(2)} (${formState.shortagePercentageDisplay}%)',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Colors.orange,
                          ),
                    ),
                  ],
                ),
              ),
            SizedBox(height: 20),

            // CliQ reference (conditional)
            if (cliqRequired) ...[
              Text(
                'CliQ Reference',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              SizedBox(height: 8),
              TextField(
                controller: _cliqRefController,
                decoration: InputDecoration(
                  hintText: 'Enter reference number',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                onChanged: (value) {
                  ref
                      .read(paymentCollectionFormProvider.notifier)
                      .setCliqReference(value);
                },
              ),
              SizedBox(height: 20),
            ],

            // Shortage reason (conditional)
            if (shortageRequired) ...[
              Text(
                'Shortage Reason',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              SizedBox(height: 8),
              DropdownButtonFormField<ShortageReason>(
                decoration: InputDecoration(
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                  contentPadding: EdgeInsets.symmetric(horizontal: 12),
                ),
                hint: Text('Select reason'),
                items: ShortageReason.values
                    .map((reason) => DropdownMenuItem(
                          value: reason,
                          child: Text(reason.label),
                        ))
                    .toList(),
                onChanged: (reason) {
                  if (reason != null) {
                    ref
                        .read(paymentCollectionFormProvider.notifier)
                        .setShortageReason(reason.toApiString());
                  }
                },
              ),
              SizedBox(height: 20),
            ],

            // Optional notes
            Text(
              'Notes (Optional)',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            SizedBox(height: 8),
            TextField(
              controller: _notesController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Add any notes about this payment',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              onChanged: (value) {
                ref
                    .read(paymentCollectionFormProvider.notifier)
                    .setNotes(value.isEmpty ? null : value);
              },
            ),
            SizedBox(height: 24),

            // Error message
            if (submissionState.hasError)
              Container(
                padding: EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.withOpacity(0.1),
                  border: Border.all(color: Colors.red),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  submissionState.error.toString(),
                  style: TextStyle(color: Colors.red),
                ),
              ),
            SizedBox(height: 16),

            // Submit button
            ElevatedButton(
              onPressed: (isValid && submissionState.isLoading == false)
                  ? () async {
                      await ref
                          .read(paymentCollectionProvider.notifier)
                          .submitPayment(
                            widget.tripId,
                            widget.destinationId,
                            formState,
                          );

                      if (mounted && !submissionState.hasError) {
                        Navigator.pop(context);
                        widget.onSuccess();
                      }
                    }
                  : null,
              child: submissionState.isLoading
                  ? SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : Text('Confirm Payment'),
            ),
            SizedBox(height: 16),
          ],
        ),
      ),
    );
  }
}
