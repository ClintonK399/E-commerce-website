<?php
// admin_dashboard.php
session_start();
require 'db.php';

// STRICT SECURITY CHECK: Admins Only
if (!isset($_SESSION['user_id']) || (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin')) {
    header('Location: index.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

// Determine which tab is active (default is 'dashboard')
$currentTab = $_GET['tab'] ?? 'dashboard';

// ---------------------------------------------------------
// POST REQUEST HANDLERS
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Handle Adding a New Product
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $price = $_POST['price'];
        $category = $_POST['category'];
        $stock = $_POST['stock'];
        $description = trim($_POST['description']);
        $image_url = trim($_POST['image_url']);
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . rand(1000, 9999);

        if (empty($name) || empty($price) || empty($category)) {
            $errorMessage = "Please fill in all required fields.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO products (name, slug, price, description, image_url, stock, category, is_new, is_flash_sale) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $price, $description, $image_url, $stock, $category, $is_new, $is_flash_sale]);
                $successMessage = "Product successfully added to " . htmlspecialchars($category) . "!";
            } catch (PDOException $e) {
                $errorMessage = "Failed to add product. Error: " . $e->getMessage();
            }
        }
    }

    // 2. Handle Order Payment Verification
    if (isset($_POST['verify_order'])) {
        $orderId = (int)$_POST['order_id'];
        try {
            // Assuming your orders table has a payment_status column
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'verified' WHERE id = ?");
            $stmt->execute([$orderId]);
            $successMessage = "Payment for Order #$orderId marked as verified.";
        } catch (PDOException $e) {
            $errorMessage = "Error verifying payment: Make sure 'payment_status' exists in your orders table.";
        }
    }

    // 3. Handle Order Dispatching
    if (isset($_POST['dispatch_order'])) {
        $orderId = (int)$_POST['order_id'];
        try {
            // Update order status to Shipped
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Shipped' WHERE id = ?");
            $stmt->execute([$orderId]);
            $successMessage = "Order #$orderId has been dispatched/shipped!";
        } catch (PDOException $e) {
            $errorMessage = "Error dispatching order.";
        }
    }
}

// ---------------------------------------------------------
// FETCH DATA FOR TABS (With Safe Fallbacks)
// ---------------------------------------------------------

// Dashboard Stats
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalUsers = 0;
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' OR role IS NULL")->fetchColumn();
} catch (PDOException $e) {}

