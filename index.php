<?php
// index.php - Brewery Finder - FIXED DATA VERSION

// ==================== SECURITY CONFIGURATION ====================
define('MAX_SEARCH_LENGTH', 50);
define('MAX_API_RESULTS', 100);
define('RATE_LIMIT_REQUESTS', 10);
define('RATE_LIMIT_TIME', 60);

// ==================== SESSION SECURITY ====================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

// ==================== SECURITY HEADERS ====================
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==================== USER AUTHENTICATION SYSTEM ====================
class UserAuth {
    private $users_file;
    
    public function __construct() {
        $this->users_file = __DIR__ . '/data/users.json';
        $this->ensureDataDirectory();
    }
    
    private function ensureDataDirectory() {
        $dir = dirname($this->users_file);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: $dir");
            }
        }
        if (!file_exists($this->users_file)) {
            file_put_contents($this->users_file, json_encode([]));
            chmod($this->users_file, 0644);
        }
    }
    
    public function isStrongPassword($password) {
        if (strlen($password) < 8) return false;
        if (!preg_match('/[A-Z]/', $password)) return false;
        if (!preg_match('/[a-z]/', $password)) return false;
        if (!preg_match('/[0-9]/', $password)) return false;
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) return false;
        return true;
    }
    
    public function register($username, $email, $password) {
        $users = $this->getUsers();
        
        // Check if user already exists
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            if ($user['email'] === $email) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
        }
        
        // Validate username
        if (strlen($username) < 3) {
            return ['success' => false, 'message' => 'Username must be at least 3 characters long'];
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
        }
        
        // Validate password strength
        if (!$this->isStrongPassword($password)) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters with uppercase letters, lowercase letters, numbers, and symbols.'];
        }
        
        // Create new user
        $new_user = [
            'id' => uniqid(),
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
            'last_login' => null,
            'is_active' => true
        ];
        
        $users[] = $new_user;
        if ($this->saveUsers($users)) {
            return ['success' => true, 'message' => 'Registration successful! Welcome to BrewFinder!'];
        } else {
            return ['success' => false, 'message' => 'Failed to save user data.'];
        }
    }
    
    public function login($username, $password) {
        $users = $this->getUsers();
        
        foreach ($users as $user) {
            if (($user['username'] === $username || $user['email'] === $username) && $user['is_active']) {
                if (password_verify($password, $user['password'])) {
                    // Update last login
                    $user['last_login'] = date('Y-m-d H:i:s');
                    $this->updateUser($user);
                    
                    // Set session
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'created_at' => $user['created_at']
                    ];
                    
                    return ['success' => true, 'message' => 'Login successful! Welcome back!'];
                }
            }
        }
        
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    public function logout() {
        unset($_SESSION['user']);
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user']);
    }
    
    public function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }
    
    private function getUsers() {
        if (!file_exists($this->users_file)) {
            return [];
        }
        $data = file_get_contents($this->users_file);
        if ($data === false) {
            error_log("Failed to read users file");
            return [];
        }
        $users = json_decode($data, true);
        return is_array($users) ? $users : [];
    }
    
    private function saveUsers($users) {
        $result = file_put_contents($this->users_file, json_encode($users, JSON_PRETTY_PRINT));
        if ($result === false) {
            error_log("Failed to write to users file");
            return false;
        }
        return true;
    }
    
    private function updateUser($updated_user) {
        $users = $this->getUsers();
        foreach ($users as &$user) {
            if ($user['id'] === $updated_user['id']) {
                $user = $updated_user;
                break;
            }
        }
        $this->saveUsers($users);
    }
}

// Initialize authentication system
$auth = new UserAuth();

