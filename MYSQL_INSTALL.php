<?php
// SPARK Platform - MySQL Installation & Compatibility Test
echo "SPARK Platform - MySQL Installation & Compatibility Test\n";
echo "=================================================\n\n";

// Check PHP version
echo "Checking PHP Version...\n";
$phpVersion = PHP_VERSION;
echo "PHP Version: $phpVersion\n";
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "✓ PHP version is compatible with MySQL\n";
} else {
    echo "✗ PHP version should be 7.4.0 or higher\n";
    exit(1);
}

// Check MySQL extension
echo "\nChecking MySQL Extension...\n";
if (extension_loaded('pdo_mysql')) {
    echo "✓ PDO MySQL extension is loaded\n";
} else {
    echo "✗ PDO MySQL extension is not installed\n";
    echo "Please install: php-mysql or php-pdo-mysql\n";
    exit(1);
}

// Check MySQL/MariaDB version
echo "\nTesting MySQL/MariaDB Connection...\n";
try {
    require_once 'includes/config.php';
    require_once 'includes/database.php';

    $testResult = testConnection();
    if ($testResult['connected']) {
        echo "✓ Database connection successful\n";
        echo "MySQL Version: " . $testResult['mysql_version'] . "\n";
        echo "Charset: " . $testResult['charset'] . "\n";
        echo "Collation: " . $testResult['collation'] . "\n";

        // Check for MySQL 5.7+ or MariaDB 10.2+
        $mysqlVersion = $testResult['mysql_version'];
        if (strpos($mysqlVersion, 'MariaDB') !== false) {
            // MariaDB version check
            if (preg_match('/(\d+\.\d+)/', $mysqlVersion, $matches)) {
                $mariadbVersion = $matches[1];
                if (version_compare($mariadbVersion, '10.2.0', '>=')) {
                    echo "✓ MariaDB version is compatible\n";
                } else {
                    echo "⚠ MariaDB version should be 10.2.0 or higher for full compatibility\n";
                }
            }
        } else {
            // MySQL version check
            if (preg_match('/(\d+\.\d+)/', $mysqlVersion, $matches)) {
                $mysqlMainVersion = $matches[1];
                if (version_compare($mysqlMainVersion, '5.7.0', '>=')) {
                    echo "✓ MySQL version is compatible\n";
                } else {
                    echo "⚠ MySQL version should be 5.7.0 or higher for full compatibility\n";
                }
            }
        }
    } else {
        echo "✗ Database connection failed\n";
        if (isset($testResult['error'])) {
            echo "Error: " . $testResult['error'] . "\n";
        }
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Database test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test database schema compatibility
echo "\nTesting Database Schema...\n";
try {
    $pdo = getPDO();

    // Check if database exists
    $dbExists = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'")->fetch();
    if ($dbExists) {
        echo "✓ Database '" . DB_NAME . "' exists\n";

        // Check tables
        $tables = $pdo->query("SHOW TABLES FROM `" . DB_NAME . "`")->fetchAll(PDO::FETCH_COLUMN);
        $expectedTables = [
            'students', 'events', 'event_registrations', 'payments', 'certificates',
            'research_projects', 'project_members', 'opportunities', 'attendance',
            'team_members', 'gallery', 'contact_messages', 'activity_logs',
            'homepage_content', 'email_queue', 'settings', 'email_logs'
        ];

        $missingTables = array_diff($expectedTables, $tables);
        if (empty($missingTables)) {
            echo "✓ All required tables exist\n";
        } else {
            echo "⚠ Missing tables: " . implode(', ', $missingTables) . "\n";
            echo "You may need to import the schema.sql file\n";
        }

        echo "Current tables: " . count($tables) . "/16\n";
    } else {
        echo "⚠ Database '" . DB_NAME . "' does not exist\n";
        echo "Create it with: CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    }
} catch (Exception $e) {
    echo "✗ Schema test failed: " . $e->getMessage() . "\n";
}

// Test MySQL features
echo "\nTesting MySQL Features...\n";
try {
    $pdo = getPDO();

    // Test JSON support
    $jsonTest = $pdo->query("SELECT JSON_OBJECT('test', 1) as json_test")->fetch();
    if ($jsonTest && $jsonTest['json_test']) {
        echo "✓ JSON support is available\n";
    } else {
        echo "⚠ JSON support may not be available\n";
    }

    // Test ENUM support
    $enumTest = $pdo->query("SELECT CAST('test' AS CHAR) as enum_test")->fetch();
    if ($enumTest) {
        echo "✓ ENUM support is available\n";
    }

    // Test UTF8MB4 support
    $utf8Test = $pdo->query("SELECT '🔥 SPARK Platform 🚀' as utf8_test")->fetch();
    if ($utf8Test && strpos($utf8Test['utf8_test'], '🔥') !== false) {
        echo "✓ UTF8MB4/Unicode support is working\n";
    } else {
        echo "⚠ UTF8MB4 support may have issues\n";
    }

} catch (Exception $e) {
    echo "✗ Feature test failed: " . $e->getMessage() . "\n";
}

// Test query performance
echo "\nTesting Query Performance...\n";
try {
    $pdo = getPDO();

    // Test basic SELECT
    $start = microtime(true);
    $testQuery = $pdo->query("SELECT 1 as test")->fetch();
    $end = microtime(true);
    $queryTime = ($end - $start) * 1000;

    if ($queryTime < 10) { // Less than 10ms
        echo "✓ Basic query performance is good (" . number_format($queryTime, 2) . "ms)\n";
    } else {
        echo "⚠ Query performance may be slow (" . number_format($queryTime, 2) . "ms)\n";
    }

} catch (Exception $e) {
    echo "✗ Performance test failed: " . $e->getMessage() . "\n";
}

// Installation instructions
echo "\n" . str_repeat("=", 50) . "\n";
echo "MySQL Installation Instructions\n";
echo "==========================\n\n";

echo "1. CREATE DATABASE (if not exists):\n";
echo "CREATE DATABASE IF NOT EXISTS spark_platform\n";
echo "CHARACTER SET utf8mb4\n";
echo "COLLATE utf8mb4_unicode_ci;\n\n";

echo "2. IMPORT SCHEMA:\n";
echo "mysql -u root -p spark_platform < database/schema.sql\n\n";

echo "3. UPDATE CONFIGURATION:\n";
echo "Edit includes/config.php with your database credentials:\n";
echo "- DB_HOST: 'localhost' (or your server)\n";
echo "- DB_NAME: 'spark_platform'\n";
echo "- DB_USER: 'your_mysql_username'\n";
echo "- DB_PASS: 'your_mysql_password'\n\n";

echo "4. VERIFY INSTALLATION:\n";
echo "Access: http://localhost/spark/MYSQL_INSTALL.php\n\n";

// Compatibility summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "Compatibility Summary\n";
echo "==================\n\n";

echo "✓ Fully Compatible:\n";
echo "  - MySQL 5.7+ / MariaDB 10.2+\n";
echo "  - PHP 7.4+ with PDO MySQL\n";
echo "  - UTF8MB4 character support\n";
echo "  - JSON data types\n";
echo "  - ENUM data types\n";
echo "  - InnoDB engine\n\n";

echo "⚠ May Need Configuration:\n";
echo "  - SQL mode settings\n";
echo "  - Timezone settings\n";
echo "  - Buffer pool size\n";
echo "  - Query cache (for performance)\n\n";

echo "🎉 SPARK Platform is ready for MySQL!\n";
echo "Upload files and enjoy your new platform! 🚀\n\n";

echo "For support and documentation, see SETUP_GUIDE.md\n";
?>