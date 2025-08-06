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
    
    if ($action === 'install_ssl') {
        $website_id = (int)($_POST['website_id'] ?? 0);
        $ssl_type = sanitize_input($_POST['ssl_type'] ?? 'letsencrypt');
        
        if ($website_id > 0) {
            // Get website info
            $stmt = $db->prepare('SELECT domain FROM websites WHERE id = :id');
            $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($website = $result->fetchArray(SQLITE3_ASSOC)) {
                $domain = $website['domain'];
                
                if ($ssl_type === 'letsencrypt') {
                    // Install Let's Encrypt SSL
                    $certbot_command = "certbot certonly --webroot -w /usr/local/litewp/websites/{$domain}/public_html -d {$domain} --non-interactive --agree-tos --email admin@{$domain}";
                    
                    $output = [];
                    $return_var = 0;
                    exec($certbot_command, $output, $return_var);
                    
                    if ($return_var === 0) {
                        // Update database
                        $stmt = $db->prepare('UPDATE websites SET ssl_enabled = 1 WHERE id = :id');
                        $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
                        
                        if ($stmt->execute()) {
                            $message = "SSL certificate installed successfully for {$domain}";
                            log_message("SSL installed for {$domain} by user {$user['username']}", 'INFO');
                        } else {
                            $error = 'Failed to update database';
                        }
                    } else {
                        $error = 'Failed to install SSL certificate';
                    }
                } else {
                    $error = 'Invalid SSL type';
                }
            } else {
                $error = 'Website not found';
            }
        } else {
            $error = 'Please select a website';
        }
    } elseif ($action === 'remove_ssl') {
        $website_id = (int)($_POST['website_id'] ?? 0);
        
        if ($website_id > 0) {
            // Get website info
            $stmt = $db->prepare('SELECT domain FROM websites WHERE id = :id');
            $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($website = $result->fetchArray(SQLITE3_ASSOC)) {
                $domain = $website['domain'];
                
                // Remove SSL certificate
                $certbot_command = "certbot delete --cert-name {$domain} --non-interactive";
                
                $output = [];
                $return_var = 0;
                exec($certbot_command, $output, $return_var);
                
                // Update database
                $stmt = $db->prepare('UPDATE websites SET ssl_enabled = 0 WHERE id = :id');
                $stmt->bindValue(':id', $website_id, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $message = "SSL certificate removed successfully for {$domain}";
                    log_message("SSL removed for {$domain} by user {$user['username']}", 'INFO');
                } else {
                    $error = 'Failed to update database';
                }
            } else {
                $error = 'Website not found';
            }
        } else {
            $error = 'Invalid website ID';
        }
    }
}

// Get websites without SSL
$stmt = $db->prepare('SELECT * FROM websites WHERE ssl_enabled = 0 ORDER BY created_at DESC');
$result = $stmt->execute();
$websites_without_ssl = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $websites_without_ssl[] = $row;
}

// Get websites with SSL
$stmt = $db->prepare('SELECT * FROM websites WHERE ssl_enabled = 1 ORDER BY created_at DESC');
$result = $stmt->execute();
$websites_with_ssl = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $websites_with_ssl[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL - LiteWP</title>
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
                <a href="/ssl.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">SSL</a>
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

        <!-- Install SSL -->
        <?php if (!empty($websites_without_ssl)): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Install SSL Certificate</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="install_ssl">
                
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
                            <?php foreach ($websites_without_ssl as $website): ?>
                                <option value="<?php echo $website['id']; ?>">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="ssl_type" class="block text-sm font-medium text-gray-700 mb-2">
                            SSL Type
                        </label>
                        <select 
                            id="ssl_type" 
                            name="ssl_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="letsencrypt">Let's Encrypt (Free)</option>
                            <option value="custom" disabled>Custom SSL (Coming Soon)</option>
                        </select>
                    </div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-blue-900 mb-2">Requirements</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Domain must point to this server</li>
                        <li>• Website must be accessible via HTTP</li>
                        <li>• Port 80 must be open for verification</li>
                    </ul>
                </div>
                
                <button 
                    type="submit"
                    class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Install SSL Certificate
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- SSL Certificates -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">SSL Certificates</h2>
            </div>
            
            <?php if (empty($websites_with_ssl)): ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-500">No SSL certificates found. Install SSL on a website above.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SSL Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($websites_with_ssl as $website): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    Let's Encrypt
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="https://<?php echo htmlspecialchars($website['domain']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Visit HTTPS</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this SSL certificate?')">
                                        <input type="hidden" name="action" value="remove_ssl">
                                        <input type="hidden" name="website_id" value="<?php echo $website['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- SSL Information -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">About SSL Certificates</h3>
            <p class="text-sm text-blue-700 mb-3">
                SSL certificates encrypt the connection between your website and visitors, providing security and trust. 
                Let's Encrypt provides free SSL certificates that are automatically renewed.
            </p>
            <div class="text-sm text-blue-700 space-y-1">
                <p><strong>Benefits:</strong></p>
                <ul class="list-disc list-inside space-y-1 ml-4">
                    <li>Secure HTTPS connection</li>
                    <li>Better search engine ranking</li>
                    <li>Increased visitor trust</li>
                    <li>Free and automatic renewal</li>
                </ul>
            </div>
        </div>
    </main>
</body>
</html> 