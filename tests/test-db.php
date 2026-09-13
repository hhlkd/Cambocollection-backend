<?php
$host = '127.0.0.1';
$db = 'ecommerce_db';
$user = 'root';
$pass = ''; // Add your password if you have one

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo "✅ Database connection successful!";
} catch(PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
?>
