<?php
require_once '../backend/includes/config.php';
require_once '../backend/includes/auth.php';

$auth = new Auth();
$auth->require_login();

$user = $auth->get_current_user();
$message = '';
$error = '';

// Get log type
$log_type = $_GET['type'] ?? 'system';
$lines = (int)($_GET['lines'] ?? 100);

// Validate log type
$allowed_logs = ['system', 'access', 'error', 'panel'];
if (!in_array($log_type, $allowed_logs)) {
    $log_type = 'system';
}

// Get log file path
$log_files = [
    'system' => '/var/log/syslog',
    'access' => '/usr/local/litewp/panel/logs/access.log',
    'error' => '/usr/local/litewp/panel/logs/error.log',
    'panel' => '/usr/local/litewp/panel/logs/panel.log'
];

$log_file = $log_files[$log_type] ?? $log_files['system'];

// Get log content
$log_content = '';
if (file_exists($log_file)) {
    $log_content = shell_exec("tail -n {$lines} " . escapeshellarg($log_file));
} else {
    $log_content = "Log file not found: {$log_file}";
}

// Handle clear log action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'clear_log') {
    if (file_exists($log_file) && is_writable($log_file)) {
        if (file_put_contents($log_file, '') !== false) {
            $message = "Log file cleared successfully";
            log_message("Log file '{$log_type}' cleared by user {$user['username']}", 'INFO');
        } else {
            $error = 'Failed to clear log file';
        }
    } else {
        $error = 'Log file not found or not writable';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs - LiteWP</title>
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
                <a href="/settings.php" class="text-gray-600 hover:text-gray-800">Settings</a>
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

        <!-- Log Controls -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <h2 class="text-lg font-medium text-gray-900">System Logs</h2>
                    
                    <!-- Log Type Selector -->
                    <div class="flex space-x-2">
                        <a href="?type=system&lines=<?php echo $lines; ?>" class="px-3 py-1 text-sm rounded-md <?php echo $log_type === 'system' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'; ?>">
                            System
                        </a>
                        <a href="?type=access&lines=<?php echo $lines; ?>" class="px-3 py-1 text-sm rounded-md <?php echo $log_type === 'access' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'; ?>">
                            Access
                        </a>
                        <a href="?type=error&lines=<?php echo $lines; ?>" class="px-3 py-1 text-sm rounded-md <?php echo $log_type === 'error' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'; ?>">
                            Error
                        </a>
                        <a href="?type=panel&lines=<?php echo $lines; ?>" class="px-3 py-1 text-sm rounded-md <?php echo $log_type === 'panel' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'; ?>">
                            Panel
                        </a>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Lines Selector -->
                    <select onchange="window.location.href='?type=<?php echo $log_type; ?>&lines=' + this.value" class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="50" <?php echo $lines === 50 ? 'selected' : ''; ?>>50 lines</option>
                        <option value="100" <?php echo $lines === 100 ? 'selected' : ''; ?>>100 lines</option>
                        <option value="200" <?php echo $lines === 200 ? 'selected' : ''; ?>>200 lines</option>
                        <option value="500" <?php echo $lines === 500 ? 'selected' : ''; ?>>500 lines</option>
                    </select>
                    
                    <!-- Clear Log Button -->
                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to clear this log file?')">
                        <input type="hidden" name="action" value="clear_log">
                        <button type="submit" class="bg-red-500 text-white px-3 py-1 text-sm rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                            Clear Log
                        </button>
                    </form>
                    
                    <!-- Refresh Button -->
                    <a href="?type=<?php echo $log_type; ?>&lines=<?php echo $lines; ?>" class="bg-blue-500 text-white px-3 py-1 text-sm rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                        Refresh
                    </a>
                </div>
            </div>
        </div>

        <!-- Log Content -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <?php echo ucfirst($log_type); ?> Log
                    <span class="text-sm font-normal text-gray-500">(<?php echo htmlspecialchars($log_file); ?>)</span>
                </h3>
            </div>
            
            <div class="p-6">
                <div class="bg-gray-900 text-green-400 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm font-mono"><?php echo htmlspecialchars($log_content); ?></pre>
                </div>
            </div>
        </div>

        <!-- Log Information -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">Log Types</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-700">
                <div>
                    <p><strong>System Log:</strong> General system messages and events</p>
                    <p><strong>Access Log:</strong> Web server access requests</p>
                </div>
                <div>
                    <p><strong>Error Log:</strong> Web server error messages</p>
                    <p><strong>Panel Log:</strong> LiteWP panel activity</p>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 