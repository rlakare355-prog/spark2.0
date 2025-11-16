<?php
// Simple test to check if dashboard loads without loading screen
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><title>Dashboard Test</title>";
echo "<style>body{background:#000;color:#0f0;font-family:monospace;padding:20px;}</style>";
echo "</head><body>";

echo "<h1>✓ PHP is working!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
require_once __DIR__ . '/includes/config.php';

echo "<h2>Database Configuration:</h2>";
echo "Host: " . DB_HOST . "<br>";
echo "Database: " . DB_NAME . "<br>";
echo "User: " . DB_USER . "<br>";

try {
    require_once __DIR__ . '/includes/database.php';
    $pdo = getPDO();
    echo "<p style='color:#0f0;'>✓ Database connection successful!</p>";
    
    // Check tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Tables found: " . count($tables) . "</p>";
    
    if (count($tables) > 0) {
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:#f00;'>⚠ No tables! Import database/schema.sql</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:#f00;'>✗ Database error: " . $e->getMessage() . "</p>";
    echo "<p>Make sure you:</p>";
    echo "<ol>";
    echo "<li>Created database 'spark_platform' in phpMyAdmin</li>";
    echo "<li>Imported database/schema.sql</li>";
    echo "<li>XAMPP MySQL is running</li>";
    echo "</ol>";
}

echo "<h2>File Paths:</h2>";
echo "SITE_URL: " . SITE_URL . "<br>";
echo "Current script: " . $_SERVER['SCRIPT_NAME'] . "<br>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>If database connection failed, fix that first</li>";
echo "<li>Update SITE_URL in includes/config.php if needed</li>";
echo "<li>Try loading the dashboard: <a href='student/dashboard.php' style='color:#0ff;'>student/dashboard.php</a></li>";
echo "</ol>";

echo "</body></html>";
?>
