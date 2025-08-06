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
    
    if ($action === 'create_database') {
        $db_name = sanitize_input($_POST['db_name'] ?? '');
        $db_user = sanitize_input($_POST['db_user'] ?? '');
        $db_password = $_POST['db_password'] ?? '';
        $website_id = (int)($_POST['website_id'] ?? 0);
        
        if (empty($db_name) || empty($db_user) || empty($db_password)) {
            $error = 'Please fill all required fields';
        } else {
            // Check if database already exists
            $stmt = $db->prepare('SELECT id FROM databases WHERE name = :name');
            $stmt->bindValue(':name', $db_name, SQLITE3_TEXT);
            $result = $stmt->execute();
            
            if ($result->fetchArray()) {
                $error = 'Database name already exists';
            } else {
                // Create database in MariaDB
                $mysql_command = "mysql -u root -e \"CREATE DATABASE IF NOT EXISTS {$db_name}; CREATE USER IF NOT EXISTS '{$db_user}'@'localhost' IDENTIFIED BY '{$db_password}'; GRANT ALL PRIVILEGES ON {$db_name}.* TO '{$db_user}'@'localhost'; FLUSH PRIVILEGES;\"";
                
                $output = [];
                $return_var = 0;
                exec($mysql_command, $output, $return_var);
                
                if ($return_var === 0) {
                    // Insert into panel database
                    $stmt = $db->prepare('INSERT INTO databases (name, username, password, website_id) VALUES (:name, :username, :password, :website_id)');
                    $stmt->bindValue(':name', $db_name, SQLITE3_TEXT);
                    $stmt->bindValue(':username', $db_user, SQLITE3_TEXT);
                    $stmt->bindValue(':password', $db_password, SQLITE3_TEXT);
                    $stmt->bindValue(':website_id', $website_id > 0 ? $website_id : null, $website_id > 0 ? SQLITE3_INTEGER : SQLITE3_NULL);
                    
                    if ($stmt->execute()) {
                        $message = "Database '{$db_name}' created successfully";
                        log_message("Database '{$db_name}' created by user {$user['username']}", 'INFO');
                    } else {
                        $error = 'Failed to save database info to panel';
                    }
                } else {
                    $error = 'Failed to create database in MariaDB';
                }
            }
        }
    } elseif ($action === 'delete_database') {
        $db_id = (int)($_POST['db_id'] ?? 0);
        
        if ($db_id > 0) {
            // Get database info
            $stmt = $db->prepare('SELECT name, username FROM databases WHERE id = :id');
            $stmt->bindValue(':id', $db_id, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($database = $result->fetchArray(SQLITE3_ASSOC)) {
                // Drop database and user in MariaDB
                $mysql_command = "mysql -u root -e \"DROP DATABASE IF EXISTS {$database['name']}; DROP USER IF EXISTS '{$database['username']}'@'localhost'; FLUSH PRIVILEGES;\"";
                
                $output = [];
                $return_var = 0;
                exec($mysql_command, $output, $return_var);
                
                // Delete from panel database
                $stmt = $db->prepare('DELETE FROM databases WHERE id = :id');
                $stmt->bindValue(':id', $db_id, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $message = "Database '{$database['name']}' deleted successfully";
                    log_message("Database '{$database['name']}' deleted by user {$user['username']}", 'INFO');
                } else {
                    $error = 'Failed to delete database from panel';
                }
            } else {
                $error = 'Database not found';
            }
        } else {
            $error = 'Invalid database ID';
        }
    }
}

// Get all databases
$stmt = $db->prepare('SELECT d.*, w.domain as website_domain FROM databases d LEFT JOIN websites w ON d.website_id = w.id ORDER BY d.created_at DESC');
$result = $stmt->execute();
$databases = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $databases[] = $row;
}

// Get websites for dropdown
$stmt = $db->prepare('SELECT id, domain FROM websites ORDER BY domain');
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
    <title>Database - LiteWP</title>
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
                <a href="/database.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">Database</a>
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

        <!-- Create Database -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Create New Database</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_database">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="db_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Name
                        </label>
                        <input 
                            type="text" 
                            id="db_name" 
                            name="db_name" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="mydatabase"
                        >
                    </div>
                    
                    <div>
                        <label for="website_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Associated Website (Optional)
                        </label>
                        <select 
                            id="website_id" 
                            name="website_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">No website (standalone database)</option>
                            <?php foreach ($websites as $website): ?>
                                <option value="<?php echo $website['id']; ?>">
                                    <?php echo htmlspecialchars($website['domain']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="db_user" class="block text-sm font-medium text-gray-700 mb-2">
                            Database User
                        </label>
                        <input 
                            type="text" 
                            id="db_user" 
                            name="db_user" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="dbuser"
                        >
                    </div>
                    
                    <div>
                        <label for="db_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Database Password
                        </label>
                        <input 
                            type="password" 
                            id="db_password" 
                            name="db_password" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Enter password"
                        >
                    </div>
                </div>
                
                <button 
                    type="submit"
                    class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Create Database
                </button>
            </form>
        </div>

        <!-- Databases List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Your Databases</h2>
            </div>
            
            <?php if (empty($databases)): ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-500">No databases found. Create your first database above.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Database Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Website</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($databases as $database): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($database['name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($database['username']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($database['website_domain']): ?>
                                        <?php echo htmlspecialchars($database['website_domain']); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Standalone</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($database['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="/tools/adminer.php?server=localhost&username=<?php echo urlencode($database['username']); ?>&db=<?php echo urlencode($database['name']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Manage</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this database? This action cannot be undone.')">
                                        <input type="hidden" name="action" value="delete_database">
                                        <input type="hidden" name="db_id" value="<?php echo $database['id']; ?>">
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

        <!-- Adminer Link -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">Database Management</h3>
            <p class="text-sm text-blue-700 mb-3">
                Use Adminer to manage your databases with a web-based interface.
            </p>
            <a href="/tools/adminer.php" target="_blank" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Open Adminer
            </a>
        </div>
    </main>
</body>
</html> 