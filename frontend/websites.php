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
    
    if ($action === 'add_website') {
        $domain = sanitize_input($_POST['domain'] ?? '');
        $php_version = sanitize_input($_POST['php_version'] ?? '8.3');
        
        if (empty($domain)) {
            $error = 'Domain name is required';
        } elseif (!validate_domain($domain)) {
            $error = 'Invalid domain name';
        } else {
            // Check if domain already exists
            $stmt = $db->prepare('SELECT id FROM websites WHERE domain = :domain');
            $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                $error = 'Domain already exists';
            } else {
                // Create website directory
                $website_dir = WEBSITES_ROOT . '/' . $domain;
                $public_html = $website_dir . '/public_html';
                $logs_dir = $website_dir . '/logs';
                $backups_dir = $website_dir . '/backups';
                
                if (!mkdir($public_html, 0755, true) || 
                    !mkdir($logs_dir, 0755, true) || 
                    !mkdir($backups_dir, 0755, true)) {
                    $error = 'Failed to create website directories';
                } else {
                    // Insert into database
                    $stmt = $db->prepare('INSERT INTO websites (domain, document_root, php_version) VALUES (:domain, :document_root, :php_version)');
                    $stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
                    $stmt->bindValue(':document_root', $public_html, SQLITE3_TEXT);
                    $stmt->bindValue(':php_version', $php_version, SQLITE3_TEXT);
                    
                    if ($stmt->execute()) {
                        $message = "Website '{$domain}' created successfully";
                        log_message("Website '{$domain}' created by user {$user['username']}", 'INFO');
                    } else {
                        $error = 'Failed to create website in database';
                    }
                }
            }
        }
    } elseif ($action === 'delete_website') {
        $website_id = (int)($_POST['website_id'] ?? 0);
        
        if ($website_id > 0) {
            // Get website info
            $stmt = $db->prepare('SELECT domain, document_root FROM websites WHERE id = :id');
            $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($website = $result->fetchArray(SQLITE3_ASSOC)) {
                // Delete from database
                $stmt = $db->prepare('DELETE FROM websites WHERE id = :id');
                $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    // Remove directory
                    $website_dir = dirname($website['document_root']);
                    if (is_dir($website_dir)) {
                        exec("rm -rf " . escapeshellarg($website_dir));
                    }
                    
                    $message = "Website '{$website['domain']}' deleted successfully";
                    log_message("Website '{$website['domain']}' deleted by user {$user['username']}", 'INFO');
                } else {
                    $error = 'Failed to delete website from database';
                }
            } else {
                $error = 'Website not found';
            }
        } else {
            $error = 'Invalid website ID';
        }
    }
}

// Get all websites
$stmt = $db->prepare('SELECT * FROM websites ORDER BY created_at DESC');
$result = $stmt->execute();
$websites = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $websites[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Websites - LiteWP</title>
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
                <a href="/websites.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">Websites</a>
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

        <!-- Add Website Form -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Add New Website</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_website">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="domain" class="block text-sm font-medium text-gray-700 mb-2">
                            Domain Name
                        </label>
                        <input 
                            type="text" 
                            id="domain" 
                            name="domain" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="example.com"
                        >
                    </div>
                    
                    <div>
                        <label for="php_version" class="block text-sm font-medium text-gray-700 mb-2">
                            PHP Version
                        </label>
                        <select 
                            id="php_version" 
                            name="php_version"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="8.3">PHP 8.3</option>
                            <option value="8.2">PHP 8.2</option>
                            <option value="8.1">PHP 8.1</option>
                        </select>
                    </div>
                </div>
                
                <button 
                    type="submit"
                    class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Create Website
                </button>
            </form>
        </div>

        <!-- Websites List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Your Websites</h2>
            </div>
            
            <?php if (empty($websites)): ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-500">No websites found. Create your first website above.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PHP Version</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WordPress</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SSL</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($websites as $website): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($website['php_version']); ?>
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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($website['ssl_enabled']): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Enabled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Disabled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($website['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="/website-manage.php?id=<?php echo $website['id']; ?>" class="text-blue-600 hover:text-blue-900">Manage</a>
                                    <a href="/file-manager.php?domain=<?php echo urlencode($website['domain']); ?>" class="text-green-600 hover:text-green-900">Files</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this website?')">
                                        <input type="hidden" name="action" value="delete_website">
                                        <input type="hidden" name="website_id" value="<?php echo $website['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html> 