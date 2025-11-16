<?php
// SPARK Platform - Database Connection

require_once __DIR__ . '/config.php';

// PDO Database connection - Optimized for MySQL
function getPDO() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Set MySQL-specific settings for optimal performance
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $pdo->exec("SET SESSION time_zone = '+00:00'");

        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());

            // In production, show a generic error
            die("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}

// Database helper functions
function dbQuery($sql, $params = []) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . json_encode($params));
        throw $e;
    }
}

function dbFetch($sql, $params = []) {
    return dbQuery($sql, $params)->fetch();
}

function dbFetchAll($sql, $params = []) {
    return dbQuery($sql, $params)->fetchAll();
}

// Alias functions for compatibility
function fetchRow($sql, $params = []) {
    return dbFetch($sql, $params);
}

function fetchAll($sql, $params = []) {
    return dbFetchAll($sql, $params);
}

function fetchColumn($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchColumn();
}

function executeUpdate($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

function dbInsert($table, $data) {
    $columns = array_keys($data);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

    dbQuery($sql, array_values($data));

    return getPDO()->lastInsertId();
}

function dbUpdate($table, $data, $where, $whereParams = []) {
    $setClause = [];
    $params = [];

    foreach ($data as $column => $value) {
        $setClause[] = "$column = ?";
        $params[] = $value;
    }

    $sql = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE $where";
    $params = array_merge($params, $whereParams);

    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

function dbDelete($table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    $stmt = dbQuery($sql, $params);
    return $stmt->rowCount();
}

function dbCount($table, $where = '1=1', $params = []) {
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $where";
    $result = dbFetch($sql, $params);
    return (int)$result['count'];
}

// Transaction helpers
function dbTransaction($callback) {
    $pdo = getPDO();
    $pdo->beginTransaction();

    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Check if table exists - MySQL optimized
function tableExists($tableName) {
    try {
        // Use information_schema for reliable table existence check
        $sql = "SELECT COUNT(*) as count
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = ?";
        $result = dbFetch($sql, [$tableName]);
        return $result && $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Table existence check failed: " . $e->getMessage());
        return false;
    }
}

// Get table columns - MySQL optimized
function getTableColumns($tableName) {
    try {
        $sql = "SHOW COLUMNS FROM ?";
        // For table names, we need to use tableExists validation first
        if (!tableExists($tableName)) {
            throw new Exception("Table '$tableName' does not exist");
        }

        // Direct SQL for column names (table name is validated)
        $result = dbFetchAll("SHOW COLUMNS FROM `$tableName`");
        return array_column($result, 'Field');
    } catch (Exception $e) {
        error_log("Failed to get columns for '$tableName': " . $e->getMessage());
        return [];
    }
}

// Build WHERE clause from filters
function buildWhereClause($filters) {
    if (empty($filters)) {
        return ['1=1', []];
    }

    $conditions = [];
    $params = [];

    foreach ($filters as $column => $value) {
        if (is_array($value)) {
            // IN clause
            $placeholders = str_repeat('?,', count($value) - 1) . '?';
            $conditions[] = "$column IN ($placeholders)";
            $params = array_merge($params, $value);
        } elseif (strpos($value, '%') !== false) {
            // LIKE clause
            $conditions[] = "$column LIKE ?";
            $params[] = $value;
        } else {
            // Equals clause
            $conditions[] = "$column = ?";
            $params[] = $value;
        }
    }

    return [implode(' AND ', $conditions), $params];
}

// Pagination helper - MySQL optimized
function paginate($query, $params, $page = 1, $perPage = 10) {
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage)); // Limit per page to 100
    $offset = ($page - 1) * $perPage;

    try {
        // Count total records using SQL_CALC_FOUND_ROWS for MySQL optimization
        $countQuery = "SELECT COUNT(*) as total FROM ($query) as counted_query";
        $totalResult = dbFetch($countQuery, $params);
        $total = (int)$totalResult['total'];

        // Get paginated results with LIMIT and OFFSET
        $paginatedQuery = $query . " LIMIT ? OFFSET ?";
        $allParams = array_merge($params, [$perPage, $offset]);
        $results = dbFetchAll($paginatedQuery, $allParams);

        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'has_next' => $page < ceil($total / $perPage),
            'has_prev' => $page > 1
        ];
    } catch (Exception $e) {
        error_log("Pagination failed: " . $e->getMessage());
        return [
            'data' => [],
            'total' => 0,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => 0,
            'has_next' => false,
            'has_prev' => false
        ];
    }
}

// Initialize database (create tables if they don't exist)
function initializeDatabase() {
    $pdo = getPDO();

    // Check if students table exists
    if (!tableExists('students')) {
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            $statements = array_filter(array_map('trim', explode(';', $schema)));

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            return true;
        }
    }

    return false;
}

// Test database connection - MySQL optimized
function testConnection() {
    try {
        $pdo = getPDO();

        // Test basic connectivity
        $stmt = $pdo->query("SELECT 1 as test_connection");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['test_connection'] != 1) {
            return false;
        }

        // Test MySQL version and features
        $versionStmt = $pdo->query("SELECT VERSION() as mysql_version");
        $versionInfo = $versionStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'connected' => true,
            'mysql_version' => $versionInfo['mysql_version'] ?? 'unknown',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ];
    } catch (Exception $e) {
        error_log("Database connection test failed: " . $e->getMessage());
        return [
            'connected' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Auto-initialize database on first load if needed
try {
    if (!tableExists('students')) {
        initializeDatabase();
    }
} catch (Exception $e) {
    error_log("Database auto-initialization failed: " . $e->getMessage());
    // Don't die here - let the main application handle missing database
}
?>