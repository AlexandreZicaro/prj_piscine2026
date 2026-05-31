<?php
// 1. Démarrage de la session pour la sécurité
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: connexion_et_incription.html");
    exit();
}

// 2. Connexion à la base de données
require_once 'db.php';

try {
    // --- KPIs DYNAMIQUES ---
    $nbEnseignants = $pdo->query("SELECT COUNT(*) FROM enseignant")->fetchColumn();
    $nbEtudiants = $pdo->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();

    // --- MESSAGES DYNAMIQUES (Supervision) ---
    // Récupération des 4 dernières discussions créées
    $stmtMsg = $pdo->query("
        SELECT d.sujet, d.date_creation, e.nom AS etu_nom, p.nom AS prof_nom 
        FROM discussion_messagerie d
        JOIN etudiant e ON d.id_etudiant = e.id
        JOIN enseignant p ON d.id_enseignant = p.id
        ORDER BY d.id_discussion DESC 
        LIMIT 4
    ");
    $derniersMessages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

    // --- ACTIVITÉS DYNAMIQUES (Derniers étudiants inscrits) ---
    $stmtAct = $pdo->query("SELECT nom, prenom, date_creation FROM etudiant ORDER BY date_creation DESC, id DESC LIMIT 4");
    $dernieresActivites = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Administrateur - SmartCampus</title>
  
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  
  <style>
    /* ── RESET ET VARIABLES (Thème Accueil) ── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --sky: #b8f0f0; --sky-dark: #8de0e0; --navy: #0d1b2a; --teal: #0a9396; --teal-light: #94d2bd; --white: #f8fffe; --alert: #e63946; --warning: #f4a261; --success: #2a9d8f; }
    html, body { min-height: 100vh; font-family: 'DM Sans', sans-serif; background: var(--sky); color: var(--navy); overflow-x: hidden; }

    /* ── HERO BG DECORATION (Bulles de fond) ── */
    .bg-blob { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.35; pointer-events: none; z-index: 0; }
    .blob1 { width: 480px; height: 480px; background: var(--teal); top: -100px; left: -80px; }
    .blob2 { width: 360px; height: 360px; background: #00b4d8; bottom: 60px; right: -60px; }

    /* ── NAVBAR ── */
    nav { position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; background: var(--navy); padding: 0 2rem; height: 52px; z-index: 100; }
    .nav-logo-img { height: 32px; margin-right: 12px; border-radius: 4px; object-fit: contain; }
    .nav-brand { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.95rem; color: var(--white); letter-spacing: 0.04em; }
    .nav-links { margin-left: auto; display: flex; gap: 12px; align-items: center; }
    .nav-links a { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500; color: var(--sky); text-decoration: none; padding: 6px 14px; border-radius: 4px; transition: background .2s, color .2s; letter-spacing: 0.04em; text-transform: uppercase; }
    .nav-links a:hover, .nav-links a.active { background: var(--teal); color: #fff; }
    .nav-bell { position: relative; font-size: 1.1rem; color: var(--sky); cursor: pointer; margin: 0 8px; transition: transform 0.2s, color 0.2s; }
    .nav-bell:hover { transform: scale(1.1); color: #fff; }
    .nav-bell::after { content: ''; position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: var(--alert); border-radius: 50%; border: 2px solid var(--navy); }
    .nav-logout { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600; color: #ffb3b8; text-decoration: none; padding: 6px 14px; border-radius: 4px; border: 1px solid rgba(230,57,70,0.4); transition: all .2s; }
    .nav-logout:hover { background: var(--alert); color: #fff; border-color: var(--alert); }

    /* ── LAYOUT PRINCIPAL ── */
    .dashboard-wrapper { position: relative; z-index: 1; width: 100%; max-width: 1300px; margin: 0 auto; padding: 5rem 1.5rem 4rem 1.5rem; display: grid; grid-template-columns: 1fr; gap: 2.5rem; }
    @media (min-width: 992px) { .dashboard-wrapper { grid-template-columns: 2fr 1fr; } }
    .main-col, .side-col { display: flex; flex-direction: column; gap: 2rem; }

    /* ── STYLE GLASSMORPHISM ── */
    .glass-panel { background: rgba(255,255,255,0.55); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.7); border-radius: 16px; box-shadow: 0 6px 28px rgba(0,0,0,.06); padding: 1.5rem; transition: transform 0.25s, box-shadow 0.25s; }
    .glass-panel:hover { box-shadow: 0 12px 36px rgba(10,147,150,.15); }

    .page-header h1 { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--navy); margin-bottom: 0.5rem; }
    .page-header p { font-size: 0.95rem; font-weight: 600; color: var(--navy); background: rgba(255,255,255,0.55); display: inline-block; padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.8); text-transform: capitalize; backdrop-filter: blur(10px); }
    .section-divider { border: none; border-top: 2px solid rgba(13, 27, 42, 0.1); margin: 0.5rem 0; }

    /* KPIs */
    .kpi-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .kpi-card { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 2rem 1rem; }
    .kpi-title { font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
    .kpi-value { font-size: 2.8rem; font-weight: 800; line-height: 1; color: var(--teal); }

    /* ACTIONS RAPIDES */
    .card-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .actions-header { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--navy); margin-bottom: 1rem; }
    .actions-grid { display: flex; flex-direction: column; gap: 1rem; }
    
    /* Correction CSS pour que les balises <a> ressemblent à des boutons */
    .action-btn { background: rgba(255,255,255,0.7); color: var(--navy); border: 1px solid rgba(255,255,255,0.9); padding: 1.2rem; border-radius: 12px; font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.04); text-decoration: none; }
    .action-btn::after { content: '→'; font-size: 1.2rem; opacity: 0.5; transition: all 0.2s; }
    .action-btn:hover { background: var(--teal); color: var(--white); transform: translateY(-2px); }
    .action-btn:hover::after { opacity: 1; transform: translateX(4px); }

    /* LISTES */
    .std-card h2 { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--navy); border-bottom: 2px solid rgba(13, 27, 42, 0.1); padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .std-card h2 span.badge { background: var(--alert); color: var(--white); font-family: 'DM Sans', sans-serif; font-size: 0.75rem; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .list-item { padding: 12px 0; border-bottom: 1px solid rgba(13, 27, 42, 0.08); }
    .list-item:last-child { border-bottom: none; padding-bottom: 0; }
    .item-title { font-weight: 700; font-size: 0.95rem; display: block; color: var(--navy); }
    .item-sub { font-size: 0.85rem; color: #4a6a6a; margin-top: 4px; display: block; }
    
    .msg-btn { width: 100%; display: block; text-align: center; margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.6); color: var(--teal); font-family: 'Syne', sans-serif; font-weight: 700; border: 1px solid rgba(10, 147, 150, 0.3); border-radius: 10px; cursor: pointer; transition: all 0.2s; text-decoration: none; }
    .msg-btn:hover { background: var(--teal); color: var(--white); }

    /* COLONNE DE DROITE */
    .img-box { width: 100%; height: 220px; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .img-box img { width: 100%; height: 100%; object-fit: cover; }
    .image-placeholder h3 { font-family: 'Syne', sans-serif; font-size: 1.1rem; color: var(--navy); }

    .activity-feed h3 { font-family: 'Syne', sans-serif; font-size: 1.2rem; margin-bottom: 1rem; color: var(--navy); border-bottom: 2px solid rgba(13, 27, 42, 0.1); padding-bottom: 10px; }
    .log-item { display: flex; gap: 12px; margin-bottom: 15px; }
    .log-time { font-size: 0.75rem; font-weight: 700; color: var(--teal); min-width: 60px; margin-top: 2px; }
    .log-text { font-size: 0.88rem; color: var(--navy); line-height: 1.4; }
    .log-text strong { font-weight: 700; }

    footer { position: fixed; bottom: 0; left: 0; right: 0; height: 42px; background: var(--navy); display: flex; align-items: center; justify-content: center; font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--teal-light); letter-spacing: 0.08em; z-index: 100; }
  </style>
</head>
<body>

  <div class="bg-blob blob1"></div>
  <div class="bg-blob blob2"></div>

  <nav>
    <img src="logo.jpg" alt="Logo SmartCampus" class="nav-logo-img">
    <span class="nav-brand">SmartCampus</span>
    <div class="nav-links">
      <a href="menu_admin.html">Menu</a>
      <a href="#" class="active">Tableau de bord</a>
      <a href="#">Profil : <?= htmlspecialchars($_SESSION['prenom'] ?? 'Admin') ?></a>
      <div class="nav-bell" title="Notifications">🔔</div>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="dashboard-wrapper">
    
    <div class="main-col">
      
      <div class="page-header">
        <h1>Dashboard Administrateur</h1>
        <p id="current-date">Date</p>
      </div>

      <hr class="section-divider">

      <div class="kpi-container">
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Enseignants Actifs</div>
          <div class="kpi-value"><?= $nbEnseignants ?></div>
        </div>
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Étudiants Inscrits</div>
          <div class="kpi-value"><?= $nbEtudiants ?></div>
        </div>
      </div>

      <hr class="section-divider">

      <div class="card-container">
        
        <div>
          <div class="actions-header">Actions rapides</div>
          <div class="actions-grid">
            <!-- Correction des boutons de navigation -->
            <a href="gestion_enseignant.html" class="action-btn">Rechercher enseignant</a>
            <a href="gestion_etudiant.html" class="action-btn">Rechercher étudiant</a>
          </div>
        </div>

        <div class="glass-panel std-card">
          <div>
            <h2>Supervision Messagerie <span class="badge"><?= count($derniersMessages) ?> flux</span></h2>
            <?php if (count($derniersMessages) > 0): ?>
                <?php foreach($derniersMessages as $msg): ?>
                <div class="list-item">
                  <span class="item-title">De <?= htmlspecialchars($msg['etu_nom']) ?> à Pr. <?= htmlspecialchars($msg['prof_nom']) ?></span>
                  <span class="item-sub">Sujet : <?= htmlspecialchars($msg['sujet']) ?></span>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="list-item">
                  <span class="item-sub">Aucun message sur le réseau.</span>
                </div>
            <?php endif; ?>
          </div>
          <!-- Correction du bouton messagerie -->
          <a href="messagerie_admin.html" class="msg-btn">Accéder à la messagerie globale</a>
        </div>

      </div>

    </div>

    <div class="side-col">
      
      <div class="glass-panel image-placeholder">
        <div class="img-box">
          <img src="ville-de-Bordeaux.jpg" alt="Campus">
        </div>
        <h3>Espace de Supervision</h3>
        <p style="font-size:0.85rem; color:#4a6a6a; margin-top:8px;">Gérez l'ensemble des flux académiques depuis cette interface centralisée.</p>
      </div>

      <div class="glass-panel activity-feed">
        <h3>Dernières inscriptions</h3>
        
        <?php if (count($dernieresActivites) > 0): ?>
            <?php foreach($dernieresActivites as $act): ?>
            <div class="log-item">
              <div class="log-time"><?= date('d/m', strtotime($act['date_creation'])) ?></div>
              <div class="log-text"><strong>Système :</strong> Création du profil étudiant pour <em><?= htmlspecialchars($act['prenom'] . ' ' . $act['nom']) ?></em>.</div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="log-item">
              <div class="log-text">Aucune activité récente.</div>
            </div>
        <?php endif; ?>
        
        <!-- Alerte statique pour maintenir le design de la maquette -->
        <div class="log-item">
          <div class="log-time">Système</div>
          <div class="log-text"><strong>Sauvegarde :</strong> Base de données synchronisée avec succès.</div>
        </div>
      </div>

    </div>

  </main>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>

  <script>
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const today = new Date().toLocaleDateString('fr-FR', options);
    document.getElementById('current-date').textContent = today;
  </script>

</body>
</html>