// Fetch Orders
$ordersList = [];
try {
    // Left join just in case user is deleted
    $ordersStmt = $pdo->query("
        SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC
    ");
    $ordersList = $ordersStmt->fetchAll();
} catch (PDOException $e) { /* Failsafe */ }

// Fetch Users
$usersList = [];
try {
    $usersList = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) { /* Failsafe */ }

// Fetch Reviews
$reviewsList = [];
try {
    $reviewsList = $pdo->query("
        SELECT r.*, p.name as product_name, u.name as reviewer_name 
        FROM reviews r 
        LEFT JOIN products p ON r.product_id = p.id 
        LEFT JOIN users u ON r.user_id = u.id 
        ORDER BY r.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) { /* Failsafe */ }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CustomStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans antialiased text-gray-900">

    <header class="bg-indigo-900 shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center text-white">
            <div class="flex items-center space-x-4">
                <a href="admin_dashboard.php" class="text-2xl font-extrabold tracking-tight">AdminPanel.</a>
                <span class="bg-indigo-800 text-indigo-100 text-xs px-2 py-1 rounded font-bold uppercase tracking-wider hidden sm:inline-block">Secure Area</span>
            </div>
            <div class="flex items-center space-x-6">
                <span class="font-medium text-indigo-100">Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></span>
                <a href="index.php" class="bg-indigo-700 hover:bg-indigo-600 px-4 py-2 rounded-lg font-semibold transition text-sm shadow-sm">Live Store &rarr;</a>
                <a href="logout.php" class="text-indigo-200 hover:text-red-400 transition text-sm font-medium">Logout</a>
            </div>
        </div>
        
        <div class="bg-white border-b border-gray-200 shadow-sm text-gray-600">
            <div class="container mx-auto px-4 flex space-x-8 overflow-x-auto">
                <a href="?tab=dashboard" class="py-4 font-semibold text-sm border-b-2 transition-colors <?= $currentTab === 'dashboard' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-indigo-600' ?>">Overview</a>
                <a href="?tab=add_product" class="py-4 font-semibold text-sm border-b-2 transition-colors <?= $currentTab === 'add_product' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-indigo-600' ?>">Add Product</a>
                <a href="?tab=orders" class="py-4 font-semibold text-sm border-b-2 transition-colors <?= $currentTab === 'orders' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-indigo-600' ?>">Orders & Payments</a>
                <a href="?tab=users" class="py-4 font-semibold text-sm border-b-2 transition-colors <?= $currentTab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-indigo-600' ?>">Users</a>
                <a href="?tab=reviews" class="py-4 font-semibold text-sm border-b-2 transition-colors <?= $currentTab === 'reviews' ? 'border-indigo-600 text-indigo-600' : 'border-transparent hover:text-indigo-600' ?>">Reviews</a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">

        <?php if ($successMessage): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-800 p-4 mb-6 rounded shadow-sm font-medium">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-800 p-4 mb-6 rounded shadow-sm font-medium">
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'dashboard'): ?>
            <h1 class="text-2xl font-bold mb-6">Store Overview</h1>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="p-3 bg-blue-100 text-blue-600 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $totalProducts ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="p-3 bg-green-100 text-green-600 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Users</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $totalUsers ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex items-center space-x-4">
                    <div class="p-3 bg-purple-100 text-purple-600 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-gray-500 text-sm font-medium">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($ordersList) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'add_product'): ?>
            <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-4">Add New Product to Catalog</h2>
                <form method="POST" action="admin_dashboard.php?tab=add_product" class="space-y-5">
                    <input type="hidden" name="add_product" value="1">
                    
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Price ($) *</label>
                            <input type="number" step="0.01" name="price" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Stock Qty *</label>
                            <input type="number" name="stock" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Category *</label>
                        <select name="category" required class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 bg-white transition">
                            <optgroup label="TV, Audio & Video">
                                <option value="TV">TV</option>
                                <option value="Home Audio Systems">Home Audio Systems</option>
                                <option value="Camera & Photo">Camera & Photo</option>
                                <option value="Accessories">Accessories</option>
                            </optgroup>
                            <optgroup label="Fashion & Apparel">
                                <option value="Clothes">Clothes</option>
                                <option value="Bags">Bags</option>
                                <option value="Shoes">Shoes</option>
                            </optgroup>
                            <optgroup label="Home & Living">
                                <option value="Appliances">Appliances</option>
                                <option value="Home & Kitchen">Home & Kitchen</option>
                            </optgroup>
                            <optgroup label="Personal Care">
                                <option value="Health & Beauty">Health & Beauty</option>
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Image URL</label>
                        <input type="url" name="image_url" placeholder="https://example.com/image.jpg" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 transition">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="4" class="w-full border border-gray-300 p-2.5 rounded-lg focus:ring-2 focus:ring-indigo-500 transition"></textarea>
                    </div>

                    <div class="flex items-center space-x-8 pt-4 border-t border-gray-100">
                        <label class="flex items-center space-x-2 text-sm font-bold text-gray-700 cursor-pointer">
                            <input type="checkbox" name="is_new" class="rounded text-indigo-600 h-4 w-4">
                            <span>Mark as "New"</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm font-bold text-gray-700 cursor-pointer">
                            <input type="checkbox" name="is_flash_sale" class="rounded text-indigo-600 h-4 w-4">
                            <span>Flash Sale Item</span>
                        </label>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition mt-6 shadow-md">
                        Publish Product
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'orders'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 overflow-x-auto">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Orders & Fulfillment</h2>
                
                <?php if (empty($ordersList)): ?>
                    <p class="text-gray-500 py-4 italic">No orders found.</p>
                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="p-3 text-sm font-bold text-gray-700">Order ID</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Customer</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Total</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Payment</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Dispatch Status</th>
                                <th class="p-3 text-sm font-bold text-gray-700 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php foreach ($ordersList as $order): 
                                $payStatus = strtolower($order['payment_status'] ?? 'pending');
                                $orderStatus = strtolower($order['status'] ?? 'pending');
                            ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="p-3 font-bold text-gray-900">#<?= htmlspecialchars($order['id']) ?></td>
                                    <td class="p-3">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></div>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($order['customer_email'] ?? '') ?></div>
                                    </td>
                                    <td class="p-3 font-bold text-green-600">$<?= number_format($order['total_amount'], 2) ?></td>
                                    
                                    <td class="p-3">
                                        <?php if ($payStatus === 'verified'): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">Verified</span>
                                        <?php else: ?>
                                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">Pending</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="p-3">
                                        <?php if ($orderStatus === 'shipped' || $orderStatus === 'dispatched'): ?>
                                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-bold">Shipped</span>
                                        <?php elseif ($orderStatus === 'delivered'): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-bold">Delivered</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-bold">Processing</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="p-3 flex justify-end space-x-2">
                                        <?php if ($payStatus !== 'verified'): ?>
                                            <form method="POST" action="admin_dashboard.php?tab=orders" class="inline">
                                                <input type="hidden" name="verify_order" value="1">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" class="text-white bg-green-500 hover:bg-green-600 font-semibold text-xs px-3 py-1.5 rounded transition shadow-sm">
                                                    Verify Pay
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($orderStatus !== 'shipped' && $orderStatus !== 'delivered'): ?>
                                            <form method="POST" action="admin_dashboard.php?tab=orders" class="inline">
                                                <input type="hidden" name="dispatch_order" value="1">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" class="text-white bg-indigo-600 hover:bg-indigo-700 font-semibold text-xs px-3 py-1.5 rounded transition shadow-sm">
                                                    Dispatch
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($payStatus === 'verified' && ($orderStatus === 'shipped' || $orderStatus === 'delivered')): ?>
                                            <span class="text-gray-400 text-xs italic py-1.5">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'users'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 overflow-x-auto">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Registered Users</h2>
                <?php if (empty($usersList)): ?>
                    <p class="text-gray-500 italic">No users found.</p>
                <?php else: ?>
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b">
                                <th class="p-3 text-sm font-bold text-gray-700">ID</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Name</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Email</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Role</th>
                                <th class="p-3 text-sm font-bold text-gray-700">Registered On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-sm">
                            <?php foreach ($usersList as $u): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 text-gray-500">#<?= htmlspecialchars($u['id']) ?></td>
                                    <td class="p-3 font-bold text-gray-900"><?= htmlspecialchars($u['name']) ?></td>
                                    <td class="p-3 text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                                    <td class="p-3">
                                        <?php if ($u['role'] === 'admin'): ?>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold uppercase">Admin</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-bold uppercase">Customer</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-gray-500"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($currentTab === 'reviews'): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Customer Reviews</h2>
                
                <?php if (empty($reviewsList)): ?>
                    <p class="text-gray-500 italic text-center py-8">No product reviews yet.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($reviewsList as $review): ?>
                            <div class="border border-gray-100 p-4 rounded-lg bg-gray-50 hover:bg-white transition shadow-sm">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-bold text-gray-900"><?= htmlspecialchars($review['product_name']) ?></h3>
                                        <p class="text-xs text-gray-500">Reviewed by <?= htmlspecialchars($review['reviewer_name']) ?> on <?= date('M j, Y', strtotime($review['created_at'])) ?></p>
                                    </div>
                                    <div class="flex text-yellow-400">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="h-4 w-4 <?= $i <= $review['rating'] ? 'fill-current' : 'text-gray-300' ?>" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                    <p class="text-gray-700 text-sm mt-2 italic">"<?= htmlspecialchars($review['comment']) ?>"</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>
</body>
</html>