<?php
// orders.php
session_start();
require 'db.php';

// FORCE AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$dbError = false;
$orders = [];

// Fetch Orders and their associated items
try {
    // 1. Get the main orders
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Loop through each order and fetch its items
    foreach ($orders as $key => $order) {
        // We join with the products table to get the product name and image
        $itemStmt = $pdo->prepare("
            SELECT oi.quantity, oi.price, p.name, p.image_url 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        
        // Attach the items directly to the order array
        $orders[$key]['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    // Failsafe if the tables don't exist yet
    $dbError = true; 
}

// Helper function to color-code order statuses
function getStatusColor($status) {
    $status = $status ?? 'pending'; 
    switch (strtolower($status)) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'shipped': return 'bg-purple-100 text-purple-800';
        case 'delivered': return 'bg-green-100 text-green-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders | CustomStore.</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans antialiased">

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex flex-wrap justify-between items-center">
            <a href="index.php" class="text-2xl font-extrabold tracking-tight text-indigo-600">CustomStore.</a>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-gray-500 hover:text-indigo-600 font-medium text-sm transition">&larr; Back to Shop</a>
                <a href="logout.php" class="text-gray-500 hover:text-red-600 font-medium text-sm transition">Logout</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Order History & Tracking</h1>

        <?php if ($dbError): ?>
            <div class="bg-red-50 p-6 rounded-xl border border-red-200 text-red-700">
                <strong>Database Error:</strong> We could not fetch your orders. Please ensure the <code>orders</code> and <code>order_items</code> tables exist.
            </div>
        <?php elseif (empty($orders)): ?>
            <div class="bg-white p-8 rounded-xl border border-gray-100 text-center shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-gray-500 text-lg">You haven't placed any orders yet.</p>
                <a href="index.php" class="text-indigo-600 font-semibold mt-4 inline-block hover:underline">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): 
                    $orderId = $order['id'] ?? 'Unknown';
                    $orderStatus = $order['status'] ?? 'Pending';
                    $orderTotal = $order['total_amount'] ?? 0.00;
                    $orderDate = isset($order['created_at']) ? date('F j, Y, g:i a', strtotime($order['created_at'])) : 'Date unavailable';
                    $trackingNum = $order['tracking_number'] ?? null;
                    $items = $order['items'] ?? [];
                ?>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                        
                        <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                            <div>
                                <div class="flex items-center space-x-3 mb-2">
                                    <h2 class="text-lg font-bold text-gray-900">Order #<?= htmlspecialchars($orderId) ?></h2>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= getStatusColor($orderStatus) ?>">
                                        <?= htmlspecialchars($orderStatus) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500">Placed on: <?= htmlspecialchars($orderDate) ?></p>
                            </div>

                            <div class="md:text-right">
                                <?php if (!empty($trackingNum)): ?>
                                    <p class="text-sm text-gray-500">Tracking Number:</p>
                                    <p class="font-mono text-indigo-600 font-bold"><?= htmlspecialchars($trackingNum) ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-400 italic">Tracking details pending</p>
                                <?php endif; ?>
                                <p class="text-md font-extrabold text-gray-900 mt-2">Total: $<?= number_format((float)$orderTotal, 2) ?></p>
                            </div>
                        </div>

                        <div class="p-6">
                            <h3 class="text-sm font-bold text-gray-900 mb-4 uppercase tracking-wider">Items in this order</h3>
                            <ul class="space-y-4">
                                <?php if (empty($items)): ?>
                                    <li class="text-sm text-gray-500 italic">No item details available for this order.</li>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <li class="flex items-center space-x-4">
                                            <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-100">
                                                <img src="<?= htmlspecialchars($item['image_url'] ?? 'https://via.placeholder.com/150') ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="h-full w-full object-cover object-center">
                                            </div>
                                            <div class="flex-1 flex justify-between">
                                                <div>
                                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($item['name']) ?></p>
                                                    <p class="text-sm text-gray-500">Qty: <?= (int)$item['quantity'] ?></p>
                                                </div>
                                                <p class="font-medium text-gray-900">$<?= number_format((float)$item['price'], 2) ?></p>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>