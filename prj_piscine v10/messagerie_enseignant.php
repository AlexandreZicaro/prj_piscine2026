<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: connexion_et_incription.html"); exit();
}
require_once 'db.php';
$id_enseignant = $_SESSION['user_id'];

// Infos enseignant
$stmtEns = $pdo->prepare("SELECT * FROM enseignant WHERE id=?");
$stmtEns->execute([$id_enseignant]);
$enseignant = $stmtEns->fetch(PDO::FETCH_ASSOC);

// Messages reçus (discussions où il est l'enseignant)
$stmtInbox = $pdo->prepare("
    SELECT d.id_discussion, d.sujet, d.date_creation,
           e.nom AS etu_nom, e.prenom AS etu_prenom, e.email AS etu_email, e.classe
    FROM discussion_messagerie d
    JOIN etudiant e ON d.id_etudiant = e.id
    WHERE d.id_enseignant = ?
    ORDER BY d.id_discussion DESC
");
$stmtInbox->execute([$id_enseignant]);
$inbox = $stmtInbox->fetchAll(PDO::FETCH_ASSOC);
$nbMessages = count($inbox);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Messagerie Enseignant - SmartCampus</title>
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
    .page-wrapper{position:relative;z-index:1;width:100%;max-width:1100px;margin:0 auto;padding:6rem 1.5rem 5rem;display:flex;flex-direction:column;gap:2rem}
    .glass-panel{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.08);padding:1.5rem 2rem}
    .page-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy);background:rgba(255,255,255,.55);padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,.8);display:inline-block}
    .section-title{font-family:'Syne',sans-serif;font-weight:700;font-size:1.2rem;color:var(--navy);margin-bottom:1.2rem;padding-bottom:10px;border-bottom:2px solid rgba(13,27,42,.1);display:flex;justify-content:space-between;align-items:center}
    .badge{font-size:.75rem;font-weight:700;background:var(--alert);color:#fff;padding:4px 10px;border-radius:12px;font-family:'DM Sans',sans-serif}
    .badge.teal{background:var(--teal)}
    /* Liste messages */
    .msg-list{display:flex;flex-direction:column;gap:10px}
    .msg-item{background:rgba(255,255,255,.6);border:1px solid rgba(13,27,42,.06);border-radius:12px;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all .2s;border-left:4px solid transparent}
    .msg-item:hover{background:#fff;transform:translateX(4px);border-left-color:var(--teal)}
    .msg-item.unread{background:rgba(255,255,255,.9);border-left-color:var(--teal)}
    .msg-meta{display:flex;flex-direction:column;gap:4px}
    .msg-sender{font-size:.95rem;font-weight:700;color:var(--navy)}
    .msg-subject{font-size:.88rem;color:#4a5a5a}
    .msg-classe{font-size:.78rem;color:#6b8080;margin-top:2px}
    .msg-date{font-size:.8rem;color:#6b8080;white-space:nowrap;margin-left:1.5rem;flex-shrink:0}
    .empty-state{text-align:center;padding:2.5rem;color:#6b8080;font-style:italic}
    /* Stat cards */
    .stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}
    .stat-card{text-align:center;padding:1rem}
    .stat-val{font-family:'Syne',sans-serif;font-size:2rem;font-weight:800;color:var(--teal)}
    .stat-lbl{font-size:.78rem;font-weight:600;color:#4a6a6a;text-transform:uppercase;margin-top:4px}
    /* Lecteur */
    .reader-overlay{position:fixed;inset:0;background:rgba(13,27,42,.5);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:300;opacity:0;pointer-events:none;transition:opacity .3s}
    .reader-overlay.open{opacity:1;pointer-events:all}
    .reader-box{background:var(--white);border-radius:20px;width:580px;max-width:94vw;padding:2.2rem;box-shadow:0 24px 70px rgba(0,0,0,.2);position:relative}
    .reader-title{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--navy);margin-bottom:12px}
    .reader-meta{font-size:.88rem;color:#4a6a6a;line-height:1.9;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid rgba(13,27,42,.1)}
    .reader-meta strong{color:var(--navy)}
    .reader-info{font-size:.9rem;color:var(--navy);line-height:1.6;background:rgba(10,147,150,.06);padding:14px 18px;border-radius:10px;border-left:3px solid var(--teal)}
    .btn-close-reader{position:absolute;top:16px;right:20px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6b8080}
    .btn-close-reader:hover{color:var(--navy)}
    footer{position:fixed;bottom:0;left:0;right:0;height:42px;background:var(--navy);display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;font-size:.78rem;color:var(--teal-light);z-index:100}
  </style>
</head>
<body>
  <div class="bg-blob blob1"></div><div class="bg-blob blob2"></div>
  <nav>
    <img src="logo.jpg" alt="Logo" class="nav-logo-img">
    <span class="nav-brand">SmartCampus</span>
    <div class="nav-links">
      <a href="dashboard_enseignant.php">Tableau de bord</a>
      <a href="gestion_notes.php">Notes</a>
      <a href="gestion_presences.php">Présences</a>
      <a href="messagerie_enseignant.php" class="active">Messagerie</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">

    <div class="page-title">💬 Ma Messagerie</div>

    <!-- STATS -->
    <div class="glass-panel">
      <div class="stat-row">
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $nbMessages ?></div>
          <div class="stat-lbl">Message<?= $nbMessages>1?'s':'' ?> reçu<?= $nbMessages>1?'s':'' ?></div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= htmlspecialchars($enseignant['departement']) ?></div>
          <div class="stat-lbl">Département</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val" style="font-size:1.1rem;padding-top:.4rem"><?= htmlspecialchars($enseignant['prenom'].' '.$enseignant['nom']) ?></div>
          <div class="stat-lbl">Compte connecté</div>
        </div>
      </div>
    </div>

    <!-- LISTE DES MESSAGES -->
    <div class="glass-panel">
      <div class="section-title">
        📥 Messages reçus de mes étudiants
        <?php if ($nbMessages > 0): ?>
        <span class="badge"><?= $nbMessages ?> message<?= $nbMessages>1?'s':'' ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($inbox)): ?>
        <div class="empty-state">
          Aucun message reçu pour l'instant.<br>
          <span style="font-size:.85rem;margin-top:8px;display:block">Vos étudiants peuvent vous envoyer des messages depuis leur espace étudiant.</span>
        </div>
      <?php else: ?>
      <div class="msg-list">
        <?php foreach ($inbox as $i => $msg):
          $dateF = (new DateTime($msg['date_creation']))->format('d/m/Y');
        ?>
        <div class="msg-item <?= $i < 3 ? 'unread' : '' ?>"
             onclick="readMessage(<?= htmlspecialchars(json_encode($msg), ENT_QUOTES) ?>)">
          <div class="msg-meta">
            <span class="msg-sender">🎓 <?= htmlspecialchars($msg['etu_prenom'].' '.$msg['etu_nom']) ?></span>
            <span class="msg-subject">Sujet : <?= htmlspecialchars($msg['sujet']) ?></span>
            <span class="msg-classe"><?= htmlspecialchars($msg['classe']) ?> — <?= htmlspecialchars($msg['etu_email']) ?></span>
          </div>
          <div class="msg-date"><?= $dateF ?></div>
        </div>
        <?php endforeach; ?>
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
      <div class="reader-info" id="readInfo"></div>
    </div>
  </div>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    function readMessage(m) {
      document.getElementById('readSubject').textContent = m.sujet;
      document.getElementById('readMeta').innerHTML =
        '<strong>De :</strong> ' + m.etu_prenom + ' ' + m.etu_nom +
        ' &lt;' + m.etu_email + '&gt;<br>' +
        '<strong>Classe :</strong> ' + m.classe + '<br>' +
        '<strong>Date :</strong> ' + m.date_creation;
      document.getElementById('readInfo').innerHTML =
        '💡 Cet étudiant vous a contacté au sujet de : <strong>' + m.sujet + '</strong><br>' +
        'Pour lui répondre, utilisez son adresse email : <a href="mailto:' + m.etu_email + '" style="color:var(--teal)">' + m.etu_email + '</a>';
      document.getElementById('readerOverlay').classList.add('open');
    }
    function closeReader() { document.getElementById('readerOverlay').classList.remove('open'); }
    document.getElementById('readerOverlay').addEventListener('click', e => {
      if (e.target === document.getElementById('readerOverlay')) closeReader();
    });
  </script>
</body>
</html>
