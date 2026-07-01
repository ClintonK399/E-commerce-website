<?php
// index.php
session_start();
require 'db.php';

// FORCE AUTHENTICATION
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Calculate total items in the cart
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}

// Handle Search & Category Filtering
$whereClauses = ['stock > 0'];
$params = [];
$searchQuery = $_GET['search'] ?? '';
$currentCategory = $_GET['category'] ?? '';

if (!empty($searchQuery)) {
    $whereClauses[] = '(name LIKE ? OR description LIKE ?)';
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}

if (!empty($currentCategory)) {
    $whereClauses[] = 'category = ?';
    $params[] = $currentCategory;
}

$whereSql = implode(' AND ', $whereClauses);
$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as review_count 
    FROM products p 
    LEFT JOIN reviews r ON p.id = r.product_id 
    WHERE $whereSql 
    GROUP BY p.id 
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$mainProducts = $stmt->fetchAll();

// Fetch Featured Sections
$flashSales = $pdo->query("SELECT p.*, COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as review_count FROM products p LEFT JOIN reviews r ON p.id = r.product_id WHERE p.stock > 0 AND p.is_flash_sale = 1 GROUP BY p.id LIMIT 4")->fetchAll();
$newProducts = $pdo->query("SELECT p.*, COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as review_count FROM products p LEFT JOIN reviews r ON p.id = r.product_id WHERE p.stock > 0 AND p.is_new = 1 GROUP BY p.id ORDER BY p.created_at DESC LIMIT 4")->fetchAll();
$youMayLike = $pdo->query("SELECT p.*, COALESCE(AVG(r.rating), 0) as avg_rating, COUNT(r.id) as review_count FROM products p LEFT JOIN reviews r ON p.id = r.product_id WHERE p.stock > 0 GROUP BY p.id ORDER BY RAND() LIMIT 4")->fetchAll();

