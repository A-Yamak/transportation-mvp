import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/tupperware_balance_model.dart';
import '../../providers/tupperware_providers.dart';

/// Dialog for collecting tupperware/containers at a destination
/// Shows balance per product type with color-coded warnings
class TupperwarePickupDialog extends ConsumerStatefulWidget {
  final String tripId;
  final String destinationId;
  final String shopId;
  final VoidCallback onSuccess;

  const TupperwarePickupDialog({
    Key? key,
    required this.tripId,
    required this.destinationId,
    required this.shopId,
    required this.onSuccess,
  }) : super(key: key);

  /// Show tupperware pickup dialog as bottom sheet
  static void show(
    BuildContext context, {
    required String tripId,
    required String destinationId,
    required String shopId,
    required VoidCallback onSuccess,
  }) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => TupperwarePickupDialog(
        tripId: tripId,
        destinationId: destinationId,
        shopId: shopId,
        onSuccess: onSuccess,
      ),
    );
  }

  @override
  ConsumerState<TupperwarePickupDialog> createState() =>
      _TupperwarePickupDialogState();
}

class _TupperwarePickupDialogState
    extends ConsumerState<TupperwarePickupDialog> {
  late TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _notesController = TextEditingController();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Fetch balance for the shop
    final balanceAsync = ref.watch(
      tupperwareBalanceProvider(widget.shopId),
    );

    final formState = ref.watch(tupperwarePickupFormProvider);
    final submissionState = ref.watch(tupperwareCollectionProvider);
    final isValid = ref.watch(tupperwareFormValidProvider);
    final totalPickup = ref.watch(tuppwareTotalPickupProvider);

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
              'Collect Tupperware',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            SizedBox(height: 20),

            // Balance list
            balanceAsync.when(
              loading: () => Center(child: CircularProgressIndicator()),
              error: (error, stack) => Container(
                padding: EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.withOpacity(0.1),
                  border: Border.all(color: Colors.red),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  'Failed to load balance: $error',
                  style: TextStyle(color: Colors.red),
                ),
              ),
              data: (balances) {
                // Initialize form from balance on first load
                if (formState.pickupQuantities.isEmpty) {
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    ref
                        .read(tupperwarePickupFormProvider.notifier)
                        .initializeFromBalance(balances);
                  });
                }

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ...balances.map((balance) {
                      final quantity = formState.getQuantity(balance.productType);
                      final newBalance = balance.currentBalance - quantity;

                      return _BalanceCard(
                        balance: balance,
                        quantity: quantity,
                        newBalance: newBalance,
                        onIncrement: () {
                          if (quantity < balance.currentBalance) {
                            ref
                                .read(tupperwarePickupFormProvider.notifier)
                                .incrementQuantity(balance.productType);
                          }
                        },
                        onDecrement: () {
                          ref
                              .read(tupperwarePickupFormProvider.notifier)
                              .decrementQuantity(balance.productType);
                        },
                        onQuantityChanged: (value) {
                          try {
                            final qty = int.parse(value);
                            if (qty <= balance.currentBalance) {
                              ref
                                  .read(tupperwarePickupFormProvider.notifier)
                                  .setQuantity(balance.productType, qty);
                            }
                          } catch (e) {
                            // Invalid input
                          }
                        },
                      );
                    }).toList(),
                    SizedBox(height: 20),
                  ],
                );
              },
            ),

            // Summary card
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
                    'Total to Pickup',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                  Text(
                    '$totalPickup items',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
            ),
            SizedBox(height: 20),

            // Optional notes
            Text(
              'Notes (Optional)',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            SizedBox(height: 8),
            TextField(
              controller: _notesController,
              maxLines: 2,
              decoration: InputDecoration(
                hintText: 'Add any notes about the pickup',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              onChanged: (value) {
                ref
                    .read(tupperwarePickupFormProvider.notifier)
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
                          .read(tupperwareCollectionProvider.notifier)
                          .submitPickup(
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
                  : Text('Confirm Pickup'),
            ),
            SizedBox(height: 16),
          ],
        ),
      ),
    );
  }
}

/// Card for each tupperware balance with +/- controls
class _BalanceCard extends StatefulWidget {
  final TupperwareBalanceModel balance;
  final int quantity;
  final int newBalance;
  final VoidCallback onIncrement;
  final VoidCallback onDecrement;
  final ValueChanged<String> onQuantityChanged;

  const _BalanceCard({
    Key? key,
    required this.balance,
    required this.quantity,
    required this.newBalance,
    required this.onIncrement,
    required this.onDecrement,
    required this.onQuantityChanged,
  }) : super(key: key);

  @override
  State<_BalanceCard> createState() => _BalanceCardState();
}

class _BalanceCardState extends State<_BalanceCard> {
  late TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.quantity.toString());
  }

  @override
  void didUpdateWidget(_BalanceCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.quantity != oldWidget.quantity) {
      _controller.text = widget.quantity.toString();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(12),
      margin: EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header with product type and warning
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                widget.balance.productType.replaceFirst(
                  widget.balance.productType[0],
                  widget.balance.productType[0].toUpperCase(),
                ),
                style: Theme.of(context).textTheme.titleSmall,
              ),
              Container(
                padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: widget.balance.balanceColor.withOpacity(0.2),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  widget.balance.balanceStatus,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: widget.balance.balanceColor,
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ),
            ],
          ),
          SizedBox(height: 8),

          // Balance information
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Current: ${widget.balance.currentBalance}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Text(
                'After: ${widget.newBalance}',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
            ],
          ),
          SizedBox(height: 12),

          // Quantity controls
          Row(
            children: [
              IconButton(
                onPressed: widget.onDecrement,
                icon: Icon(Icons.remove),
                iconSize: 20,
                constraints: BoxConstraints(minWidth: 40, minHeight: 40),
                padding: EdgeInsets.zero,
              ),
              Expanded(
                child: TextField(
                  controller: _controller,
                  textAlign: TextAlign.center,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(4),
                    ),
                    contentPadding: EdgeInsets.symmetric(vertical: 8),
                    isDense: true,
                  ),
                  onChanged: widget.onQuantityChanged,
                ),
              ),
              IconButton(
                onPressed: widget.onIncrement,
                icon: Icon(Icons.add),
                iconSize: 20,
                constraints: BoxConstraints(minWidth: 40, minHeight: 40),
                padding: EdgeInsets.zero,
              ),
            ],
          ),

          // Deposit info
          if (widget.balance.depositPerUnit > 0) ...[
            SizedBox(height: 8),
            Text(
              'Deposit owed: JOD ${widget.balance.depositOwed.toStringAsFixed(2)} (${widget.balance.currentBalance} × ${widget.balance.depositPerUnit})',
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ],
      ),
    );
  }
}
