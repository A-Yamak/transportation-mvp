import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/shop_model.dart';
import '../../providers/shops_provider.dart';

/// Card widget displaying shop information with delivery and waste status
class ShopCard extends ConsumerWidget {
  final ShopModel shop;
  final VoidCallback? onTap;
  final VoidCallback? onWasteTap;
  final VoidCallback? onNavigateTap;

  const ShopCard({
    Key? key,
    required this.shop,
    this.onTap,
    this.onWasteTap,
    this.onNavigateTap,
  }) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDarkMode = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDarkMode ? Colors.grey[900] : Colors.white;
    final textColor = isDarkMode ? Colors.white : Colors.black;
    final subtitleColor = isDarkMode ? Colors.grey[400] : Colors.grey[600];

    return Card(
      elevation: 2,
      color: cardColor,
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Shop header with sequence number and name
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          shop.displayLabel,
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                                color: textColor,
                              ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          shop.address,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: subtitleColor,
                              ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  if (shop.contactPhone != null)
                    Tooltip(
                      message: shop.contactPhone ?? 'No phone',
                      child: Icon(
                        Icons.phone,
                        color: Colors.blue[600],
                        size: 24,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 12),

              // Delivery status section (if applicable)
              if (!shop.hasWaste)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(
                    children: [
                      Icon(
                        Icons.check_circle,
                        color: Colors.green[600],
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'No pending waste',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.green[600],
                              fontWeight: FontWeight.w500,
                            ),
                      ),
                    ],
                  ),
                ),

              // Waste section
              if (shop.hasWaste) ...[
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.orange[50],
                    border: Border.all(color: Colors.orange[200]!),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Icon(
                            Icons.warning_amber,
                            color: Colors.orange[700],
                            size: 20,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Waste Expected',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(
                                        fontWeight: FontWeight.bold,
                                        color: Colors.orange[900],
                                      ),
                                ),
                                Text(
                                  '${shop.wasteSummaryItemsCount ?? shop.expectedWaste?.itemsCount ?? 0} items',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(
                                        color: Colors.orange[700],
                                      ),
                                ),
                              ],
                            ),
                          ),
                          if (shop.isWasteCollected ?? shop.expectedWaste?.isCollected ?? false)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                                vertical: 4,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.green[100],
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                'Collected',
                                style: Theme.of(context)
                                    .textTheme
                                    .labelSmall
                                    ?.copyWith(
                                      color: Colors.green[700],
                                      fontWeight: FontWeight.bold,
                                    ),
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 8),

                      // Waste summary
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            'Delivered: ${shop.wasteSummaryTotalDelivered ?? shop.expectedWaste?.totalDeliveredPieces ?? 0}',
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                          Text(
                            'Waste: ${shop.wasteSummaryTotalWaste ?? shop.expectedWaste?.totalWastePieces ?? 0}',
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                          Text(
                            'Sold: ${shop.wasteSummaryTotalSold ?? shop.expectedWaste?.totalSoldPieces ?? 0}',
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                        ],
                      ),

                      // Expired items warning
                      if ((shop.wasteSummaryExpiredCount ?? shop.expectedWaste?.expiredItemsCount ?? 0) > 0)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text(
                            '⚠️ ${shop.wasteSummaryExpiredCount ?? shop.expectedWaste?.expiredItemsCount ?? 0} expired item(s)',
                            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                  color: Colors.red[700],
                                  fontWeight: FontWeight.bold,
                                ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],

              // Action buttons
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  if (shop.contactPhone != null)
                    Expanded(
                      child: _ActionButton(
                        icon: Icons.navigation,
                        label: 'Navigate',
                        onPressed: onNavigateTap,
                        color: Colors.blue,
                      ),
                    ),
                  if (shop.hasWaste && !(shop.isWasteCollected ?? shop.expectedWaste?.isCollected ?? false))
                    Expanded(
                      child: _ActionButton(
                        icon: Icons.inventory_2,
                        label: 'Log Waste',
                        onPressed: onWasteTap,
                        color: Colors.orange,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Small action button for use in ShopCard
class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
  final Color color;

  const _ActionButton({
    Key? key,
    required this.icon,
    required this.label,
    this.onPressed,
    required this.color,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: ElevatedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 18),
        label: Text(
          label,
          style: Theme.of(context).textTheme.labelSmall,
        ),
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        ),
      ),
    );
  }
}
