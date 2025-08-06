<?php
require_once '../backend/includes/config.php';
require_once '../backend/includes/auth.php';

$auth = new Auth();
$auth->require_login();

$user = $auth->get_current_user();

// Get system information
function get_system_info() {
    $info = [];
    
    // CPU usage
    $load = sys_getloadavg();
    $info['cpu_load'] = $load[0];
    
    // Memory usage
    $mem_info = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)/', $mem_info, $total);
    preg_match('/MemAvailable:\s+(\d+)/', $mem_info, $available);
    
    $total_mem = $total[1] ?? 0;
    $available_mem = $available[1] ?? 0;
    $used_mem = $total_mem - $available_mem;
    $info['memory_usage'] = round(($used_mem / $total_mem) * 100, 2);
    $info['memory_total'] = round($total_mem / 1024 / 1024, 2); // GB
    $info['memory_used'] = round($used_mem / 1024 / 1024, 2); // GB
    
    // Disk usage
    $disk_total = disk_total_space('/');
    $disk_free = disk_free_space('/');
    $disk_used = $disk_total - $disk_free;
    $info['disk_usage'] = round(($disk_used / $disk_total) * 100, 2);
    $info['disk_total'] = round($disk_total / 1024 / 1024 / 1024, 2); // GB
    $info['disk_used'] = round($disk_used / 1024 / 1024 / 1024, 2); // GB
    
    // Uptime
    $uptime = file_get_contents('/proc/uptime');
    $uptime_seconds = explode(' ', $uptime)[0];
    $info['uptime'] = gmdate('H:i:s', $uptime_seconds);
    
    return $info;
}

$system_info = get_system_info();

// Get website count
$db = new SQLite3(DB_PATH);
$stmt = $db->prepare('SELECT COUNT(*) as count FROM websites');
$result = $stmt->execute();
$website_count = $result->fetchArray(SQLITE3_ASSOC)['count'];

// Get recent websites
$stmt = $db->prepare('SELECT * FROM websites ORDER BY created_at DESC LIMIT 5');
$result = $stmt->execute();
$recent_websites = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recent_websites[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LiteWP</title>
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
                <a href="/dashboard.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">Dashboard</a>
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
        <!-- System Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">CPU Load</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $system_info['cpu_load']; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Memory Usage</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $system_info['memory_usage']; ?>%</p>
                        <p class="text-sm text-gray-500"><?php echo $system_info['memory_used']; ?>GB / <?php echo $system_info['memory_total']; ?>GB</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Disk Usage</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $system_info['disk_usage']; ?>%</p>
                        <p class="text-sm text-gray-500"><?php echo $system_info['disk_used']; ?>GB / <?php echo $system_info['disk_total']; ?>GB</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Uptime</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $system_info['uptime']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Stats</h3>
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Websites</span>
                        <span class="font-semibold"><?php echo $website_count; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Panel Version</span>
                        <span class="font-semibold"><?php echo PANEL_VERSION; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">PHP Version</span>
                        <span class="font-semibold"><?php echo DEFAULT_PHP_VERSION; ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="/websites.php?action=add" class="block w-full bg-blue-500 text-white text-center py-2 px-4 rounded hover:bg-blue-600 transition-colors">
                        Add New Website
                    </a>
                    <a href="/wordpress.php" class="block w-full bg-green-500 text-white text-center py-2 px-4 rounded hover:bg-green-600 transition-colors">
                        Manage WordPress
                    </a>
                    <a href="/ssl.php" class="block w-full bg-yellow-500 text-white text-center py-2 px-4 rounded hover:bg-yellow-600 transition-colors">
                        SSL Certificates
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Websites -->
        <?php if (!empty($recent_websites)): ?>
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Recent Websites</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WordPress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_websites as $website): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($website['domain']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    Active
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($website['wordpress_installed']): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Installed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                        Not Installed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($website['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="/websites.php?domain=<?php echo urlencode($website['domain']); ?>" class="text-blue-600 hover:text-blue-900">Manage</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html> 