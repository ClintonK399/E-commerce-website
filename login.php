<?php
// login.php
session_start();
require 'db.php';

$error = '';

// Generate CSRF token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) === false) {
        die("Invalid CSRF token.");
    }

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Fetch user
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify password
        if ($user && password_verify($password, $user['password'])) {

            // Prevent Session Fixation Attacks
            session_regenerate_id(true);

            // NORMALIZE THE ROLE: 
            // This forces it to lowercase and trims whitespace so "Admin" or " admin " matches "admin"
            $role = strtolower(trim($user['role'] ?? 'customer'));

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $role;

            // Route based on role
            if ($role === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | CustomStore</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 flex items-center justify-center min-h-screen py-12 px-4">

    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-100 w-full max-w-md">

        <div class="text-center mb-8">
            <a href="index.php" class="text-3xl font-extrabold tracking-tight text-indigo-600 inline-block mb-2">CustomStore.</a>
            <h2 class="text-2xl font-bold text-gray-900">Sign in to your account</h2>
        </div>

        <?php if (isset($_GET['registered']) && $_GET['registered'] === 'success'): ?>
            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r text-sm">
                <p>Account created successfully! Please log in below.</p>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r text-sm">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required
                    class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none">
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" required
                    class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition outline-none">
            </div>

            <button type="submit" class="w-full bg-gray-900 text-white p-3 rounded-lg hover:bg-indigo-600 font-bold shadow-sm transition-colors mt-4">
                Sign In
            </button>
        </form>

        <div class="mt-8 text-center">
            <p class="text-sm text-gray-600">
                Don't have an account?
                <a href="signup.php" class="font-semibold text-indigo-600 hover:text-indigo-800 transition">Sign up now</a>
            </p>
        </div>
    </div>

</body>

</html>