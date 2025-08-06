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
    
    if ($action === 'install_wordpress') {
        $website_id = (int)($_POST['website_id'] ?? 0);
        $wp_title = sanitize_input($_POST['wp_title'] ?? 'My WordPress Site');
        $wp_user = sanitize_input($_POST['wp_user'] ?? 'admin');
        $wp_password = $_POST['wp_password'] ?? '';
        $wp_email = sanitize_input($_POST['wp_email'] ?? 'admin@example.com');
        
        if ($website_id > 0 && !empty($wp_password)) {
            // Get website info
            $stmt = $db->prepare('SELECT domain, document_root FROM websites WHERE id = :id');
            $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($website = $result->fetchArray(SQLITE3_ASSOC)) {
                $wp_path = $website['document_root'];
                
                // Download WordPress
                $wp_url = 'https://wordpress.org/latest.zip';
                $wp_zip = '/tmp/wordpress.zip';
                
                if (file_put_contents($wp_zip, file_get_contents($wp_url))) {
                    // Extract WordPress
                    $zip = new ZipArchive;
                    if ($zip->open($wp_zip) === TRUE) {
                        $zip->extractTo($wp_path);
                        $zip->close();
                        
                        // Move files from wordpress subdirectory
                        $wp_subdir = $wp_path . '/wordpress';
                        if (is_dir($wp_subdir)) {
                            $files = scandir($wp_subdir);
                            foreach ($files as $file) {
                                if ($file != '.' && $file != '..') {
                                    rename($wp_subdir . '/' . $file, $wp_path . '/' . $file);
                                }
                            }
                            rmdir($wp_subdir);
                        }
                        
                        // Create wp-config.php
                        $wp_config_content = "<?php
define('DB_NAME', 'wp_{$website['domain']}');
define('DB_USER', 'wp_{$website['domain']}');
define('DB_PASSWORD', '" . bin2hex(random_bytes(16)) . "');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('AUTH_KEY',         '" . bin2hex(random_bytes(32)) . "');
define('SECURE_AUTH_KEY',  '" . bin2hex(random_bytes(32)) . "');
define('LOGGED_IN_KEY',    '" . bin2hex(random_bytes(32)) . "');
define('NONCE_KEY',        '" . bin2hex(random_bytes(32)) . "');
define('AUTH_SALT',        '" . bin2hex(random_bytes(32)) . "');
define('SECURE_AUTH_SALT', '" . bin2hex(random_bytes(32)) . "');
define('LOGGED_IN_SALT',   '" . bin2hex(random_bytes(32)) . "');
define('NONCE_SALT',       '" . bin2hex(random_bytes(32)) . "');

\$table_prefix = 'wp_';

define('WP_DEBUG', false);

if ( !defined('ABSPATH') )
    define('ABSPATH', dirname(__FILE__) . '/');

require_once ABSPATH . 'wp-settings.php';
";
                        
                        if (file_put_contents($wp_path . '/wp-config.php', $wp_config_content)) {
                            // Update database
                            $stmt = $db->prepare('UPDATE websites SET wordpress_installed = 1 WHERE id = :id');
                            $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
                            
                            if ($stmt->execute()) {
                                $message = "WordPress installed successfully on {$website['domain']}";
                                log_message("WordPress installed on {$website['domain']} by user {$user['username']}", 'INFO');
                            } else {
                                $error = 'Failed to update database';
                            }
                        } else {
                            $error = 'Failed to create wp-config.php';
                        }
                    } else {
                        $error = 'Failed to extract WordPress';
                    }
                } else {
                    $error = 'Failed to download WordPress';
                }
                
                // Clean up
                if (file_exists($wp_zip)) {
                    unlink($wp_zip);
                }
            } else {
                $error = 'Website not found';
            }
        } else {
            $error = 'Please fill all required fields';
        }
    }
}

// Get websites without WordPress
$stmt = $db->prepare('SELECT * FROM websites WHERE wordpress_installed = 0 ORDER BY created_at DESC');
$result = $stmt->execute();
$websites_without_wp = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $websites_without_wp[] = $row;
}

// Get websites with WordPress
$stmt = $db->prepare('SELECT * FROM websites WHERE wordpress_installed = 1 ORDER BY created_at DESC');
$result = $stmt->execute();
$websites_with_wp = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $websites_with_wp[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress - LiteWP</title>
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
                <a href="/wordpress.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">WordPress</a>
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

        <!-- Install WordPress -->
        <?php if (!empty($websites_without_wp)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Install WordPress</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="install_wordpress">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="website_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Select Website
                        </label>
                        <select 
                            id="website_id" 
                            name="website_id"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Choose a website...</option>
                            <?php foreach ($websites_without_wp as $website): ?>
                                <option value="<?php echo $website['id']; ?>">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="wp_title" class="block text-sm font-medium text-gray-700 mb-2">
                            Site Title
                        </label>
                        <input 
                            type="text" 
                            id="wp_title" 
                            name="wp_title" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="My WordPress Site"
                        >
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="wp_user" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Username
                        </label>
                        <input 
                            type="text" 
                            id="wp_user" 
                            name="wp_user" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="admin"
                        >
                    </div>
                    
                    <div>
                        <label for="wp_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Password
                        </label>
                        <input 
                            type="password" 
                            id="wp_password" 
                            name="wp_password" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter password"
                        >
                    </div>
                    
                    <div>
                        <label for="wp_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Email
                        </label>
                        <input 
                            type="email" 
                            id="wp_email" 
                            name="wp_email" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="admin@example.com"
                        >
                    </div>
                </div>
                
                <button 
                    type="submit"
                    class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Install WordPress
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- WordPress Sites -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">WordPress Sites</h2>
            </div>
            
            <?php if (empty($websites_with_wp)): ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-500">No WordPress sites found. Install WordPress on a website above.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PHP Version</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($websites_with_wp as $website): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($website['php_version']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Installed
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="http://<?php echo htmlspecialchars($website['domain']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Visit Site</a>
                                    <a href="http://<?php echo htmlspecialchars($website['domain']); ?>/wp-admin" target="_blank" class="text-green-600 hover:text-green-900">Admin</a>
                                    <a href="/file-manager.php?domain=<?php echo urlencode($website['domain']); ?>" class="text-purple-600 hover:text-purple-900">Files</a>
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