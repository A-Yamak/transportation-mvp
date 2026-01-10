import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/reconciliation_provider.dart';
import '../../data/models/daily_reconciliation_model.dart';
import '../../data/models/payment_status_enum.dart';

/// Screen for daily reconciliation summary and submission
class DailyReconciliationScreen extends ConsumerStatefulWidget {
  const DailyReconciliationScreen({Key? key}) : super(key: key);

  @override
  ConsumerState<DailyReconciliationScreen> createState() =>
      _DailyReconciliationScreenState();
}

class _DailyReconciliationScreenState
    extends ConsumerState<DailyReconciliationScreen> {
  late TextEditingController _notesController;

  @override
  void initState() {
    super.initState();
    _notesController = TextEditingController();

    // Generate reconciliation when screen loads
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref
          .read(reconciliationProvider.notifier)
          .generateReconciliation();
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reconciliationAsync = ref.watch(reconciliationProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text('Daily Reconciliation'),
      ),
      body: reconciliationAsync.when(
        loading: () => Center(child: CircularProgressIndicator()),
        error: (error, stack) => _ErrorWidget(error: error),
        data: (reconciliation) {
          if (reconciliation == null) {
            return Center(
              child: Text('No reconciliation data available'),
            );
          }

          return _ReconciliationContent(
            reconciliation: reconciliation,
            notesController: _notesController,
            onSubmit: () async {
              await ref
                  .read(reconciliationProvider.notifier)
                  .submitReconciliation(
                    reconciliation.id,
                    notes: _notesController.text.isEmpty
                        ? null
                        : _notesController.text,
                  );

              if (mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Reconciliation submitted')),
                );
                Navigator.pop(context);
              }
            },
          );
        },
      ),
    );
  }
}

class _ReconciliationContent extends ConsumerWidget {
  final DailyReconciliationModel reconciliation;
  final TextEditingController notesController;
  final VoidCallback onSubmit;