// ==================== PROCESS AUTHENTICATION FORMS ====================
$auth_message = '';
$auth_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'register':
            $username = trim($_POST['username'] ?? '');
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($username) || empty($email) || empty($password)) {
                $auth_message = 'All fields are required';
            } elseif (!$email) {
                $auth_message = 'Invalid email address';
            } elseif ($password !== $confirm_password) {
                $auth_message = 'Passwords do not match';
            } else {
                $result = $auth->register($username, $email, $password);
                $auth_message = $result['message'];
                $auth_success = $result['success'];
                
                if ($auth_success) {
                    // Auto-login after successful registration
                    $login_result = $auth->login($username, $password);
                    if (!$login_result['success']) {
                        $auth_message = 'Registration successful but auto-login failed. Please login manually.';
                    }
                }
            }
            break;
            
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $auth_message = 'Username and password are required';
            } else {
                $result = $auth->login($username, $password);
                $auth_message = $result['message'];
                $auth_success = $result['success'];
            }
            break;
            
        case 'logout':
            $result = $auth->logout();
            $auth_message = $result['message'];
            $auth_success = $result['success'];
            break;
    }
}

// Determine current page
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// API Configuration
define('BREWERY_API_BASE', 'https://api.openbrewerydb.org/v1/breweries');

// Function to fetch breweries from API with better error handling
function fetchBreweriesFromAPI($params = []) {
    $defaultParams = ['per_page' => 20]; // Reduced for better performance
    $queryParams = array_merge($defaultParams, $params);
    
    $apiUrl = BREWERY_API_BASE . '?' . http_build_query($queryParams);
    
    // Try multiple methods to fetch data
    $response = null;
    
    // Method 1: cURL
    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false, // More lenient for local development
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT => 'BrewFinder/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : [];
        }
    }
    
    // Method 2: file_get_contents with context
    if (ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'user_agent' => 'BrewFinder/1.0'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        if ($response !== false) {
            $data = json_decode($response, true);
            return is_array($data) ? $data : [];
        }
    }
    
    // Return sample data if API fails
    return getSampleBreweries();
}

// Sample data fallback
function getSampleBreweries() {
    return [
        [
            'id' => '1',
            'name' => 'Sample Micro Brewery',
            'brewery_type' => 'micro',
            'street' => '123 Beer Street',
            'city' => 'Portland',
            'state' => 'Oregon',
            'country' => 'United States',
            'website_url' => 'https://example.com',
            'phone' => '555-0123'
        ],
        [
            'id' => '2', 
            'name' => 'Local Brewpub',
            'brewery_type' => 'brewpub',
            'street' => '456 Ale Avenue',
            'city' => 'Denver',
            'state' => 'Colorado',
            'country' => 'United States',
            'website_url' => 'https://example.com',
            'phone' => '555-0456'
        ],
        [
            'id' => '3',
            'name' => 'Regional Beer Co.',
            'brewery_type' => 'regional',
            'street' => '789 Lager Lane',
            'city' => 'San Diego',
            'state' => 'California',
            'country' => 'United States',
            'website_url' => 'https://example.com',
            'phone' => '555-0789'
        ]
    ];
}

function searchBreweriesAPI($name = '', $city = '', $state = '', $type = '') {
    $params = [];
    if (!empty($name)) $params['by_name'] = $name;
    if (!empty($city)) $params['by_city'] = $city;
    if (!empty($state)) $params['by_state'] = $state;
    if (!empty($type)) $params['by_type'] = $type;
    
    $result = fetchBreweriesFromAPI($params);
    
    // If no results from API, try to filter sample data
    if (empty($result)) {
        $sampleData = getSampleBreweries();
        $result = array_filter($sampleData, function($brewery) use ($name, $city, $state, $type) {
            $match = true;
            if (!empty($name)) {
                $match = $match && stripos($brewery['name'], $name) !== false;
            }
            if (!empty($city)) {
                $match = $match && stripos($brewery['city'], $city) !== false;
            }
            if (!empty($state)) {
                $match = $match && stripos($brewery['state'], $state) !== false;
            }
            if (!empty($type)) {
                $match = $match && $brewery['brewery_type'] === $type;
            }
            return $match;
        });
    }
    
    return array_values($result);
}

