<?php
// cart.php
session_start();
require 'db.php';

$cartItems = [];
$totalPrice = 0;
$cartCount = 0;

// Check if cart exists and is not empty
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);

    // Extract product IDs from the session
    $productIds = array_keys($_SESSION['cart']);

    // Create placeholders for the SQL IN clause (e.g., ?, ?, ?)
    $placeholders = rtrim(str_repeat('?, ', count($productIds)), ', ');

    // Fetch only the products that are in the cart
    $stmt = $pdo->prepare("SELECT id, name, price, image_url FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll();

    // Map fetched products to their quantities and calculate total
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        $subtotal = $product['price'] * $quantity;
        $totalPrice += $subtotal;

        $product['quantity'] = $quantity;
        $product['subtotal'] = $subtotal;
        $cartItems[] = $product;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-900 font-sans antialiased">

    <!-- Navigation Bar -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="index.php" class="text-2xl font-extrabold tracking-tight text-indigo-600">CustomStore.</a>
            <div class="flex items-center space-x-4">
                <a href="cart.php" class="flex items-center space-x-2 bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg font-semibold transition">
                    <span>Cart (<?= $cartCount ?>)</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-12 max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

        <?php if (empty($cartItems)): ?>
            <div class="bg-white p-8 rounded-xl shadow-sm text-center border border-gray-100">
                <p class="text-gray-500 mb-4">Your cart is currently empty.</p>
                <a href="index.php" class="inline-block bg-indigo-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    <?php foreach ($cartItems as $item): ?>
                        <li class="p-6 flex items-center">
                            <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="h-20 w-20 object-cover rounded-md bg-gray-100">
                            <div class="ml-6 flex-1">
                                <h3 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($item['name']) ?></h3>
                                <p class="text-gray-500 mt-1">Price: $<?= number_format($item['price'], 2) ?></p>
                            </div>
                            <div class="ml-6 flex flex-col items-end">
                                <p class="text-sm font-medium text-gray-900">Qty: <?= $item['quantity'] ?></p>
                                <p class="text-lg font-bold text-indigo-600 mt-1">$<?= number_format($item['subtotal'], 2) ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Cart Summary -->
                <div class="bg-gray-50 p-6 border-t border-gray-200 flex justify-between items-center">
                    <div>
                        <a href="index.php" class="text-indigo-600 hover:text-indigo-800 font-medium">&larr; Continue Shopping</a>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Subtotal</p>
                        <p class="text-3xl font-extrabold text-gray-900">$<?= number_format($totalPrice, 2) ?></p>

                        <!-- UPDATED: Changed from <button> to an <a> tag pointing to checkout.php -->
                        <a href="checkout.php" class="mt-4 inline-block text-center bg-gray-900 hover:bg-gray-800 text-white px-8 py-3 rounded-lg font-bold transition-colors w-full sm:w-auto">
                            Proceed to Checkout
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

</body>

</html>