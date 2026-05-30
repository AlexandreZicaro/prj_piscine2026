<?php
session_start();
require_once 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        if ($password === $admin['mot_de_passe']) {
            // Création du pass d'entrée (la session)
            $_SESSION['user_id'] = $admin['Id'];
            $_SESSION['nom'] = $admin['nom'];
            $_SESSION['prenom'] = $admin['prenom'];
            $_SESSION['role'] = 'admin';
            
            // Redirection vers le tableau de bord DYNAMIQUE
            header("Location: dashboard_admin.php");
            exit();
        } else {
            echo "<script>alert('Mot de passe administrateur incorrect.'); window.location.href = 'connexion_et_incription.html';</script>";
            exit();
        }
    }
    
    echo "<script>alert('Adresse email introuvable.'); window.location.href = 'connexion_et_incription.html';</script>";
    exit();
}
?>