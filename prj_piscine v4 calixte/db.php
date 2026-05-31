<?php
// Paramètres de connexion pour MAMP
$host = 'localhost';
$dbname = 'smartcampus';
$user = 'root';
$pass = 'root'; // Sous MAMP Windows, le mot de passe par défaut est 'root'

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>