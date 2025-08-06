<?php
require_once '../backend/includes/config.php';
require_once '../backend/includes/auth.php';

$auth = new Auth();
$auth->require_login();

$user = $auth->get_current_user();
$db = new SQLite3(DB_PATH);
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill all password fields';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } else {
            if ($auth->change_password($user['id'], $current_password, $new_password)) {
                $message = 'Password changed successfully';
            } else {
                $error = 'Current password is incorrect';
            }
        }
    } elseif ($action === 'update_settings') {
        $panel_name = sanitize_input($_POST['panel_name'] ?? 'LiteWP');
        $backup_retention = (int)($_POST['backup_retention'] ?? 30);
        $ssl_provider = sanitize_input($_POST['ssl_provider'] ?? 'letsencrypt');
        
        set_setting('panel_name', $panel_name);
        set_setting('backup_retention', $backup_retention);
        set_setting('ssl_provider', $ssl_provider);
        
        $message = 'Settings updated successfully';
    }
}

// Get current settings
$panel_name = get_setting('panel_name', 'LiteWP');
$backup_retention = get_setting('backup_retention', '30');
$ssl_provider = get_setting('ssl_provider', 'letsencrypt');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LiteWP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-800">LiteWP</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                    <a href="/logout.php" class="text-red-600 hover:text-red-800">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-4">
                <a href="/dashboard.php" class="text-gray-600 hover:text-gray-800">Dashboard</a>
                <a href="/websites.php" class="text-gray-600 hover:text-gray-800">Websites</a>
                <a href="/wordpress.php" class="text-gray-600 hover:text-gray-800">WordPress</a>
                <a href="/database.php" class="text-gray-600 hover:text-gray-800">Database</a>
                <a href="/ssl.php" class="text-gray-600 hover:text-gray-800">SSL</a>
                <a href="/firewall.php" class="text-gray-600 hover:text-gray-800">Firewall</a>
                <a href="/settings.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">Settings</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Security Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Security Settings</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Password
                        </label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter current password"
                        >
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                            New Password
                        </label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter new password"
                        >
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm New Password
                        </label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Confirm new password"
                        >
                    </div>
                    
                    <button 
                        type="submit"
                        class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                    >
                        Change Password
                    </button>
                </form>
            </div>

            <!-- Panel Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Panel Settings</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div>
                        <label for="panel_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Panel Name
                        </label>
                        <input 
                            type="text" 
                            id="panel_name" 
                            name="panel_name" 
                            value="<?php echo htmlspecialchars($panel_name); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label for="backup_retention" class="block text-sm font-medium text-gray-700 mb-2">
                            Backup Retention (Days)
                        </label>
                        <input 
                            type="number" 
                            id="backup_retention" 
                            name="backup_retention" 
                            value="<?php echo htmlspecialchars($backup_retention); ?>"
                            min="1"
                            max="365"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label for="ssl_provider" class="block text-sm font-medium text-gray-700 mb-2">
                            SSL Provider
                        </label>
                        <select 
                            id="ssl_provider" 
                            name="ssl_provider"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="letsencrypt" <?php echo $ssl_provider === 'letsencrypt' ? 'selected' : ''; ?>>Let's Encrypt</option>
                            <option value="custom" <?php echo $ssl_provider === 'custom' ? 'selected' : ''; ?>>Custom SSL</option>
                        </select>
                    </div>
                    
                    <button 
                        type="submit"
                        class="bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
                    >
                        Update Settings
                    </button>
                </form>
            </div>
        </div>

        <!-- System Information -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">System Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm font-medium text-gray-500">Panel Version</p>
                    <p class="text-sm text-gray-900"><?php echo PANEL_VERSION; ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">PHP Version</p>
                    <p class="text-sm text-gray-900"><?php echo phpversion(); ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Server</p>
                    <p class="text-sm text-gray-900"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Database</p>
                    <p class="text-sm text-gray-900">SQLite</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="/tools/adminer.php" target="_blank" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">Database Admin</p>
                        <p class="text-sm text-gray-500">Manage databases</p>
                    </div>
                </a>
                
                <a href="/tools/filemanager.php" target="_blank" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">File Manager</p>
                        <p class="text-sm text-gray-500">Manage files</p>
                    </div>
                </a>
                
                <a href="/logs.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-900">System Logs</p>
                        <p class="text-sm text-gray-500">View logs</p>
                    </div>
                </a>
            </div>
        </div>
    </main>
</body>
</html> 