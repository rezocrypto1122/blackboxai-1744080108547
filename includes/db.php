<?php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                )
            );
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Execute a query with prepared statements
     * @param string $query The SQL query
     * @param array $params Parameters for the prepared statement
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            // Log error and return false
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single row
     * @param string $query The SQL query
     * @param array $params Parameters for the prepared statement
     * @return array|false
     */
    public function getRow($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * Get multiple rows
     * @param string $query The SQL query
     * @param array $params Parameters for the prepared statement
     * @return array|false
     */
    public function getRows($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    /**
     * Get the last inserted ID
     * @return string
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->conn->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
}

// Example usage:
// $db = Database::getInstance();
// $conn = $db->getConnection();
// $result = $db->query("SELECT * FROM users WHERE id = ?", [1]);
// $user = $db->getRow("SELECT * FROM users WHERE id = ?", [1]);
// $users = $db->getRows("SELECT * FROM users WHERE status = ?", ['active']);