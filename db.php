<?php
/**
 * db.php
 * Shared PDO database connection for the Restaurant Listing and Review System.
 * Every page that needs the database includes this file:
 *      require_once 'db.php';
 * and then uses the $pdo variable.
 */

$host   = '127.0.0.1';
$dbname = 'fooddb';
$user   = 'root';
$pass   = '';   // default XAMPP MySQL password is empty

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass
    );

    // Throw exceptions on error instead of silently failing
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Return real column names (avoid uppercase keys issue on some setups)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Stop execution and show a safe error message (don't leak credentials)
    die("Database connection failed: " . $e->getMessage());
}