$sidebarMenu = [
    'TV, Audio & Video' => ['TV', 'Home Audio Systems', 'Camera & Photo', 'Accessories'],
    'Fashion & Apparel' => ['Clothes', 'Bags', 'Shoes'],
    'Home & Living' => ['Appliances', 'Home & Kitchen'],
    'Personal Care' => ['Health & Beauty']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Custom Storefront | High-Performance E-commerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 text-gray-900 font-sans antialiased">

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">
            <a href="index.php" class="text-2xl font-extrabold tracking-tight text-indigo-600">CustomStore.</a>

            <div class="flex-1 max-w-2xl mx-auto order-last md:order-none w-full md:w-auto">
                <form action="index.php" method="GET" class="relative">
                    <?php if (!empty($currentCategory)): ?>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($currentCategory) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search for products, brands..." class="w-full border border-gray-300 rounded-lg pl-4 pr-12 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <button type="submit" class="absolute right-0 top-0 bottom-0 px-4 text-gray-500 hover:text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </form>
            </div>

            <div class="flex items-center space-x-4">
                <span class="text-gray-700 font-medium hidden sm:block">Hi, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin_dashboard.php" class="text-indigo-600 font-medium hover:underline text-sm">Dashboard</a>
                <?php endif; ?>
                
                <a href="orders.php" class="text-indigo-600 font-semibold text-sm hover:underline transition">My Orders</a>
                
                <a href="logout.php" class="text-gray-500 hover:text-red-600 font-medium text-sm transition">Logout</a>

                <a href="cart.php" class="flex items-center space-x-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg font-semibold transition">
                    <span>Cart (<?= $cartCount ?>)</span>
                </a>

                <?php if ($cartCount > 0): ?>
                    <a href="checkout.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-semibold transition">Checkout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 flex flex-col md:flex-row gap-8">

        <aside class="w-full md:w-64 flex-shrink-0">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sticky top-24">
                <a href="index.php" class="block font-bold text-gray-900 mb-4 text-lg border-b pb-2 hover:text-indigo-600 transition">
                    All Categories
                </a>

                <div class="space-y-6">
                    <?php foreach ($sidebarMenu as $parentCategory => $subCategories): ?>
                        <div>
                            <h4 class="font-bold text-gray-800 text-sm uppercase tracking-wider mb-2"><?= htmlspecialchars($parentCategory) ?></h4>
                            <ul class="space-y-1 border-l-2 border-gray-100 ml-2 pl-2">
                                <?php foreach ($subCategories as $cat): ?>
                                    <li>
                                        <a href="index.php?category=<?= urlencode($cat) ?>"
                                            class="block px-2 py-1.5 rounded-md text-sm transition <?= ($currentCategory === $cat) ? 'bg-indigo-50 text-indigo-700 font-semibold border-l-2 border-indigo-500 -ml-[10px] pl-[10px]' : 'text-gray-600 hover:text-indigo-600 hover:bg-gray-50' ?>">
                                            <?= htmlspecialchars($cat) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <div class="flex-1 space-y-12">
            <?php
            $renderProductCard = function ($product) {
                $rating = $product['avg_rating'] ?? 0;
                $reviewCount = $product['review_count'] ?? 0;
            ?>
                <article class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300 group relative flex flex-col">
                    <div class="absolute top-2 left-2 z-10 flex flex-col gap-1">
                        <?php if (!empty($product['is_flash_sale'])): ?><span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded shadow-sm">Flash Sale</span><?php endif; ?>
                        <?php if (!empty($product['is_new'])): ?><span class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded shadow-sm">New</span><?php endif; ?>
                    </div>

                    <a href="product.php?id=<?= urlencode($product['id']) ?>" class="block flex-1">
                        <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden bg-gray-200">
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="h-48 w-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                        </div>
                        <div class="p-4 flex flex-col">
                            <p class="text-xs text-indigo-500 font-semibold mb-1 uppercase tracking-wider"><?= htmlspecialchars($product['category']) ?></p>
                            <h2 class="text-md font-bold text-gray-900 line-clamp-1 group-hover:text-indigo-600 transition"><?= htmlspecialchars($product['name']) ?></h2>

                            <div class="flex items-center mt-2 space-x-1">
                                <div class="flex text-yellow-400">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="h-4 w-4 <?= $i <= round($rating) ? 'fill-current' : 'text-gray-300' ?>" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-xs text-gray-500 font-medium">(<?= $reviewCount ?>)</span>
                            </div>
                        </div>
                    </a>

                    <div class="p-4 pt-0 mt-auto flex items-center justify-between">
                        <div>
                            <p class="text-lg font-extrabold text-gray-900">$<?= number_format($product['price'], 2) ?></p>
                        </div>
                        <form action="add-to-cart.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['id']) ?>">
                            <button type="submit" class="bg-gray-900 text-white p-2 rounded-lg hover:bg-indigo-600 transition shadow-sm">Add</button>
                        </form>
                    </div>
                </article>
            <?php }; ?>

            <section>
                <div class="mb-6 border-b pb-4">
                    <h1 class="text-2xl font-bold text-gray-900">
                        <?php
                        if (!empty($searchQuery)) echo "Search Results for '" . htmlspecialchars($searchQuery) . "'";
                        elseif (!empty($currentCategory)) echo htmlspecialchars($currentCategory);
                        else echo "All Products";
                        ?>
                    </h1>
                </div>

                <?php if (empty($mainProducts)): ?>
                    <div class="bg-white p-8 rounded-xl border border-gray-100 text-center">
                        <p class="text-gray-500 text-lg">No products found matching your criteria.</p>
                        <a href="index.php" class="text-indigo-600 font-semibold mt-4 inline-block hover:underline">Clear filters</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach ($mainProducts as $product) $renderProductCard($product); ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (empty($searchQuery) && empty($currentCategory)): ?>
                <?php if (!empty($flashSales)): ?>
                    <section class="bg-red-50 p-6 rounded-2xl border border-red-100 mt-12">
                        <div class="flex items-center space-x-2 mb-6">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <h2 class="text-2xl font-bold text-gray-900">Flash Sale</h2>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <?php foreach ($flashSales as $product) $renderProductCard($product); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($newProducts)): ?>
                    <section class="mt-12">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">What's New in the Market</h2>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <?php foreach ($newProducts as $product) $renderProductCard($product); ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($youMayLike)): ?>
                    <section class="bg-indigo-50/50 p-6 rounded-2xl border border-indigo-50 mt-12">
                        <div class="mb-6">
                            <h2 class="text-2xl font-bold text-gray-900">What You May Also Like</h2>
                        </div>
                        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <?php foreach ($youMayLike as $product) $renderProductCard($product); ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>
</body>

</html>