function getRandomBreweries($limit = 8) {
    $breweries = fetchBreweriesFromAPI(['per_page' => $limit, 'sort' => 'random']);
    if (empty($breweries)) {
        $breweries = getSampleBreweries();
    }
    return array_slice($breweries, 0, $limit);
}

function getBreweryTypes() {
    return [
        'micro' => 'Micro Brewery',
        'nano' => 'Nano Brewery', 
        'regional' => 'Regional Brewery',
        'brewpub' => 'Brewpub',
        'large' => 'Large Brewery',
        'planning' => 'Planning',
        'bar' => 'Bar',
        'contract' => 'Contract',
        'proprietor' => 'Proprietor'
    ];
}

// Process search
$breweries = [];
$error = '';
$searchName = '';
$searchCity = '';
$searchState = '';
$searchType = '';

if ($page === 'breweries' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    $searchName = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $searchCity = htmlspecialchars(trim($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8');
    $searchState = htmlspecialchars(trim($_POST['state'] ?? ''), ENT_QUOTES, 'UTF-8');
    $searchType = htmlspecialchars(trim($_POST['type'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if (strlen($searchName) > MAX_SEARCH_LENGTH || 
        strlen($searchCity) > MAX_SEARCH_LENGTH || 
        strlen($searchState) > MAX_SEARCH_LENGTH) {
        $error = 'Input too long. Maximum ' . MAX_SEARCH_LENGTH . ' characters allowed.';
    } elseif (!empty($searchType) && !array_key_exists($searchType, getBreweryTypes())) {
        $error = 'Invalid brewery type selected.';
    } elseif (empty($searchName) && empty($searchCity) && empty($searchState) && empty($searchType)) {
        $error = 'Please enter at least one search criteria.';
    } else {
        $breweries = searchBreweriesAPI($searchName, $searchCity, $searchState, $searchType);
        if (empty($breweries)) {
            $error = 'No breweries found matching your search criteria. Try different search terms.';
        }
    }
}

// Get sample breweries for home page
$sampleBreweries = $page === 'home' ? getRandomBreweries(8) : [];
$breweryTypes = getBreweryTypes();

// Check if data directory exists and is writable
$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) {
    // Try to create it
    if (!mkdir($data_dir, 0755, true)) {
        error_log("CRITICAL: Cannot create data directory: $data_dir");
    }
}

// Check if we can write to data directory
if (is_dir($data_dir) && !is_writable($data_dir)) {
    error_log("CRITICAL: Data directory not writable: $data_dir");
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrewFinder - Craft Brewery Discovery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--darker) 0%, var(--dark) 50%, var(--dark-light) 100%);
            padding: 20px;
        }
        
        .auth-card {
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-lg);
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .auth-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }
        
        .auth-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .auth-message {
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 600;
        }
        
        .auth-message.success {
            background: rgba(6, 214, 160, 0.1);
            border: 1px solid rgba(6, 214, 160, 0.3);
            color: #06D6A0;
        }
        
        .auth-message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 1rem;
            min-width: 200px;
            box-shadow: var(--shadow-lg);
            display: none;
            z-index: 1000;
        }
        
        .user-dropdown.show {
            display: block;
        }
        
        .user-info {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 0.5rem;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .logout-form {
            margin-top: 0.5rem;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            padding: 0.75rem;
            border-radius: var(--radius);
            font-size: 0.85rem;
            text-align: center;
            font-weight: 600;
            display: none;
        }
        
        .password-strength.visible {
            display: block;
        }
        
        .password-strength.weak {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #EF4444;
        }
        
        .password-strength.medium {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #F59E0B;
        }
        
        .password-strength.strong {
            background: rgba(6, 214, 160, 0.1);
            border: 1px solid rgba(6, 214, 160, 0.3);
            color: #06D6A0;
        }
        
        .password-hint {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.5rem;
            text-align: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
        }
        
        .debug-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php if (!$auth->isLoggedIn() && $page !== 'login'): ?>
        <?php $page = 'login'; ?>
    <?php endif; ?>

    <!-- Debug Information -->
    <div class="debug-info">
        Data Dir: <?= is_dir($data_dir) ? '✅' : '❌' ?> | 
        Users File: <?= file_exists($data_dir . '/users.json') ? '✅' : '❌' ?> |
        Logged In: <?= $auth->isLoggedIn() ? '✅' : '❌' ?>
    </div>

    <?php if ($auth->isLoggedIn()): ?>
        <header class="header">
            <div class="container">
                <nav class="navbar">
                    <div class="nav-brand">
                        <div class="logo">
                            <div class="logo-icon">
                                <i class="fas fa-beer"></i>
                            </div>
                            <span class="logo-text">BrewFinder</span>
                        </div>
                    </div>
                    
                    <div class="nav-main">
                        <ul class="nav-menu">
                            <li class="nav-item">
                                <a href="?page=home" class="nav-link <?= $page === 'home' ? 'active' : '' ?>">
                                    <i class="fas fa-home"></i>
                                    <span>Home</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="?page=breweries" class="nav-link <?= $page === 'breweries' ? 'active' : '' ?>">
                                    <i class="fas fa-search"></i>
                                    <span>Discover</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="?page=types" class="nav-link <?= $page === 'types' ? 'active' : '' ?>">
                                    <i class="fas fa-tags"></i>
                                    <span>Brewery Types</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="?page=about" class="nav-link <?= $page === 'about' ? 'active' : '' ?>">
                                    <i class="fas fa-info-circle"></i>
                                    <span>About</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="nav-actions">
                        <div class="theme-switcher">
                            <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                                <i class="fas fa-moon"></i>
                                <span class="theme-text">Dark Mode</span>
                            </button>
                            <div class="theme-options" id="themeOptions">
                                <button class="theme-option" data-theme="light">
                                    <i class="fas fa-sun"></i>
                                    <span>Light</span>
                                </button>
                                <button class="theme-option" data-theme="dark">
                                    <i class="fas fa-moon"></i>
                                    <span>Dark</span>
                                </button>
                                <button class="theme-option" data-theme="auto">
                                    <i class="fas fa-robot"></i>
                                    <span>Auto</span>
                                </button>
                            </div>
                        </div>
                        
                        <div class="user-menu">
                            <button class="btn btn-secondary" id="userMenuButton">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($auth->getCurrentUser()['username']) ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown" id="userDropdown">
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($auth->getCurrentUser()['username']) ?></strong>
                                    <div class="user-email"><?= htmlspecialchars($auth->getCurrentUser()['email']) ?></div>
                                </div>
                                <form method="POST" class="logout-form">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="btn btn-outline btn-small" style="width: 100%;">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </header>

        <main>
            <?php if ($page === 'home'): ?>
                <section class="hero">
                    <div class="hero-background">
                        <div class="hero-pattern"></div>
                        <div class="floating-elements">
                            <div class="floating-element element-1"><i class="fas fa-beer"></i></div>
                            <div class="floating-element element-2"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="floating-element element-3"><i class="fas fa-globe-americas"></i></div>
                            <div class="floating-element element-4"><i class="fas fa-award"></i></div>
                            <div class="floating-element element-5"><i class="fas fa-users"></i></div>
                            <div class="floating-element element-6"><i class="fas fa-star"></i></div>
                        </div>
                    </div>
                    <div class="container">
                        <div class="hero-content">
                            <div class="hero-badge">
                                <i class="fas fa-user-check"></i>
                                Welcome, <?= htmlspecialchars($auth->getCurrentUser()['username']) ?>!
                            </div>
                            <h1 class="hero-title">
                                <i class="fas fa-beer hero-title-icon"></i>
                                Discover Craft Breweries
                            </h1>
                            <p class="hero-subtitle">
                                <i class="fas fa-map-marked-alt"></i>
                                Explore thousands of breweries, brewpubs, and craft beer destinations worldwide
                            </p>
                            <div class="hero-actions">
                                <a href="?page=breweries" class="btn btn-primary btn-hero">
                                    <i class="fas fa-search"></i>
                                    Explore Breweries
                                    <i class="fas fa-arrow-right btn-arrow"></i>
                                </a>
                                <a href="#features" class="btn btn-secondary">
                                    <i class="fas fa-play-circle"></i>
                                    How It Works
                                </a>
                            </div>
                            <div class="hero-features">
                                <div class="hero-feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>10,000+ Breweries</span>
                                </div>
                                <div class="hero-feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>50+ Countries</span>
                                </div>
                                <div class="hero-feature">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Real-time Data</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section" id="featured">
                    <div class="container">
                        <div class="section-header">
                            <h2>Featured Breweries</h2>
                            <p>Discover handpicked breweries from around the world</p>
                        </div>

                        <?php if (!empty($sampleBreweries)): ?>
                            <div class="breweries-grid">
                                <?php foreach ($sampleBreweries as $brewery): ?>
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="brewery-type"><?= htmlspecialchars(ucfirst($brewery['brewery_type'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></div>
                                            <h3 class="brewery-name"><?= htmlspecialchars($brewery['name'] ?? 'Unnamed Brewery', ENT_QUOTES, 'UTF-8') ?></h3>
                                            <div class="brewery-location">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?= htmlspecialchars($brewery['city'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($brewery['state'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <?php if (!empty($brewery['street'])): ?>
                                                <div class="detail-item">
                                                    <i class="fas fa-road"></i>
                                                    <span><?= htmlspecialchars($brewery['street'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($brewery['website_url'])): ?>
                                                <div class="detail-item">
                                                    <i class="fas fa-globe"></i>
                                                    <a href="<?= htmlspecialchars($brewery['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="website-link">Visit Website</a>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($brewery['phone'])): ?>
                                                <div class="detail-item">
                                                    <i class="fas fa-phone"></i>
                                                    <span><?= htmlspecialchars($brewery['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <span class="country-flag">
                                                <i class="fas fa-flag"></i> <?= htmlspecialchars($brewery['country'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <a href="https://maps.google.com/maps?q=<?= urlencode(($brewery['name'] ?? '') . ' ' . ($brewery['city'] ?? '') . ' ' . ($brewery['state'] ?? '')) ?>" 
                                               target="_blank" class="btn btn-outline btn-small">
                                                <i class="fas fa-map-marked-alt"></i> View Map
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-beer"></i>
                                <h3>Sample Breweries Loaded</h3>
                                <p>Using sample data. API might be temporarily unavailable.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="section features-section" id="features">
                    <div class="container">
                        <div class="section-header">
                            <i class="fas fa-rocket section-icon"></i>
                            <h2>Why Choose BrewFinder?</h2>
                            <p>Experience the ultimate brewery discovery platform</p>
                        </div>
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-bolt"></i></div>
                                <h3>Lightning Fast</h3>
                                <p>Instant search results with our optimized API integration.</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-database"></i></div>
                                <h3>Live Data</h3>
                                <p>Real-time brewery information from Open Brewery DB.</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                                <h3>Mobile First</h3>
                                <p>Perfect experience on all devices.</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-map-marked-alt"></i></div>
                                <h3>Smart Maps</h3>
                                <p>Integrated Google Maps with directions.</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-filter"></i></div>
                                <h3>Advanced Filters</h3>
                                <p>Powerful search and filtering options.</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                                <h3>Secure System</h3>
                                <p>Protected access with strong authentication.</p>
                            </div>
                        </div>
                    </div>
                </section>

            <?php elseif ($page === 'breweries'): ?>
                <section class="section search-page">
                    <div class="container">
                        <div class="section-header">
                            <h2>Discover Breweries</h2>
                            <p>Search for breweries by name, location, or type</p>
                        </div>

                        <div class="search-card">
                            <div class="search-header">
                                <i class="fas fa-search search-icon"></i>
                                <h3>Find Your Perfect Brewery</h3>
                                <p>Search by name, location, or brewery type</p>
                            </div>
                            <form method="POST" id="searchForm">
                                <input type="hidden" name="search" value="1">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="name"><i class="fas fa-building"></i>Brewery Name</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-beer input-icon"></i>
                                            <input type="text" id="name" name="name" value="<?= $searchName ?>" placeholder="e.g., Sierra Nevada, BrewDog">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="city"><i class="fas fa-city"></i>City</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-map-marker-alt input-icon"></i>
                                            <input type="text" id="city" name="city" value="<?= $searchCity ?>" placeholder="e.g., Portland, Denver">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="state"><i class="fas fa-map"></i>State/Province</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-globe-americas input-icon"></i>
                                            <input type="text" id="state" name="state" value="<?= $searchState ?>" placeholder="e.g., California, Colorado">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="type"><i class="fas fa-tag"></i>Brewery Type</label>
                                        <div class="input-with-icon">
                                            <i class="fas fa-industry input-icon"></i>
                                            <select id="type" name="type">
                                                <option value="">All Types</option>
                                                <?php foreach ($breweryTypes as $key => $label): ?>
                                                    <option value="<?= $key ?>" <?= $searchType === $key ? 'selected' : '' ?>><?= $label ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary btn-large">
                                        <i class="fas fa-search"></i>Search Breweries
                                    </button>
                                    <button type="button" id="clearButton" class="btn btn-secondary">
                                        <i class="fas fa-eraser"></i>Clear Filters
                                    </button>
                                </div>
                            </form>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="error">
                                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>

                        <div id="resultsContainer">
                            <?php if (!empty($breweries)): ?>
                                <div class="results-header">
                                    <h3>Search Results</h3>
                                    <div class="results-count"><?= count($breweries) ?> breweries found</div>
                                </div>
                                <div class="breweries-grid">
                                    <?php foreach ($breweries as $brewery): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="brewery-type"><?= htmlspecialchars(ucfirst($brewery['brewery_type'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></div>
                                                <h3 class="brewery-name"><?= htmlspecialchars($brewery['name'] ?? 'Unnamed Brewery', ENT_QUOTES, 'UTF-8') ?></h3>
                                                <div class="brewery-location">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <span><?= htmlspecialchars($brewery['city'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($brewery['state'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!empty($brewery['street'])): ?>
                                                    <div class="detail-item">
                                                        <i class="fas fa-road"></i>
                                                        <span><?= htmlspecialchars($brewery['street'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($brewery['website_url'])): ?>
                                                    <div class="detail-item">
                                                        <i class="fas fa-globe"></i>
                                                        <a href="<?= htmlspecialchars($brewery['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="website-link">Visit Website</a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($brewery['phone'])): ?>
                                                    <div class="detail-item">
                                                        <i class="fas fa-phone"></i>
                                                        <span><?= htmlspecialchars($brewery['phone'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer">
                                                <span class="country-flag">
                                                    <i class="fas fa-flag"></i> <?= htmlspecialchars($brewery['country'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <a href="https://maps.google.com/maps?q=<?= urlencode(($brewery['name'] ?? '') . ' ' . ($brewery['city'] ?? '') . ' ' . ($brewery['state'] ?? '')) ?>" 
                                                   target="_blank" class="btn btn-outline btn-small">
                                                    <i class="fas fa-map-marked-alt"></i> View Map
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])): ?>
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <h3>No breweries found</h3>
                                    <p>Try adjusting your search criteria or try again later</p>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-beer"></i>
                                    <h3>Discover Breweries</h3>
                                    <p>Use the search form to find breweries worldwide</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

            <?php elseif ($page === 'types'): ?>
                <section class="section types-page">
                    <div class="container">
                        <div class="section-header">
                            <h2>Brewery Types</h2>
                            <p>Learn about different types of breweries</p>
                        </div>
                        <div class="breweries-grid">
                            <?php foreach ($breweryTypes as $key => $label): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="brewery-name"><?= $label ?></h3>
                                    </div>
                                    <div class="card-body">
                                        <p class="type-description">
                                            <?php
                                            $descriptions = [
                                                'micro' => 'Small, independent breweries producing limited quantities of beer.',
                                                'nano' => 'Very small breweries, often experimental with tiny batch sizes.',
                                                'regional' => 'Larger breweries distributing beer across multiple regions.',
                                                'brewpub' => 'Restaurant-brewery combinations selling beer on premises.',
                                                'large' => 'Major breweries with significant production capacity.',
                                                'planning' => 'Breweries in the planning or construction phase.',
                                                'bar' => 'Establishments focused on serving rather than brewing.',
                                                'contract' => 'Companies that hire other breweries to produce their beer.',
                                                'proprietor' => 'Breweries operating under specific ownership models.'
                                            ];
                                            echo $descriptions[$key] ?? 'A type of brewery operation.';
                                            ?>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="?page=breweries&type=<?= $key ?>" class="btn btn-primary">
                                            Explore <?= $label ?>s
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

            <?php elseif ($page === 'about'): ?>
                <section class="section about-page">
                    <div class="container">
                        <div class="section-header">
                            <h2>About BrewFinder</h2>
                            <p>Your gateway to the world of craft beer</p>
                        </div>
                        <div class="search-card">
                            <h3 class="about-title">Discover the World of Craft Beer</h3>
                            <p class="about-description">
                                BrewFinder is a comprehensive platform that connects beer enthusiasts with breweries worldwide. 
                                Using real-time data from the Open Brewery DB API, we provide accurate, up-to-date information 
                                about breweries, their locations, and their offerings.
                            </p>
                            <div class="features-grid">
                                <div class="feature-card">
                                    <div class="feature-icon"><i class="fas fa-database"></i></div>
                                    <h3>Real-time Data</h3>
                                    <p>Live updates from Open Brewery DB API</p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"><i class="fas fa-globe"></i></div>
                                    <h3>Worldwide Coverage</h3>
                                    <p>Breweries from around the globe</p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"><i class="fas fa-mobile-alt"></i></div>
                                    <h3>Mobile Friendly</h3>
                                    <p>Optimized for all devices</p>
                                </div>
                                <div class="feature-card">
                                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                                    <h3>Secure Access</h3>
                                    <p>Strong password requirements and secure sessions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer class="footer">
            <div class="container">
                <div class="footer-content">
                    <div class="footer-section">
                        <div class="footer-logo">
                            <i class="fas fa-beer"></i>
                            <span>BrewFinder</span>
                        </div>
                        <p class="footer-description">Discover and explore craft breweries worldwide with real-time data from Open Brewery DB.</p>
                    </div>
                    <div class="footer-section">
                        <h4>Explore</h4>
                        <ul class="footer-links">
                            <li><a href="?page=home">Home</a></li>
                            <li><a href="?page=breweries">Discover Breweries</a></li>
                            <li><a href="?page=types">Brewery Types</a></li>
                            <li><a href="?page=about">About</a></li>
                        </ul>
                    </div>
                    <div class="footer-section">
                        <h4>Resources</h4>
                        <ul class="footer-links">
                            <li><a href="https://www.openbrewerydb.org/" target="_blank">Open Brewery DB</a></li>
                            <li><a href="#">API Documentation</a></li>
                            <li><a href="#">Brewery Guide</a></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-bottom">
                    <p>&copy; <?= date('Y') ?> BrewFinder. Welcome, <?= htmlspecialchars($auth->getCurrentUser()['username']) ?>!</p>
                </div>
            </div>
        </footer>

    <?php else: ?>
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-tabs">
                    <button class="auth-tab active" data-tab="login">Login</button>
                    <button class="auth-tab" data-tab="register">Sign Up</button>
                </div>

                <?php if ($auth_message): ?>
                    <div class="auth-message <?= $auth_success ? 'success' : 'error' ?>">
                        <?= htmlspecialchars($auth_message) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="auth-form active" id="loginForm">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="login_username">Username or Email</label>
                        <input type="text" id="login_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>

                <form method="POST" class="auth-form" id="registerForm">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label for="register_username">Username</label>
                        <input type="text" id="register_username" name="username" required minlength="3">
                    </div>
                    <div class="form-group">
                        <label for="register_email">Email</label>
                        <input type="email" id="register_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="register_password">Password</label>
                        <input type="password" id="register_password" name="password" required oninput="checkPasswordStrength(this.value)">
                        <div id="passwordStrength" class="password-strength"></div>
                    </div>
                    <div class="form-group">
                        <label for="register_confirm_password">Confirm Password</label>
                        <input type="password" id="register_confirm_password" name="confirm_password" required oninput="checkPasswordMatch()">
                        <div id="passwordMatch" class="password-strength"></div>
                    </div>
                    
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i>
                        <strong>Strong passwords include:</strong> uppercase & lowercase letters, numbers, and symbols
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Simple tab switching
        document.querySelectorAll('.auth-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const tabName = this.getAttribute('data-tab');
                document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
                document.getElementById(tabName + 'Form').classList.add('active');
            });
        });

        // User dropdown
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        
        if (userMenuButton && userDropdown) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function() {
                userDropdown.classList.remove('show');
            });
        }

        // Password strength
        function checkPasswordStrength(password) {
            const strengthMeter = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthMeter.textContent = '';
                strengthMeter.className = 'password-strength';
                return;
            }

            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSymbol = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
            const isLongEnough = password.length >= 8;

            const requirements = [hasUppercase, hasLowercase, hasNumber, hasSymbol, isLongEnough];
            const metRequirements = requirements.filter(Boolean).length;
            const strength = metRequirements / requirements.length;

            strengthMeter.classList.add('visible');
            
            if (strength < 0.6) {
                strengthMeter.textContent = 'Weak password';
                strengthMeter.className = 'password-strength visible weak';
            } else if (strength < 1) {
                strengthMeter.textContent = 'Good password';
                strengthMeter.className = 'password-strength visible medium';
            } else {
                strengthMeter.textContent = 'Strong password!';
                strengthMeter.className = 'password-strength visible strong';
            }
        }

        function checkPasswordMatch() {
            const password = document.getElementById('register_password').value;
            const confirmPassword = document.getElementById('register_confirm_password').value;
            const matchMeter = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchMeter.textContent = '';
                matchMeter.className = 'password-strength';
                return;
            }

            matchMeter.classList.add('visible');

            if (password === confirmPassword) {
                matchMeter.textContent = 'Passwords match!';
                matchMeter.className = 'password-strength visible strong';
            } else {
                matchMeter.textContent = 'Passwords do not match';
                matchMeter.className = 'password-strength visible weak';
            }
        }

        // Form validation
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const password = document.getElementById('register_password').value;
                const confirmPassword = document.getElementById('register_confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return;
                }

                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSymbol = /[!@#$%^&*()\-_=+{};:,<.>]/.test(password);
                const isLongEnough = password.length >= 8;

                if (!hasUppercase || !hasLowercase || !hasNumber || !hasSymbol || !isLongEnough) {
                    e.preventDefault();
                    alert('Please use a strong password with uppercase, lowercase, numbers, and symbols.');
                }
            });
        }

        // Theme switcher
        const themeToggle = document.getElementById('themeToggle');
        const themeOptions = document.getElementById('themeOptions');
        
        if (themeToggle && themeOptions) {
            themeToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                themeOptions.classList.toggle('show');
            });

            document.addEventListener('click', function() {
                themeOptions.classList.remove('show');
            });

            document.querySelectorAll('.theme-option').forEach(option => {
                option.addEventListener('click', function() {
                    const theme = this.getAttribute('data-theme');
                    document.documentElement.setAttribute('data-theme', theme);
                    localStorage.setItem('theme', theme);
                    themeOptions.classList.remove('show');
                });
            });

            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
    </script>
</body>
</html>