import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../providers/shops_provider.dart';
import 'widgets/shop_card.dart';
import 'widgets/waste_collection_dialog.dart';

/// Screen displaying list of shops with waste tracking
class ShopsListScreen extends ConsumerWidget {
  const ShopsListScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final shopsAsync = ref.watch(shopsListProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Shops'),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => ref.invalidate(shopsListProvider),
          ),
        ],
      ),
      body: shopsAsync.when(
        data: (shops) {
          if (shops.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.store_outlined, size: 64, color: Colors.grey[400]),
                  const SizedBox(height: 16),
                  Text(
                    'No shops found',
                    style: TextStyle(fontSize: 16, color: Colors.grey[600]),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Shops with waste tracking will appear here',
                    style: TextStyle(fontSize: 12, color: Colors.grey[500]),
                  ),
                ],
              ),
            );
          }

          // Separate shops with and without pending waste
          final shopsWithWaste = shops.where((s) => s.hasWaste).toList();
          final shopsNoWaste = shops.where((s) => !s.hasWaste).toList();

          return RefreshIndicator(
            onRefresh: () async {
              ref.invalidate(shopsListProvider);
            },
            child: ListView(
              padding: const EdgeInsets.symmetric(vertical: 8),
              children: [
                // Summary header
                Container(
                  margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.blue.shade100),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      _SummaryItem(
                        icon: Icons.store,
                        label: 'Total Shops',
                        value: '${shops.length}',
                        color: Colors.blue,
                      ),
                      _SummaryItem(
                        icon: Icons.warning_amber,
                        label: 'Pending Waste',
                        value: '${shopsWithWaste.length}',
                        color: Colors.orange,
                      ),
                      _SummaryItem(
                        icon: Icons.check_circle,
                        label: 'Collected',
                        value: '${shopsNoWaste.length}',
                        color: Colors.green,
                      ),
                    ],
                  ),
                ),

                // Shops with pending waste
                if (shopsWithWaste.isNotEmpty) ...[
                  _SectionHeader(
                    title: 'Pending Waste Collection',
                    count: shopsWithWaste.length,
                    color: Colors.orange,
                  ),
                  ...shopsWithWaste.map((shop) => ShopCard(
                    shop: shop,
                    onNavigateTap: () => _navigateToShop(context, shop),
                    onWasteTap: () => _showWasteDialog(context, ref, shop),
                  )),
                ],

                // Shops without pending waste
                if (shopsNoWaste.isNotEmpty) ...[
                  _SectionHeader(
                    title: 'No Pending Waste',
                    count: shopsNoWaste.length,
                    color: Colors.green,
                  ),
                  ...shopsNoWaste.map((shop) => ShopCard(
                    shop: shop,
                    onNavigateTap: () => _navigateToShop(context, shop),
                  )),
                ],

                const SizedBox(height: 80), // Bottom padding for FAB
              ],
            ),
          );
        },
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stack) => Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.error_outline, size: 64, color: Colors.red[400]),
              const SizedBox(height: 16),
              const Text('Error loading shops'),
              const SizedBox(height: 8),
              Text(
                error.toString(),
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: () => ref.invalidate(shopsListProvider),
                icon: const Icon(Icons.refresh),
                label: const Text('Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _navigateToShop(BuildContext context, shop) async {
    final url = Uri.parse(shop.navigationUrl);
    try {
      if (await canLaunchUrl(url)) {
        await launchUrl(url, mode: LaunchMode.externalApplication);
      } else {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Could not open Google Maps'),
              backgroundColor: Colors.red,
            ),
          );
        }
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Error: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  void _showWasteDialog(BuildContext context, WidgetRef ref, shop) {
    // For now, show a snackbar - waste dialog needs trip context
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Waste collection for ${shop.name}'),
        action: SnackBarAction(
          label: 'View',
          onPressed: () {
            // TODO: Navigate to waste collection detail
          },
        ),
      ),
    );
  }
}

/// Summary item widget
class _SummaryItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _SummaryItem({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: color, size: 24),
        const SizedBox(height: 4),
        Text(
          value,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: color,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            fontSize: 11,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }
}

/// Section header widget
class _SectionHeader extends StatelessWidget {
  final String title;
  final int count;
  final Color color;

  const _SectionHeader({
    required this.title,
    required this.count,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 20,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 8),
          Text(
            title,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: Colors.grey[700],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: color.withOpacity(0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              '$count',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