  const _ReconciliationContent({
    Key? key,
    required this.reconciliation,
    required this.notesController,
    required this.onSubmit,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final submissionState = ref.watch(reconciliationProvider);
    final canSubmit =
        ref.watch(canSubmitReconciliationProvider) &&
            submissionState.isLoading == false;

    return SingleChildScrollView(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Status badge
          Container(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            decoration: BoxDecoration(
              color: reconciliation.status == ReconciliationStatus.pending
                  ? Colors.orange.withOpacity(0.2)
                  : Colors.green.withOpacity(0.2),
              border: Border.all(
                color: reconciliation.status == ReconciliationStatus.pending
                    ? Colors.orange
                    : Colors.green,
              ),
              borderRadius: BorderRadius.circular(4),
            ),
            child: Text(
              reconciliation.status.label,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: reconciliation.status == ReconciliationStatus.pending
                    ? Colors.orange
                    : Colors.green,
              ),
            ),
          ),
          SizedBox(height: 20),

          // Summary cards (2x2 grid)
          GridView.count(
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            shrinkWrap: true,
            physics: NeverScrollableScrollPhysics(),
            children: [
              _SummaryCard(
                title: 'Collection Rate',
                value: '${reconciliation.collectionRate.toStringAsFixed(1)}%',
                subtitle:
                    '${reconciliation.totalCollected.toStringAsFixed(2)} / ${reconciliation.totalExpected.toStringAsFixed(2)}',
              ),
              _SummaryCard(
                title: 'Total Collected',
                value: 'JOD ${reconciliation.totalCollected.toStringAsFixed(2)}',
                subtitle: '${reconciliation.shopsFullyCollected} full, ${reconciliation.shopsPartiallyCollected} partial',
              ),
              _SummaryCard(
                title: 'Cash vs CliQ',
                value: '${reconciliation.cashPercentage.toStringAsFixed(0)}% / ${reconciliation.cliqPercentage.toStringAsFixed(0)}%',
                subtitle:
                    'JOD ${reconciliation.totalCash.toStringAsFixed(2)} / JOD ${reconciliation.totalCliq.toStringAsFixed(2)}',
              ),
              _SummaryCard(
                title: 'Trips Completed',
                value: '${reconciliation.tripsCompleted}',
                subtitle: '${reconciliation.deliveriesCompleted} deliveries, ${reconciliation.totalKmDriven.toStringAsFixed(1)} km',
              ),
            ],
          ),
          SizedBox(height: 24),

          // Shop breakdown section
          Text(
            'Shop Breakdown',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          SizedBox(height: 12),
          ...reconciliation.shopBreakdown.asMap().entries.map((entry) {
            final shop = entry.value;
            return _ShopBreakdownTile(
              shop: shop,
              index: entry.key,
            );
          }).toList(),
          SizedBox(height: 24),

          // Optional notes
          Text(
            'Additional Notes (Optional)',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          SizedBox(height: 8),
          TextField(
            controller: notesController,
            maxLines: 3,
            decoration: InputDecoration(
              hintText: 'Add any notes about this reconciliation',
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
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
            onPressed: canSubmit ? onSubmit : null,
            child: submissionState.isLoading
                ? SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : Text('Submit Reconciliation'),
          ),
          SizedBox(height: 16),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final String title;
  final String value;
  final String subtitle;

  const _SummaryCard({
    Key? key,
    required this.title,
    required this.value,
    required this.subtitle,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.grey.shade600,
                ),
          ),
          SizedBox(height: 4),
          Text(
            value,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
          ),
          SizedBox(height: 4),
          Text(
            subtitle,
            style: Theme.of(context).textTheme.labelSmall,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _ShopBreakdownTile extends StatefulWidget {
  final dynamic shop;
  final int index;

  const _ShopBreakdownTile({
    Key? key,
    required this.shop,
    required this.index,
  }) : super(key: key);

  @override
  State<_ShopBreakdownTile> createState() => _ShopBreakdownTileState();
}

class _ShopBreakdownTileState extends State<_ShopBreakdownTile> {
  bool _isExpanded = false;

  @override
  Widget build(BuildContext context) {
    final shop = widget.shop;

    return Container(
      margin: EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        border: Border.all(color: Colors.grey.shade300),
        borderRadius: BorderRadius.circular(8),
      ),
      child: ExpansionTile(
        onExpansionChanged: (expanded) {
          setState(() => _isExpanded = expanded);
        },
        leading: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: shop.isFullyCollected
                ? Colors.green.withOpacity(0.2)
                : shop.isPartiallyCollected
                    ? Colors.orange.withOpacity(0.2)
                    : Colors.grey.withOpacity(0.2),
            borderRadius: BorderRadius.circular(4),
          ),
          child: Center(
            child: Text(
              '${widget.index + 1}',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
          ),
        ),
        title: Text(
          shop.shopName,
          style: Theme.of(context).textTheme.titleSmall,
        ),
        subtitle: Text(
          'JOD ${shop.amountCollected.toStringAsFixed(2)} / ${shop.amountExpected.toStringAsFixed(2)} (${shop.collectionRate.toStringAsFixed(1)}%)',
          style: Theme.of(context).textTheme.labelSmall,
        ),
        children: [
          Padding(
            padding: EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _DetailRow(
                  label: 'Expected Amount',
                  value: 'JOD ${shop.amountExpected.toStringAsFixed(2)}',
                ),
                SizedBox(height: 8),
                _DetailRow(
                  label: 'Collected Amount',
                  value: 'JOD ${shop.amountCollected.toStringAsFixed(2)}',
                ),
                SizedBox(height: 8),
                _DetailRow(
                  label: 'Payment Method',
                  value: shop.primaryPaymentMethod.label,
                ),
                SizedBox(height: 8),
                _DetailRow(
                  label: 'Status',
                  value: shop.paymentStatus.label,
                ),
                if (shop.hasShortage) ...[
                  SizedBox(height: 8),
                  Container(
                    padding: EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.orange.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('Shortage'),
                        Text(
                          'JOD ${shop.shortageAmount?.toStringAsFixed(2)} (${shop.shortagePercentage.toStringAsFixed(1)}%)',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: Colors.orange,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow({
    Key? key,
    required this.label,
    required this.value,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
        Text(
          value,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
      ],
    );
  }
}

class _ErrorWidget extends StatelessWidget {
  final Object error;

  const _ErrorWidget({Key? key, required this.error}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.red),
            SizedBox(height: 16),
            Text(
              'Failed to generate reconciliation',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            SizedBox(height: 8),
            Text(
              error.toString(),
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}
