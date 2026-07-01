<?php
// product.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$productId = $_GET['id'] ?? null;
if (!$productId) {
    header('Location: index.php');
    exit;
}

// Handle New Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5) {
        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$productId, $_SESSION['user_id'], $rating, $comment]);
        header("Location: product.php?id=$productId&review=success");
        exit;
    }
}

// Fetch Product Details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

// Fetch Reviews
$stmtReviews = $pdo->prepare("SELECT r.*, u.name as reviewer_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmtReviews->execute([$productId]);
$reviews = $stmtReviews->fetchAll();

// Calculate Average
$avgRating = count($reviews) > 0 ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($product['name']) ?> | CustomStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-12 max-w-5xl">
        <a href="index.php" class="text-indigo-600 hover:underline mb-6 inline-block">&larr; Back to Shop</a>

        <div class="bg-white rounded-xl shadow-sm p-8 grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <div>
                <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full rounded-lg object-cover">
            </div>
            <div class="flex flex-col justify-center">
                <span class="text-indigo-500 font-bold uppercase tracking-wider text-sm"><?= htmlspecialchars($product['category']) ?></span>
                <h1 class="text-3xl font-extrabold text-gray-900 mt-2"><?= htmlspecialchars($product['name']) ?></h1>
                <p class="text-2xl font-bold text-gray-900 mt-4">$<?= number_format($product['price'], 2) ?></p>
                <p class="text-gray-600 mt-4"><?= htmlspecialchars($product['description']) ?></p>

                <form action="add-to-cart.php" method="POST" class="mt-8">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 transition">Add to Cart</button>
                </form>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="bg-white rounded-xl shadow-sm p-8">
            <h2 class="text-2xl font-bold mb-6 border-b pb-4">Customer Reviews (<?= count($reviews) ?>)</h2>

            <!-- Submit Review Form -->
            <div class="mb-10 bg-gray-50 p-6 rounded-lg">
                <h3 class="font-bold text-gray-800 mb-4">Write a Review</h3>
                <form method="POST">
                    <input type="hidden" name="submit_review" value="1">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Rating</label>
                        <select name="rating" required class="mt-1 w-full md:w-1/4 border border-gray-300 p-2 rounded-md">
                            <option value="5">5 Stars - Excellent</option>
                            <option value="4">4 Stars - Good</option>
                            <option value="3">3 Stars - Average</option>
                            <option value="2">2 Stars - Poor</option>
                            <option value="1">1 Star - Terrible</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Your Comment</label>
                        <textarea name="comment" rows="3" required class="mt-1 w-full border border-gray-300 p-2 rounded-md"></textarea>
                    </div>
                    <button type="submit" class="bg-gray-900 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-600">Submit Review</button>
                </form>
            </div>

            <!-- List Reviews -->
            <div class="space-y-6">
                <?php foreach ($reviews as $review): ?>
                    <div class="border-b pb-6">
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="font-bold text-gray-900"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                            <span class="text-gray-400 text-sm">• <?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                        </div>
                        <div class="flex text-yellow-400 mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="h-4 w-4 <?= $i <= $review['rating'] ? 'fill-current' : 'text-gray-300' ?>" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700"><?= htmlspecialchars($review['comment']) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($reviews)) echo "<p class='text-gray-500'>Be the first to review this product!</p>"; ?>
            </div>
        </div>
    </div>
</body>

</html>