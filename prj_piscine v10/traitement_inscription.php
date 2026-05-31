<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prenom           = trim($_POST['prenom']);
    $nom              = trim($_POST['nom']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validation : mots de passe identiques
    if ($password !== $password_confirm) {
        echo "<script>alert('Les mots de passe ne correspondent pas.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    // Validation : longueur minimale du mot de passe
    if (strlen($password) < 4) {
        echo "<script>alert('Le mot de passe doit contenir au moins 4 caractères.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    // Vérifier si l'email existe déjà (dans toutes les tables)
    $stmt = $pdo->prepare("SELECT id FROM etudiant WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<script>alert('Un compte étudiant existe déjà avec cet email.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM enseignant WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<script>alert('Cet email appartient déjà à un compte enseignant.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    // Insertion dans la table etudiant
    $stmt = $pdo->prepare("
        INSERT INTO etudiant (nom, prenom, email, classe, promo, diplome, mot_de_passe, num_tel, date_naissance)
        VALUES (?, ?, ?, 'Non définie', '2026', 'Non défini', ?, 0, '2000-01-01')
    ");
    $stmt->execute([$nom, $prenom, $email, $password]);

    $newId = $pdo->lastInsertId();

    // Création de la session
    $_SESSION['user_id'] = $newId;
    $_SESSION['nom']     = $nom;
    $_SESSION['prenom']  = $prenom;
    $_SESSION['email']   = $email;
    $_SESSION['role']    = 'etudiant';
    $_SESSION['classe']  = 'Non définie';

    // Redirection directe vers le dashboard étudiant
    header("Location: dashboard_etudiant.php");
    exit();
}
?>
