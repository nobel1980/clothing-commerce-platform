<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `clothing_commerce` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
    echo "Database clothing_commerce created or verified successfully.\n";
} catch (Exception $e) {
    echo "Failed to create database: " . $e->getMessage() . "\n";
}
