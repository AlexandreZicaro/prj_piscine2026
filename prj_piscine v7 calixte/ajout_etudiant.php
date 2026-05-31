<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: connexion_et_incription.html");
    exit();
}
require_once 'db.php';

$message     = '';
$messageType = '';

// ── TRAITEMENT DU FORMULAIRE ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom']);
    $prenom     = trim($_POST['prenom']);
    $email      = trim($_POST['email']);
    $classe     = trim($_POST['classe']);
    $promo      = trim($_POST['promo']);
    $diplome    = trim($_POST['diplome']);
    $tel        = intval($_POST['num_tel'] ?? 0);
    $naissance  = trim($_POST['date_naissance']);
    $password   = trim($_POST['mot_de_passe']);

    // Vérification email unique
    $check = $pdo->prepare("SELECT id FROM etudiant WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        $message     = "⚠️ Un compte étudiant avec cet email existe déjà.";
        $messageType = 'warning';
    } elseif (strlen($password) < 4) {
        $message     = "⚠️ Le mot de passe doit faire au moins 4 caractères.";
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO etudiant (nom, prenom, email, classe, promo, diplome, mot_de_passe, num_tel, date_naissance)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $prenom, $email, $classe, $promo, $diplome, $password, $tel, $naissance]);
        $message     = "✅ L'étudiant(e) $prenom $nom a été ajouté(e) avec succès !";
        $messageType = 'success';
        // Vider les champs après succès
        $nom = $prenom = $email = $classe = $promo = $diplome = $naissance = $password = '';
        $tel = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ajout Étudiant - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --sky: #b8f0f0; --navy: #0d1b2a; --teal: #0a9396; --teal-light: #94d2bd; --white: #f8fffe; --alert: #e63946; --success: #2a9d8f; --warning: #f4a261; }
    html, body { min-height: 100vh; font-family: 'DM Sans', sans-serif; background: var(--sky); color: var(--navy); overflow-x: hidden; }
    .bg-blob { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.35; pointer-events: none; z-index: 0; }
    .blob1 { width: 480px; height: 480px; background: var(--teal); top: -100px; left: -80px; }
    .blob2 { width: 360px; height: 360px; background: #00b4d8; bottom: 60px; right: -60px; }
    nav { position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; background: var(--navy); padding: 0 2rem; height: 52px; z-index: 100; }
    .nav-logo-img { height: 32px; margin-right: 12px; border-radius: 4px; object-fit: contain; }
    .nav-brand { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.95rem; color: var(--white); letter-spacing: 0.04em; }
    .nav-links { margin-left: auto; display: flex; gap: 12px; align-items: center; }
    .nav-links a { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500; color: var(--sky); text-decoration: none; padding: 6px 14px; border-radius: 4px; transition: background .2s; letter-spacing: 0.04em; text-transform: uppercase; }
    .nav-links a:hover, .nav-links a.active { background: var(--teal); color: #fff; }
    .nav-logout { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600; color: #ffb3b8; text-decoration: none; padding: 6px 14px; border-radius: 4px; border: 1px solid rgba(230,57,70,0.4); transition: all .2s; margin-left: 4px; }
    .nav-logout:hover { background: var(--alert); color: #fff; border-color: var(--alert); }
    .page-wrapper { position: relative; z-index: 1; width: 100%; max-width: 900px; margin: 0 auto; padding: 6rem 1.5rem 5rem; display: flex; flex-direction: column; gap: 2rem; }
    .page-header { display: flex; align-items: center; gap: 12px; font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--navy); background: rgba(255,255,255,0.55); padding: 12px 24px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.8); backdrop-filter: blur(10px); width: max-content; }
    .glass-panel { background: rgba(255,255,255,0.65); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.8); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.08); padding: 2.5rem; }
    .form-title { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 700; color: var(--navy); margin-bottom: 2rem; text-align: center; }
    .form-title::after { content: ''; display: block; width: 60px; height: 3px; background: var(--teal); margin: 10px auto 0; border-radius: 2px; }
    .flash { padding: 14px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; text-align: center; }
    .flash.success { background: rgba(42,157,143,0.15); color: var(--success); border: 1px solid rgba(42,157,143,0.3); }
    .flash.warning { background: rgba(244,162,97,0.15); color: #c06a10; border: 1px solid rgba(244,162,97,0.4); }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full-width { grid-column: span 2; }
    .form-group label { font-size: 0.8rem; font-weight: 600; text-transform: uppercase; color: #3d5a5a; letter-spacing: 0.05em; }
    .form-group input, .form-group select { padding: 12px 14px; border: 1.5px solid #cce0e0; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; color: var(--navy); outline: none; background: rgba(255,255,255,0.8); transition: border-color .2s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--teal); background: #fff; box-shadow: 0 0 0 3px rgba(10,147,150,.12); }
    .form-group input[readonly] { background: rgba(13,27,42,0.05); color: #6b8080; cursor: not-allowed; font-weight: 600; }
    .form-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 2.5rem; }
    .btn-submit { background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 12px; padding: 14px 32px; font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: transform .2s, box-shadow .2s; }
    .btn-submit:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(10,147,150,.3); }
    .btn-back { background: rgba(255,255,255,0.7); color: var(--navy); border: 1px solid rgba(255,255,255,0.9); border-radius: 12px; padding: 14px 24px; font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none; transition: all .2s; }
    .btn-back:hover { background: #fff; }
    footer { position: fixed; bottom: 0; left: 0; right: 0; height: 42px; background: var(--navy); display: flex; align-items: center; justify-content: center; font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--teal-light); z-index: 100; }
    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
  </style>
</head>
<body>
  <div class="bg-blob blob1"></div>
  <div class="bg-blob blob2"></div>

  <nav>
    <img src="logo.jpg" alt="Logo" class="nav-logo-img">
    <span class="nav-brand">SmartCampus</span>
    <div class="nav-links">
      <a href="menu_admin.html">Menu</a>
      <a href="dashboard_admin.php">Tableau de bord</a>
      <a href="#" class="active">Profil : Administrateur</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">
    <div class="page-header">👤+ Ajout étudiant</div>

    <?php if ($message): ?>
    <div class="flash <?= $messageType ?>">
      <?= htmlspecialchars($message) ?>
      <?php if ($messageType === 'success'): ?>
        — <a href="gestion_etudiant.php" style="color:var(--teal);font-weight:700">Voir la liste des étudiants</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="glass-panel">
      <h2 class="form-title">Remplir le formulaire</h2>

      <form method="POST" action="ajout_etudiant.php">
        <div class="form-grid">

          <div class="form-group">
            <label>ID Étudiant</label>
            <input type="text" value="Généré automatiquement" readonly title="Auto-increment par la base de données">
          </div>
          <div class="form-group">
            <label>Promotion (Année)</label>
            <select name="promo" required>
              <option value="" disabled <?= empty($promo) ? 'selected' : '' ?>>Sélectionner une promo</option>
              <option value="2026" <?= ($promo ?? '') === '2026' ? 'selected' : '' ?>>Promo 2026</option>
              <option value="2025" <?= ($promo ?? '') === '2025' ? 'selected' : '' ?>>Promo 2025</option>
              <option value="2024" <?= ($promo ?? '') === '2024' ? 'selected' : '' ?>>Promo 2024</option>
            </select>
          </div>

          <div class="form-group">
            <label>Nom</label>
            <input type="text" name="nom" placeholder="Saisir le nom" value="<?= htmlspecialchars($nom ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="prenom" placeholder="Saisir le prénom" value="<?= htmlspecialchars($prenom ?? '') ?>" required>
          </div>

          <div class="form-group">
            <label>Date de naissance</label>
            <input type="date" name="date_naissance" id="dateNaissance"
                   value="<?= htmlspecialchars($naissance ?? '') ?>" required onchange="calculateAge()">
          </div>
          <div class="form-group">
            <label>Âge</label>
            <input type="number" id="age" placeholder="Âge calculé automatiquement" readonly
                   style="background:rgba(13,27,42,0.05);color:#6b8080;">
          </div>

          <div class="form-group">
            <label>Classe / Filière</label>
            <input type="text" name="classe" placeholder="Ex: Master 1 Informatique"
                   value="<?= htmlspecialchars($classe ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Dernier diplôme obtenu</label>
            <input type="text" name="diplome" placeholder="Ex: Licence Informatique"
                   value="<?= htmlspecialchars($diplome ?? '') ?>">
          </div>

          <div class="form-group">
            <label>Adresse e-mail</label>
            <input type="email" name="email" placeholder="prenom.nom@smartcampus.fr"
                   value="<?= htmlspecialchars($email ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Numéro de téléphone</label>
            <input type="number" name="num_tel" placeholder="Ex: 612345678"
                   value="<?= htmlspecialchars($tel ?? '') ?>">
          </div>

          <div class="form-group full-width">
            <label>Mot de passe initial</label>
            <input type="text" name="mot_de_passe" placeholder="Mot de passe provisoire (min. 4 caractères)"
                   value="<?= htmlspecialchars($password ?? '') ?>" required minlength="4">
          </div>

        </div>

        <div class="form-actions">
          <a href="gestion_etudiant.php" class="btn-back">← Retour à la liste</a>
          <button type="submit" class="btn-submit">✔️ Ajouter l'étudiant</button>
        </div>
      </form>
    </div>
  </main>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>

  <script>
    function calculateAge() {
      const birthInput = document.getElementById('dateNaissance').value;
      if (birthInput) {
        const birthDate = new Date(birthInput);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        document.getElementById('age').value = age;
      }
    }
  </script>
</body>
</html>
