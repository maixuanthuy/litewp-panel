<?php
require_once '../backend/includes/config.php';
require_once '../backend/includes/auth.php';

$auth = new Auth();
$auth->require_login();

$user = $auth->get_current_user();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_rule') {
        $port = sanitize_input($_POST['port'] ?? '');
        $protocol = sanitize_input($_POST['protocol'] ?? 'tcp');
        $action_type = sanitize_input($_POST['action_type'] ?? 'allow');
        
        if (empty($port) || !is_numeric($port)) {
            $error = 'Please enter a valid port number';
        } else {
            $ufw_command = "ufw {$action_type} {$port}/{$protocol}";
            
            $output = [];
            $return_var = 0;
            exec($ufw_command, $output, $return_var);
            
            if ($return_var === 0) {
                $message = "Firewall rule added successfully: {$action_type} {$port}/{$protocol}";
                log_message("Firewall rule added: {$action_type} {$port}/{$protocol} by user {$user['username']}", 'INFO');
            } else {
                $error = 'Failed to add firewall rule';
            }
        }
    } elseif ($action === 'remove_rule') {
        $rule_number = (int)($_POST['rule_number'] ?? 0);
        
        if ($rule_number > 0) {
            $ufw_command = "ufw delete {$rule_number}";
            
            $output = [];
            $return_var = 0;
            exec($ufw_command, $output, $return_var);
            
            if ($return_var === 0) {
                $message = "Firewall rule removed successfully";
                log_message("Firewall rule removed by user {$user['username']}", 'INFO');
            } else {
                $error = 'Failed to remove firewall rule';
            }
        } else {
            $error = 'Invalid rule number';
        }
    } elseif ($action === 'enable_firewall') {
        $ufw_command = "ufw --force enable";
        
        $output = [];
        $return_var = 0;
        exec($ufw_command, $output, $return_var);
        
        if ($return_var === 0) {
            $message = "Firewall enabled successfully";
            log_message("Firewall enabled by user {$user['username']}", 'INFO');
        } else {
            $error = 'Failed to enable firewall';
        }
    } elseif ($action === 'disable_firewall') {
        $ufw_command = "ufw disable";
        
        $output = [];
        $return_var = 0;
        exec($ufw_command, $output, $return_var);
        
        if ($return_var === 0) {
            $message = "Firewall disabled successfully";
            log_message("Firewall disabled by user {$user['username']}", 'INFO');
        } else {
            $error = 'Failed to disable firewall';
        }
    }
}

// Get firewall status
$ufw_status_command = "ufw status numbered";
$output = [];
$return_var = 0;
exec($ufw_status_command, $output, $return_var);

$firewall_status = 'Unknown';
$firewall_rules = [];

if ($return_var === 0) {
    foreach ($output as $line) {
        if (strpos($line, 'Status:') !== false) {
            if (strpos($line, 'active') !== false) {
                $firewall_status = 'Active';
            } else {
                $firewall_status = 'Inactive';
            }
        } elseif (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $matches)) {
            $firewall_rules[] = [
                'number' => $matches[1],
                'rule' => trim($matches[2])
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firewall - LiteWP</title>
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
                <a href="/firewall.php" class="text-blue-600 border-b-2 border-blue-600 pb-2">Firewall</a>
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

        <!-- Firewall Status -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium text-gray-900">Firewall Status</h2>
                <div class="flex space-x-2">
                    <?php if ($firewall_status === 'Active'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="disable_firewall">
                            <button type="submit" class="bg-red-500 text-white py-2 px-4 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                                Disable Firewall
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="enable_firewall">
                            <button type="submit" class="bg-green-500 text-white py-2 px-4 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                Enable Firewall
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <?php if ($firewall_status === 'Active'): ?>
                        <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                    <?php else: ?>
                        <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">
                        Status: <?php echo htmlspecialchars($firewall_status); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Firewall Rule -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Add Firewall Rule</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_rule">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="port" class="block text-sm font-medium text-gray-700 mb-2">
                            Port
                        </label>
                        <input 
                            type="number" 
                            id="port" 
                            name="port" 
                            required
                            min="1"
                            max="65535"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="80"
                        >
                    </div>
                    
                    <div>
                        <label for="protocol" class="block text-sm font-medium text-gray-700 mb-2">
                            Protocol
                        </label>
                        <select 
                            id="protocol" 
                            name="protocol"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="tcp">TCP</option>
                            <option value="udp">UDP</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-gray-700 mb-2">
                            Action
                        </label>
                        <select 
                            id="action_type" 
                            name="action_type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="allow">Allow</option>
                            <option value="deny">Deny</option>
                        </select>
                    </div>
                </div>
                
                <button 
                    type="submit"
                    class="bg-blue-500 text-white py-2 px-4 rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
                >
                    Add Rule
                </button>
            </form>
        </div>

        <!-- Firewall Rules -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Firewall Rules</h2>
            </div>
            
            <?php if (empty($firewall_rules)): ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-gray-500">No firewall rules found.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rule #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rule</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($firewall_rules as $rule): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($rule['number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($rule['rule']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this firewall rule?')">
                                        <input type="hidden" name="action" value="remove_rule">
                                        <input type="hidden" name="rule_number" value="<?php echo $rule['number']; ?>">
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

        <!-- Common Ports -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-900 mb-2">Common Ports</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-700">
                <div>
                    <p><strong>Web Services:</strong></p>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>80 - HTTP</li>
                        <li>443 - HTTPS</li>
                        <li>8080 - Alternative HTTP</li>
                    </ul>
                </div>
                <div>
                    <p><strong>Other Services:</strong></p>
                    <ul class="list-disc list-inside space-y-1 ml-4">
                        <li>22 - SSH</li>
                        <li>21 - FTP</li>
                        <li>3306 - MySQL/MariaDB</li>
                        <li>6379 - Redis</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 