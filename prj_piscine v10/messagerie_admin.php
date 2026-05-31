<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: connexion_et_incription.html"); exit();
}
require_once 'db.php';

$message = ''; $messageType = '';

// ── DONNÉES : toutes les discussions ──────────────────────────
$discussions = $pdo->query("
    SELECT d.id_discussion, d.sujet, d.date_creation,
           et.nom AS etu_nom, et.prenom AS etu_prenom, et.email AS etu_email,
           en.nom AS ens_nom, en.prenom AS ens_prenom, en.departement
    FROM discussion_messagerie d
    JOIN etudiant  et ON d.id_etudiant  = et.id
    JOIN enseignant en ON d.id_enseignant = en.id
    ORDER BY d.id_discussion DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$nbDiscussions  = count($discussions);
$nbEtudiants    = $pdo->query("SELECT COUNT(*) FROM etudiant")->fetchColumn();
$nbEnseignants  = $pdo->query("SELECT COUNT(*) FROM enseignant")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Messagerie Admin - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--sky:#b8f0f0;--navy:#0d1b2a;--teal:#0a9396;--teal-light:#94d2bd;--white:#f8fffe;--alert:#e63946;--success:#2a9d8f;--warning:#f4a261}
    html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--sky);color:var(--navy)}
    .bg-blob{position:fixed;border-radius:50%;filter:blur(80px);opacity:.35;pointer-events:none;z-index:0}
    .blob1{width:480px;height:480px;background:var(--teal);top:-100px;left:-80px}
    .blob2{width:360px;height:360px;background:#00b4d8;bottom:60px;right:-60px}
    nav{position:fixed;top:0;left:0;right:0;display:flex;align-items:center;background:var(--navy);padding:0 2rem;height:52px;z-index:100}
    .nav-logo-img{height:32px;margin-right:12px;border-radius:4px;object-fit:contain}
    .nav-brand{font-family:'Syne',sans-serif;font-weight:700;font-size:.95rem;color:var(--white);letter-spacing:.04em}
    .nav-links{margin-left:auto;display:flex;gap:12px;align-items:center}
    .nav-links a{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:500;color:var(--sky);text-decoration:none;padding:6px 14px;border-radius:4px;transition:background .2s;letter-spacing:.04em;text-transform:uppercase}
    .nav-links a:hover,.nav-links a.active{background:var(--teal);color:#fff}
    .nav-logout{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;color:#ffb3b8;text-decoration:none;padding:6px 14px;border-radius:4px;border:1px solid rgba(230,57,70,.4);transition:all .2s;margin-left:4px}
    .nav-logout:hover{background:var(--alert);color:#fff;border-color:var(--alert)}
    .page-wrapper{position:relative;z-index:1;width:100%;max-width:1200px;margin:0 auto;padding:6rem 1.5rem 5rem;display:flex;flex-direction:column;gap:2rem}
    .glass-panel{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.06);padding:1.5rem 2rem}
    .page-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy);background:rgba(255,255,255,.55);padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,.8);display:inline-block}
    .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:0}
    .stat-card{text-align:center;padding:1.2rem}
    .stat-val{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:var(--teal)}
    .stat-lbl{font-size:.78rem;font-weight:600;color:#4a6a6a;text-transform:uppercase;margin-top:4px}
    .section-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1.2rem;color:var(--navy);margin-bottom:1.2rem;padding-bottom:10px;border-bottom:2px solid rgba(13,27,42,.1);display:flex;justify-content:space-between;align-items:center}
    .badge{font-size:.75rem;font-weight:700;background:var(--alert);color:#fff;padding:4px 10px;border-radius:12px;font-family:'DM Sans',sans-serif}
    table{width:100%;border-collapse:collapse}
    th{font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;color:#3d5a5a;padding:10px 12px;border-bottom:2px solid rgba(13,27,42,.15);text-align:left}
    td{padding:12px;border-bottom:1px solid rgba(13,27,42,.07);font-size:.92rem;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(255,255,255,.5);cursor:pointer}
    .badge-etu{display:inline-block;background:rgba(10,147,150,.12);color:var(--teal);padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700}
    .badge-ens{display:inline-block;background:rgba(244,162,97,.15);color:#c06a10;padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700}
    .date-badge{font-size:.8rem;color:#6b8080}
    .empty-state{text-align:center;padding:2.5rem;color:#6b8080;font-style:italic}
    /* Lecteur */
    .reader-overlay{position:fixed;inset:0;background:rgba(13,27,42,.5);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:300;opacity:0;pointer-events:none;transition:opacity .3s}
    .reader-overlay.open{opacity:1;pointer-events:all}
    .reader-box{background:var(--white);border-radius:16px;width:600px;max-width:94vw;padding:2rem;box-shadow:0 20px 60px rgba(0,0,0,.2);position:relative}
    .reader-title{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--navy);margin-bottom:12px}
    .reader-meta{font-size:.88rem;color:#4a6a6a;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid rgba(13,27,42,.1);line-height:1.8}
    .reader-meta strong{color:var(--navy)}
    .btn-close-reader{position:absolute;top:14px;right:18px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6b8080}
    footer{position:fixed;bottom:0;left:0;right:0;height:42px;background:var(--navy);display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;font-size:.78rem;color:var(--teal-light);z-index:100}
  </style>
</head>
<body>
  <div class="bg-blob blob1"></div><div class="bg-blob blob2"></div>
  <nav>
    <img src="logo.jpg" alt="Logo" class="nav-logo-img">
    <span class="nav-brand">SmartCampus</span>
    <div class="nav-links">
      <a href="menu_admin.html">Menu</a>
      <a href="dashboard_admin.php">Tableau de bord</a>
      <a href="#" class="active">Messagerie</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">

    <div class="page-title">💬 Messagerie — Supervision globale</div>

    <!-- STATS -->
    <div class="glass-panel">
      <div class="stats-row">
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $nbDiscussions ?></div>
          <div class="stat-lbl">Discussions actives</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $nbEtudiants ?></div>
          <div class="stat-lbl">Étudiants inscrits</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $nbEnseignants ?></div>
          <div class="stat-lbl">Enseignants actifs</div>
        </div>
      </div>
    </div>

    <!-- TOUTES LES DISCUSSIONS -->
    <div class="glass-panel">
      <div class="section-title">
        Toutes les discussions
        <?php if ($nbDiscussions > 0): ?>
        <span class="badge"><?= $nbDiscussions ?> message<?= $nbDiscussions>1?'s':'' ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($discussions)): ?>
        <div class="empty-state">Aucune discussion sur le réseau pour le moment.</div>
      <?php else: ?>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr><th>Sujet</th><th>Étudiant</th><th>Enseignant</th><th>Département</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($discussions as $d): ?>
            <tr onclick="readDiscussion(<?= htmlspecialchars(json_encode($d), ENT_QUOTES) ?>)">
              <td><strong><?= htmlspecialchars($d['sujet']) ?></strong></td>
              <td><span class="badge-etu"><?= htmlspecialchars($d['etu_prenom'].' '.$d['etu_nom']) ?></span></td>
              <td><span class="badge-ens"><?= htmlspecialchars($d['ens_prenom'].' '.$d['ens_nom']) ?></span></td>
              <td style="font-size:.85rem;color:#4a6a6a"><?= htmlspecialchars($d['departement']) ?></td>
              <td class="date-badge"><?= (new DateTime($d['date_creation']))->format('d/m/Y') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </main>

  <!-- LECTEUR -->
  <div class="reader-overlay" id="readerOverlay">
    <div class="reader-box">
      <button class="btn-close-reader" onclick="closeReader()">✕</button>
      <div class="reader-title" id="readSubject"></div>
      <div class="reader-meta" id="readMeta"></div>
      <p style="font-size:.88rem;color:#6b8080;font-style:italic">La messagerie SmartCampus affiche les échanges entre étudiants et enseignants. En tant qu'administrateur, vous pouvez superviser toutes les discussions.</p>
    </div>
  </div>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    function readDiscussion(d) {
      document.getElementById('readSubject').textContent = d.sujet;
      document.getElementById('readMeta').innerHTML =
        '<strong>Étudiant :</strong> ' + d.etu_prenom + ' ' + d.etu_nom + ' (' + d.etu_email + ')<br>' +
        '<strong>Enseignant :</strong> ' + d.ens_prenom + ' ' + d.ens_nom + ' — ' + d.departement + '<br>' +
        '<strong>Date :</strong> ' + d.date_creation;
      document.getElementById('readerOverlay').classList.add('open');
    }
    function closeReader() { document.getElementById('readerOverlay').classList.remove('open'); }
    document.getElementById('readerOverlay').addEventListener('click', e => {
      if (e.target === document.getElementById('readerOverlay')) closeReader();
    });
  </script>
</body>
</html>
