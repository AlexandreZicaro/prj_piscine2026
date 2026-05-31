<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: connexion_et_incription.html"); exit();
}
require_once 'db.php';
$id_etudiant = $_SESSION['user_id'];

$message = ''; $messageType = '';

// ── ENVOYER UN MESSAGE (créer une discussion) ──────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $id_enseignant = intval($_POST['id_enseignant']);
    $sujet         = trim($_POST['sujet']);
    if ($id_enseignant && $sujet) {
        try {
            $pdo->prepare("INSERT INTO discussion_messagerie (sujet, date_creation, id_etudiant, id_enseignant) VALUES (?, CURDATE(), ?, ?)")
                ->execute([$sujet, $id_etudiant, $id_enseignant]);
            $message = "✅ Message envoyé avec succès !"; $messageType = 'success';
        } catch (PDOException $e) {
            $message = "❌ Erreur : " . $e->getMessage(); $messageType = 'danger';
        }
    } else {
        $message = "⚠️ Veuillez remplir tous les champs."; $messageType = 'warning';
    }
}

// ── DONNÉES ──────────────────────────────────────────────────
// Messages reçus (discussions où l'étudiant est concerné)
$stmtInbox = $pdo->prepare("
    SELECT d.id_discussion, d.sujet, d.date_creation,
           e.nom AS ens_nom, e.prenom AS ens_prenom, e.email AS ens_email
    FROM discussion_messagerie d
    JOIN enseignant e ON d.id_enseignant = e.id
    WHERE d.id_etudiant = ?
    ORDER BY d.id_discussion DESC
");
$stmtInbox->execute([$id_etudiant]);
$inbox = $stmtInbox->fetchAll(PDO::FETCH_ASSOC);

// Messages envoyés (même table, vus depuis l'étudiant)
$sent = $inbox; // c'est la même table, la discussion = échange étudiant↔enseignant

// Enseignants disponibles (ceux auxquels il est inscrit)
$stmtEns = $pdo->prepare("
    SELECT DISTINCT e.id, e.nom, e.prenom, e.departement
    FROM inscription i JOIN enseignant e ON i.id_enseignant = e.id
    WHERE i.id_etudiant = ?
    ORDER BY e.nom ASC
");
$stmtEns->execute([$id_etudiant]);
$enseignants = $stmtEns->fetchAll(PDO::FETCH_ASSOC);

// Si pas d'enseignants inscrits, charger tous les enseignants
if (empty($enseignants)) {
    $enseignants = $pdo->query("SELECT id, nom, prenom, departement FROM enseignant ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$nbNonLus = count($inbox); // Simplifié, tous comptent
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Messagerie - SmartCampus</title>
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
    .page-wrapper{position:relative;z-index:1;width:100%;max-width:1200px;margin:0 auto;padding:6rem 1.5rem 5rem}
    .messagerie-container{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.08);display:flex;flex-direction:column;overflow:hidden}
    .messagerie-header{padding:20px 30px;border-bottom:2px solid rgba(13,27,42,.08);display:flex;justify-content:space-between;align-items:center}
    .messagerie-header h1{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy)}
    .btn-compose{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:8px;padding:10px 20px;font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem;cursor:pointer;transition:transform .2s}
    .btn-compose:hover{transform:translateY(-2px)}
    .messagerie-body{display:flex;min-height:500px}
    .messagerie-sidebar{width:220px;border-right:2px solid rgba(13,27,42,.08);background:rgba(255,255,255,.3);padding:20px 0;flex-shrink:0}
    .sidebar-menu{list-style:none;display:flex;flex-direction:column;gap:4px}
    .sidebar-menu li{padding:14px 24px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:.92rem;cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition:all .2s}
    .sidebar-menu li:hover,.sidebar-menu li.active{background:rgba(10,147,150,.12);color:var(--teal)}
    .sidebar-menu li.active{border-left:4px solid var(--teal)}
    .badge{font-size:.72rem;font-weight:700;background:var(--teal);color:var(--white);padding:3px 8px;border-radius:12px}
    .badge.red{background:var(--alert)}
    .messagerie-content{flex:1;padding:24px 32px;display:flex;flex-direction:column;gap:16px;min-width:0}
    .view-pane{display:none;flex-direction:column;gap:14px}
    .view-pane.active{display:flex}
    .email-list{display:flex;flex-direction:column;gap:10px}
    .email-item{background:rgba(255,255,255,.6);border:1px solid rgba(13,27,42,.06);border-radius:10px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;transition:all .2s}
    .email-item:hover{background:#fff;transform:translateX(4px);border-color:var(--teal-light)}
    .email-item.unread{background:rgba(255,255,255,.9);border-left:4px solid var(--teal)}
    .email-meta{display:flex;flex-direction:column;gap:3px}
    .email-sender{font-size:.95rem;color:var(--navy);font-weight:700}
    .email-subject{font-size:.88rem;color:#4a5a5a}
    .email-date{font-size:.8rem;color:#6b8080;white-space:nowrap;margin-left:1rem}
    .flash{padding:12px 18px;border-radius:10px;font-weight:600;font-size:.9rem}
    .flash.success{background:rgba(42,157,143,.15);color:var(--success);border:1px solid rgba(42,157,143,.3)}
    .flash.warning{background:rgba(244,162,97,.15);color:#c06a10;border:1px solid rgba(244,162,97,.4)}
    .flash.danger{background:rgba(230,57,70,.1);color:var(--alert);border:1px solid rgba(230,57,70,.3)}
    .input-group{display:flex;flex-direction:column;gap:6px}
    .input-group label{font-family:'Syne',sans-serif;font-size:.75rem;font-weight:700;text-transform:uppercase;color:#4a6a6a}
    .msg-input,.msg-select,.msg-textarea{width:100%;background:rgba(255,255,255,.8);border:1.5px solid #cce0e0;border-radius:10px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:.95rem;color:var(--navy);outline:none;transition:border-color .2s}
    .msg-input:focus,.msg-select:focus,.msg-textarea:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(10,147,150,.12);background:#fff}
    .msg-textarea{resize:vertical;min-height:120px}
    .btn-submit-msg{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:10px;padding:12px 32px;font-family:'Syne',sans-serif;font-size:1rem;font-weight:700;cursor:pointer;align-self:flex-end}
    .btn-submit-msg:hover{opacity:.9}
    .empty-inbox{color:#6b8080;font-style:italic;text-align:center;padding:2rem}
    /* Lecteur */
    .reader-overlay{position:fixed;inset:0;background:rgba(13,27,42,.5);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:300;opacity:0;pointer-events:none;transition:opacity .3s}
    .reader-overlay.open{opacity:1;pointer-events:all}
    .reader-box{background:var(--white);border-radius:16px;width:560px;max-width:94vw;padding:2rem;box-shadow:0 20px 60px rgba(0,0,0,.2);position:relative}
    .reader-title{font-family:'Syne',sans-serif;font-size:1.2rem;font-weight:800;color:var(--navy);margin-bottom:8px}
    .reader-meta{font-size:.85rem;color:#6b8080;margin-bottom:1.2rem;padding-bottom:1rem;border-bottom:1px solid rgba(13,27,42,.1)}
    .reader-body{font-size:.95rem;color:var(--navy);line-height:1.7}
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
      <a href="dashboard_etudiant.php">Mon espace</a>
      <a href="messagerie_etudiant.php" class="active">Messagerie</a>
      <a href="#">Profil : Étudiant</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">
    <div class="messagerie-container">
      <div class="messagerie-header">
        <h1>💬 Ma Messagerie</h1>
        <button class="btn-compose" onclick="switchPane('pane-compose')">✏️ Nouveau message</button>
      </div>
      <div class="messagerie-body">
        <ul class="messagerie-sidebar">
          <li class="sidebar-menu" style="list-style:none;padding:0">
            <ul class="sidebar-menu">
              <li class="active" id="menu-inbox" onclick="switchPane('pane-inbox')">
                📥 Réception
                <?php if ($nbNonLus > 0): ?><span class="badge red"><?= $nbNonLus ?></span><?php endif; ?>
              </li>
              <li id="menu-compose" onclick="switchPane('pane-compose')">✏️ Nouveau</li>
            </ul>
          </li>
        </ul>

        <div class="messagerie-content">
          <?php if ($message): ?>
          <div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
          <?php endif; ?>

          <!-- INBOX -->
          <div class="view-pane active" id="pane-inbox">
            <?php if (empty($inbox)): ?>
              <div class="empty-inbox">Aucun message pour le moment.<br>Envoyez un message à un enseignant !</div>
            <?php else: ?>
            <div class="email-list">
              <?php foreach ($inbox as $i => $msg):
                $dateF = (new DateTime($msg['date_creation']))->format('d/m/Y');
                $bodyPreview = "Sujet : " . $msg['sujet'] . "\n\nDiscussion ouverte avec " . $msg['ens_prenom'] . " " . $msg['ens_nom'] . " (" . $msg['ens_email'] . ")";
              ?>
              <div class="email-item <?= $i===0?'unread':'' ?>"
                   onclick="readEmail('<?= htmlspecialchars($msg['ens_prenom'].' '.$msg['ens_nom'], ENT_QUOTES) ?>', '<?= htmlspecialchars($msg['sujet'], ENT_QUOTES) ?>', '<?= htmlspecialchars($bodyPreview, ENT_QUOTES) ?>')">
                <div class="email-meta">
                  <span class="email-sender"><?= htmlspecialchars($msg['ens_prenom'].' '.$msg['ens_nom']) ?> (<?= htmlspecialchars($msg['ens_email']) ?>)</span>
                  <span class="email-subject">Sujet : <?= htmlspecialchars($msg['sujet']) ?></span>
                </div>
                <div class="email-date"><?= $dateF ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- COMPOSE -->
          <div class="view-pane" id="pane-compose">
            <form method="POST" action="messagerie_etudiant.php" style="display:flex;flex-direction:column;gap:14px">
              <input type="hidden" name="action" value="send">
              <div class="input-group">
                <label>Destinataire (Enseignant)</label>
                <select name="id_enseignant" class="msg-select" required>
                  <option value="" disabled selected>Choisir un enseignant</option>
                  <?php foreach ($enseignants as $e): ?>
                  <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'].' '.$e['nom']) ?> — <?= htmlspecialchars($e['departement']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="input-group">
                <label>Sujet du message</label>
                <input type="text" name="sujet" class="msg-input" placeholder="Ex : Question sur le CC1 d'Algorithmique" required>
              </div>
              <div class="input-group">
                <label>Message (optionnel — précisions)</label>
                <textarea class="msg-textarea" name="corps" placeholder="Rédigez votre message ici..."></textarea>
              </div>
              <button type="submit" class="btn-submit-msg">📨 Envoyer</button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </main>

  <!-- LECTEUR -->
  <div class="reader-overlay" id="readerOverlay">
    <div class="reader-box">
      <button class="btn-close-reader" onclick="closeReader()">✕</button>
      <div class="reader-title" id="readSubject"></div>
      <div class="reader-meta">De : <strong id="readSender"></strong></div>
      <div class="reader-body" id="readBody"></div>
    </div>
  </div>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    function switchPane(id) {
      document.querySelectorAll('.view-pane').forEach(p => p.classList.remove('active'));
      document.querySelectorAll('.sidebar-menu li').forEach(l => l.classList.remove('active'));
      document.getElementById(id).classList.add('active');
      if (id==='pane-inbox') document.getElementById('menu-inbox').classList.add('active');
      if (id==='pane-compose') document.getElementById('menu-compose').classList.add('active');
    }
    function readEmail(sender, subject, body) {
      document.getElementById('readSender').textContent = sender;
      document.getElementById('readSubject').textContent = subject;
      document.getElementById('readBody').textContent = body;
      document.getElementById('readerOverlay').classList.add('open');
    }
    function closeReader() { document.getElementById('readerOverlay').classList.remove('open'); }
    document.getElementById('readerOverlay').addEventListener('click', e => {
      if (e.target === document.getElementById('readerOverlay')) closeReader();
    });
    <?php if ($messageType === 'success'): ?>
    switchPane('pane-inbox');
    <?php endif; ?>
  </script>
</body>
</html>
