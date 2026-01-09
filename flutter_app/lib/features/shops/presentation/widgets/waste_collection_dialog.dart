import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/shop_model.dart';
import '../../data/models/waste_item_model.dart';
import '../../providers/shops_provider.dart';

/// Dialog widget for logging waste collection at a shop
class WasteCollectionDialog extends ConsumerStatefulWidget {
  final ShopModel shop;
  final String tripId;
  final VoidCallback? onSuccess;

  const WasteCollectionDialog({
    Key? key,
    required this.shop,
    required this.tripId,
    this.onSuccess,
  }) : super(key: key);

  @override
  ConsumerState<WasteCollectionDialog> createState() =>
      _WasteCollectionDialogState();
}

class _WasteCollectionDialogState extends ConsumerState<WasteCollectionDialog> {
  late TextEditingController _driverNotesController;
  late FocusNode _driverNotesFocus;

  @override
  void initState() {
    super.initState();
    _driverNotesController = TextEditingController();
    _driverNotesFocus = FocusNode();

    // Initialize waste items from expected waste
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (widget.shop.expectedWaste != null) {
        ref
            .read(wasteItemsEditingProvider.notifier)
            .initialize(widget.shop.expectedWaste!.items);
      }
    });
  }

  @override
  void dispose() {
    _driverNotesController.dispose();
    _driverNotesFocus.dispose();
    super.dispose();
  }

  void _submitWaste() async {
    final items = ref.read(wasteItemsEditingProvider);
    final driverNotes = _driverNotesController.text.trim();

    // Validate all items have valid waste quantities
    if (!ref.read(wasteItemsEditingProvider.notifier).allItemsValid) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content:
                Text('Waste quantity cannot exceed delivered quantity'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return;
    }

    // Log waste via repository
    await ref.read(wasteCollectionProvider.notifier).logWaste(
          widget.tripId,
          widget.shop.id,
          items,
          driverNotes: driverNotes.isNotEmpty ? driverNotes : null,
        );

    // Check result and handle
    if (mounted) {
      final state = ref.read(wasteCollectionProvider);
      state.whenData((collection) {
        if (collection != null) {
          // Success
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content:
                  Text('Waste logged for ${widget.shop.name}'),
              backgroundColor: Colors.green,
            ),
          );
          widget.onSuccess?.call();
          Navigator.of(context).pop(true);
        }
      }).whenError((error, stack) {
        // Error
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $error'),
            backgroundColor: Colors.red,
          ),
        );
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = ref.watch(wasteItemsEditingProvider);
    final wastePercentage = ref.watch(wastePercentageProvider);
    final wasteState = ref.watch(wasteCollectionProvider);
    final isLoading = wasteState.isLoading;

    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.orange[100],
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(12),
                  topRight: Radius.circular(12),
                ),
                border: Border(
                  bottom: BorderSide(color: Colors.orange[300]!),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Log Waste Collection',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.bold),
                        ),
                        Text(
                          widget.shop.name,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: Colors.grey[700]),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                    splashRadius: 20,
                  ),
                ],
              ),
            ),

            // Items list
            if (items.isNotEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${items.length} Item(s)',
                      style: Theme.of(context).textTheme.labelMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 12),
                    ...items.map((item) => _WasteItemRow(
                          key: ValueKey(item.id),
                          item: item,
                          onChanged: (quantity, notes) {
                            ref
                                .read(wasteItemsEditingProvider.notifier)
                                .updateWasteQuantity(item.id, quantity);
                            if (notes != null) {
                              ref
                                  .read(wasteItemsEditingProvider.notifier)
                                  .updateNotes(item.id, notes);
                            }
                          },
                        )),
                  ],
                ),
              )
            else
              Padding(
                padding: const EdgeInsets.all(16),
                child: Center(
                  child: Text(
                    'No items to collect',
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ),

            // Waste summary
            if (items.isNotEmpty)
              Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.blue[50],
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue[200]!),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _SummaryItem(
                      label: 'Waste',
                      value:
                          '${ref.read(wasteItemsEditingProvider.notifier).totalWastePieces}',
                      color: Colors.orange,
                    ),
                    _SummaryItem(
                      label: 'Sold',
                      value:
                          '${ref.read(wasteItemsEditingProvider.notifier).totalSoldPieces}',
                      color: Colors.green,
                    ),
                    _SummaryItem(
                      label: 'Waste %',
                      value: '${wastePercentage.toStringAsFixed(1)}%',
                      color: Colors.purple,
                    ),
                  ],
                ),
              ),

            // Driver notes
            if (items.isNotEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: TextField(
                  controller: _driverNotesController,
                  focusNode: _driverNotesFocus,
                  maxLines: 3,
                  maxLength: 500,
                  decoration: InputDecoration(
                    labelText: 'Additional Notes (Optional)',
                    hintText: 'Any observations about waste items...',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                    contentPadding: const EdgeInsets.all(12),
                  ),
                ),
              ),

            // Error display
            if (wasteState.hasError)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red[50],
                    border: Border.all(color: Colors.red[200]!),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.error, color: Colors.red[700], size: 20),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          wasteState.error.toString(),
                          style: TextStyle(color: Colors.red[700]),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

            // Action buttons
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: isLoading
                          ? null
                          : () {
                              ref
                                  .read(wasteItemsEditingProvider.notifier)
                                  .reset();
                              _driverNotesController.clear();
                            },
                      child: const Text('Reset'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: isLoading || items.isEmpty ? null : _submitWaste,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.green,
                        foregroundColor: Colors.white,
                      ),
                      child: isLoading
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor:
                                    AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : const Text('Submit Waste'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Widget for editing individual waste item quantities
class _WasteItemRow extends ConsumerWidget {
  final WasteItemModel item;
  final Function(int quantity, String? notes) onChanged;

  const _WasteItemRow({
    Key? key,
    required this.item,
    required this.onChanged,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wasteItems = ref.watch(wasteItemsEditingProvider);
    final currentItem = wasteItems.firstWhere(
      (i) => i.id == item.id,
      orElse: () => item,
    );

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey[300]!),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Product name and expiry status
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      currentItem.productName,
                      style: Theme.of(context).textTheme.bodyMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (currentItem.isExpired)
                      Text(
                        '⚠️ Expired ${currentItem.daysExpired} days ago',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.red[700],
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.blue[50],
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Del: ${currentItem.quantityDelivered}',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: Colors.blue[700],
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Waste quantity controls
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text(
                'Waste:',
                style: Theme.of(context).textTheme.labelMedium,
              ),
              const SizedBox(width: 12),
              // Decrement button
              SizedBox(
                width: 40,
                height: 40,
                child: IconButton(
                  icon: const Icon(Icons.remove),
                  onPressed: currentItem.piecesWaste > 0
                      ? () => ref
                          .read(wasteItemsEditingProvider.notifier)
                          .updateWasteQuantity(
                              currentItem.id, currentItem.piecesWaste - 1)
                      : null,
                  padding: EdgeInsets.zero,
                ),
              ),
              const SizedBox(width: 8),
              // Quantity display
              SizedBox(
                width: 60,
                child: TextField(
                  textAlign: TextAlign.center,
                  controller: TextEditingController(
                    text: currentItem.piecesWaste.toString(),
                  ),
                  onChanged: (value) {
                    final parsed = int.tryParse(value) ?? 0;
                    if (parsed >= 0 &&
                        parsed <= currentItem.quantityDelivered) {
                      ref
                          .read(wasteItemsEditingProvider.notifier)
                          .updateWasteQuantity(currentItem.id, parsed);
                    }
                  },
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(vertical: 8),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              // Increment button
              SizedBox(
                width: 40,
                height: 40,
                child: IconButton(
                  icon: const Icon(Icons.add),
                  onPressed: currentItem.piecesWaste <
                          currentItem.quantityDelivered
                      ? () => ref
                          .read(wasteItemsEditingProvider.notifier)
                          .updateWasteQuantity(
                              currentItem.id, currentItem.piecesWaste + 1)
                      : null,
                  padding: EdgeInsets.zero,
                ),
              ),
              const Spacer(),
              // Sold quantity display
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Sold: ${currentItem.piecesSold}',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: Colors.green[700],
                        fontWeight: FontWeight.bold,
                      ),
                ),
              ),
            ],
          ),
          if (!currentItem.isValidWasteQuantity)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                'Error: Waste exceeds delivered quantity',
                style: TextStyle(
                  fontSize: 12,
                  color: Colors.red[700],
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Widget for displaying waste summary statistics
class _SummaryItem extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _SummaryItem({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall,
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: color,
                fontWeight: FontWeight.bold,
              ),
        ),
      ],
    );
  }
}
