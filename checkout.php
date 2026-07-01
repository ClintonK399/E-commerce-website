<?php
// checkout.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['cart'])) {
    header('Location: index.php');
    exit;
}

// Calculate total
$productIds = array_keys($_SESSION['cart']);
$placeholders = rtrim(str_repeat('?, ', count($productIds)), ', ');
$stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
$stmt->execute($productIds);
$products = $stmt->fetchAll();

$totalPrice = 0;
foreach ($products as $product) {
    $totalPrice += $product['price'] * $_SESSION['cart'][$product['id']];
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'];

    // Create Order
    $stmtOrder = $pdo->prepare("INSERT INTO orders (user_id, total_amount, payment_status, payment_method) VALUES (?, ?, 'pending', ?)");
    $stmtOrder->execute([$_SESSION['user_id'], $totalPrice, $paymentMethod]);
    $orderId = $pdo->lastInsertId();

    // Clear Cart
    $_SESSION['cart'] = [];
    $success = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Secure Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-xl shadow-md w-full max-w-md border border-gray-100">

        <?php if (isset($success)): ?>
            <div class="text-center">
                <svg class="h-16 w-16 text-green-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h2>
                <p class="text-gray-600 mb-6">Your order #<?= $orderId ?> has been placed and is awaiting admin verification.</p>
                <a href="index.php" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 font-bold">Return to Shop</a>
            </div>
        <?php else: ?>
            <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-4">Checkout</h2>
            <div class="mb-6 flex justify-between items-center bg-gray-50 p-4 rounded-lg">
                <span class="text-gray-600 font-medium">Total to Pay:</span>
                <span class="text-2xl font-extrabold text-indigo-600">$<?= number_format($totalPrice, 2) ?></span>
            </div>

            <form method="POST">
                <h3 class="font-bold text-gray-800 mb-4">Select Payment Method</h3>

                <div class="space-y-3 mb-8">
                    <label class="flex items-center space-x-3 p-4 border rounded-lg hover:bg-indigo-50 cursor-pointer transition">
                        <input type="radio" name="payment_method" value="Credit Card" required class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="font-semibold text-gray-800">Credit / Debit Card</span>
                    </label>
                    <label class="flex items-center space-x-3 p-4 border rounded-lg hover:bg-indigo-50 cursor-pointer transition">
                        <input type="radio" name="payment_method" value="M-Pesa" required class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="font-semibold text-gray-800">Mobile Money (M-Pesa)</span>
                    </label>
                    <label class="flex items-center space-x-3 p-4 border rounded-lg hover:bg-indigo-50 cursor-pointer transition">
                        <input type="radio" name="payment_method" value="PayPal" required class="text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="font-semibold text-gray-800">PayPal</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-gray-900 text-white py-3 rounded-lg font-bold hover:bg-indigo-600 transition">
                    Confirm & Pay
                </button>
            </form>
            <a href="cart.php" class="block text-center text-sm text-gray-500 mt-4 hover:underline">Cancel and return to cart</a>
        <?php endif; ?>
    </div>
</body>

</html>