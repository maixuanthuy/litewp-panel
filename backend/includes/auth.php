<?php
require_once 'config.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new SQLite3(DB_PATH);
    }
    
    public function login($username, $password) {
        $username = sanitize_input($username);
        
        // Check login attempts
        if ($this->is_locked_out()) {
            error_response('Too many login attempts. Please try again later.');
        }
        
        // Get user from database
        $stmt = $this->db->prepare('SELECT id, username, password FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            if (password_verify($password, $user['password'])) {
                // Reset login attempts
                $this->reset_login_attempts();
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_time'] = time();
                
                log_message("User {$username} logged in successfully", 'INFO');
                
                return true;
            }
        }
        
        // Increment login attempts
        $this->increment_login_attempts();
        
        log_message("Failed login attempt for user {$username}", 'WARNING');
        return false;
    }
    
    public function logout() {
        session_destroy();
        log_message("User logged out", 'INFO');
    }
    
    public function is_logged_in() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        // Update login time
        $_SESSION['login_time'] = time();
        
        return true;
    }
    
    public function require_login() {
        if (!$this->is_logged_in()) {
            if (is_ajax_request()) {
                error_response('Authentication required', 401);
            } else {
                header('Location: /login.php');
                exit;
            }
        }
    }
    
    public function get_current_user() {
        if (!$this->is_logged_in()) {
            return null;
        }
        
        $stmt = $this->db->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
        $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function change_password($user_id, $current_password, $new_password) {
        // Verify current password
        $stmt = $this->db->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($user = $result->fetchArray(SQLITE3_ASSOC)) {
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $stmt = $this->db->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    log_message("Password changed for user ID {$user_id}", 'INFO');
                    return true;
                }
            }
        }
        
        return false;
    }
    
    private function increment_login_attempts() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_file = LOGS_ROOT . '/login_attempts.txt';
        
        $attempts = [];
        if (file_exists($attempts_file)) {
            $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
        }
        
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = ['count' => 0, 'time' => time()];
        }
        
        $attempts[$ip]['count']++;
        $attempts[$ip]['time'] = time();
        
        file_put_contents($attempts_file, json_encode($attempts));
    }
    
    private function reset_login_attempts() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_file = LOGS_ROOT . '/login_attempts.txt';
        
        if (file_exists($attempts_file)) {
            $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
            unset($attempts[$ip]);
            file_put_contents($attempts_file, json_encode($attempts));
        }
    }
    
    private function is_locked_out() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $attempts_file = LOGS_ROOT . '/login_attempts.txt';
        
        if (!file_exists($attempts_file)) {
            return false;
        }
        
        $attempts = json_decode(file_get_contents($attempts_file), true) ?: [];
        
        if (isset($attempts[$ip])) {
            $attempt = $attempts[$ip];
            
            // Check if within timeout period
            if (time() - $attempt['time'] < LOGIN_TIMEOUT) {
                return $attempt['count'] >= MAX_LOGIN_ATTEMPTS;
            } else {
                // Reset if timeout has passed
                unset($attempts[$ip]);
                file_put_contents($attempts_file, json_encode($attempts));
            }
        }
        
        return false;
    }
} 