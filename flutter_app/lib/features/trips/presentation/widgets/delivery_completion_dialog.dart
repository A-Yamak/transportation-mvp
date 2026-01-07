import 'package:flutter/material.dart';
import '../../data/models/delivery_item_model.dart';
import '../../data/models/destination_model.dart';

/// Result of delivery completion dialog
class DeliveryCompletionResult {
  final String? recipientName;
  final String? notes;
  final List<DeliveryItemModel> items;

  DeliveryCompletionResult({
    this.recipientName,
    this.notes,
    required this.items,
  });
}

/// Dialog for completing delivery with optional item-level tracking
class DeliveryCompletionDialog extends StatefulWidget {
  final DestinationModel destination;
  final void Function(DeliveryCompletionResult result) onComplete;

  const DeliveryCompletionDialog({
    super.key,
    required this.destination,
    required this.onComplete,
  });

  /// Show as bottom sheet
  static Future<void> show({
    required BuildContext context,
    required DestinationModel destination,
    required void Function(DeliveryCompletionResult result) onComplete,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => DeliveryCompletionDialog(
        destination: destination,
        onComplete: onComplete,
      ),
    );
  }

  @override
  State<DeliveryCompletionDialog> createState() => _DeliveryCompletionDialogState();
}

class _DeliveryCompletionDialogState extends State<DeliveryCompletionDialog> {
  final _recipientController = TextEditingController();
  final _notesController = TextEditingController();
  late List<DeliveryItemModel> _items;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    // Initialize items from destination, or create mock for MVP demo
    _items = widget.destination.items.isNotEmpty
        ? widget.destination.items.map((item) => item.copyWith()).toList()
        : [];
  }

  @override
  void dispose() {
    _recipientController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
        left: 16,
        right: 16,
        top: 16,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Header
            Row(
              children: [
                const Icon(Icons.check_circle, color: Colors.green, size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Complete Delivery',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        widget.destination.address,
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const Divider(),

            // Recipient name
            TextField(
              controller: _recipientController,
              decoration: const InputDecoration(
                labelText: 'Recipient Name (Optional)',
                hintText: 'Name of person who received delivery',
                prefixIcon: Icon(Icons.person),
              ),
            ),
            const SizedBox(height: 16),

            // Items section (if items exist)
            if (_items.isNotEmpty) ...[
              const Text(
                'Items',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 8),
              ...List.generate(_items.length, (index) {
                return _ItemQuantityEditor(
                  item: _items[index],
                  onChanged: (updatedItem) {
                    setState(() {
                      _items[index] = updatedItem;
                    });
                  },
                );
              }),
              const SizedBox(height: 16),
            ],

            // Notes
            TextField(
              controller: _notesController,
              decoration: const InputDecoration(
                labelText: 'Notes (Optional)',
                hintText: 'Any additional notes',
                prefixIcon: Icon(Icons.note),
              ),
              maxLines: 2,
            ),
            const SizedBox(height: 24),

            // Complete button
            ElevatedButton.icon(
              onPressed: _isLoading ? null : _handleComplete,
              icon: _isLoading
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.check),
              label: Text(_isLoading ? 'Completing...' : 'Complete Delivery'),
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.green,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
              ),
            ),
            const SizedBox(height: 16),
          ],
        ),
      ),
    );
  }

  void _handleComplete() {
    setState(() => _isLoading = true);

    final result = DeliveryCompletionResult(
      recipientName: _recipientController.text.isEmpty ? null : _recipientController.text,
      notes: _notesController.text.isEmpty ? null : _notesController.text,
      items: _items,
    );

    Navigator.pop(context);
    widget.onComplete(result);
  }
}

/// Widget for editing item quantity and discrepancy reason
class _ItemQuantityEditor extends StatefulWidget {
  final DeliveryItemModel item;
  final void Function(DeliveryItemModel updatedItem) onChanged;

  const _ItemQuantityEditor({
    required this.item,
    required this.onChanged,
  });

  @override
  State<_ItemQuantityEditor> createState() => _ItemQuantityEditorState();
}

class _ItemQuantityEditorState extends State<_ItemQuantityEditor> {
  late TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _notesController = TextEditingController(text: widget.item.notes);
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasDiscrepancy = widget.item.hasDiscrepancy;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Item name and expected quantity
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        widget.item.name ?? 'Item ${widget.item.orderItemId}',
                        style: const TextStyle(fontWeight: FontWeight.w500),
                      ),
                      Text(
                        'Expected: ${widget.item.quantityOrdered}',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                // Quantity controls
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.remove_circle_outline),
                      onPressed: widget.item.quantityDelivered > 0
                          ? () => _updateQuantity(-1)
                          : null,
                      color: Colors.red,
                    ),
                    Container(
                      width: 50,
                      alignment: Alignment.center,
                      child: Text(
                        '${widget.item.quantityDelivered}',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: hasDiscrepancy ? Colors.orange : Colors.green,
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.add_circle_outline),
                      onPressed: () => _updateQuantity(1),
                      color: Colors.green,
                    ),
                  ],
                ),
              ],
            ),

            // Discrepancy reason (if quantity is less than ordered)
            if (hasDiscrepancy) ...[
              const SizedBox(height: 8),
              DropdownButtonFormField<ItemDiscrepancyReason>(
                value: widget.item.discrepancyReason,
                decoration: const InputDecoration(
                  labelText: 'Reason for shortage',
                  isDense: true,
                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                ),
                items: ItemDiscrepancyReason.values.map((reason) {
                  return DropdownMenuItem(
                    value: reason,
                    child: Text(reason.labelEn),
                  );
                }).toList(),
                onChanged: (reason) {
                  widget.onChanged(widget.item.copyWith(discrepancyReason: reason));
                },
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _notesController,
                decoration: const InputDecoration(
                  labelText: 'Notes (Optional)',
                  isDense: true,
                ),
                onChanged: (value) {
                  widget.onChanged(widget.item.copyWith(notes: value.isEmpty ? null : value));
                },
              ),
            ],
          ],
        ),
      ),
    );
  }

  void _updateQuantity(int delta) {
    final newQuantity = widget.item.quantityDelivered + delta;
    if (newQuantity >= 0) {
      widget.onChanged(widget.item.copyWith(
        quantityDelivered: newQuantity,
        // Clear discrepancy reason if fully delivered
        discrepancyReason: newQuantity >= widget.item.quantityOrdered
            ? null
            : widget.item.discrepancyReason,
      ));
    }
  }
}
