<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $prenom   = trim($_POST['prenom']);
    $nom      = trim($_POST['nom']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // Validation mot de passe
    if ($password !== $password_confirm) {
        echo "<script>alert('Les mots de passe ne correspondent pas.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM etudiant WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo "<script>alert('Un compte existe déjà avec cet email.'); window.location.href = 'connexion_et_incription.html';</script>";
        exit();
    }

    // Insertion dans la table etudiant
    // Valeurs par défaut pour les champs obligatoires non saisis lors de l'inscription
    $stmt = $pdo->prepare("
        INSERT INTO etudiant (nom, prenom, email, classe, promo, diplome, mot_de_passe, num_tel, date_naissance)
        VALUES (?, ?, ?, 'Non définie', '2026', 'Non défini', ?, 0, '2000-01-01')
    ");
    $stmt->execute([$nom, $prenom, $email, $password]);

    $newId = $pdo->lastInsertId();

    // Création de session directe après inscription
    $_SESSION['user_id'] = $newId;
    $_SESSION['nom']     = $nom;
    $_SESSION['prenom']  = $prenom;
    $_SESSION['role']    = 'etudiant';

    echo "<script>alert('Compte créé avec succès ! Bienvenue " . htmlspecialchars($prenom) . " !'); window.location.href = 'connexion_et_incription.html';</script>";
    exit();
}
?>
