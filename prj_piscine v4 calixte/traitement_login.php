<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    // 1. Vérification ADMIN
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && $password === $admin['mot_de_passe']) {
        $_SESSION['user_id'] = $admin['Id'];
        $_SESSION['nom']     = $admin['nom'];
        $_SESSION['prenom']  = $admin['prenom'];
        $_SESSION['role']    = 'admin';
        header("Location: dashboard_admin.php");
        exit();
    }

    // 2. Vérification ENSEIGNANT
    $stmt = $pdo->prepare("SELECT * FROM enseignant WHERE email = ?");
    $stmt->execute([$email]);
    $enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($enseignant && $password === $enseignant['mot_de_passe']) {
        $_SESSION['user_id'] = $enseignant['id'];
        $_SESSION['nom']     = $enseignant['nom'];
        $_SESSION['prenom']  = $enseignant['prenom'];
        $_SESSION['role']    = 'enseignant';
        header("Location: dashboard_enseignant.php");
        exit();
    }

    // 3. Vérification ÉTUDIANT
    $stmt = $pdo->prepare("SELECT * FROM etudiant WHERE email = ?");
    $stmt->execute([$email]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etudiant && $password === $etudiant['mot_de_passe']) {
        $_SESSION['user_id']  = $etudiant['id'];
        $_SESSION['nom']      = $etudiant['nom'];
        $_SESSION['prenom']   = $etudiant['prenom'];
        $_SESSION['role']     = 'etudiant';
        $_SESSION['classe']   = $etudiant['classe'];
        $_SESSION['email']    = $etudiant['email'];
        header("Location: dashboard_etudiant.php");
        exit();
    }

    // Aucun compte trouvé
    echo "<script>alert('Email ou mot de passe incorrect.'); window.location.href = 'connexion_et_incription.html';</script>";
    exit();
}
?>
