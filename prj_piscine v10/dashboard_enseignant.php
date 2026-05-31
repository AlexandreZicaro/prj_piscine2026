<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: connexion_et_incription.html");
    exit();
}

require_once 'db.php';
$id_enseignant = $_SESSION['user_id'];

// ── 1. INFOS PERSONNELLES
$stmt = $pdo->prepare("SELECT * FROM enseignant WHERE id = ?");
$stmt->execute([$id_enseignant]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$enseignant) {
    session_destroy();
    header("Location: connexion_et_incription.html");
    exit();
}

// ── 2. MES COURS avec nombre d'inscrits
$stmtCours = $pdo->prepare("
    SELECT c.id_cours, c.nom_cours, c.description, c.capacite_max,
           COUNT(i.id_inscription) AS nb_etudiants
    FROM cours c
    LEFT JOIN inscription i ON i.id_enseignant = c.id_enseignant
    WHERE c.id_enseignant = ?
    GROUP BY c.id_cours
    ORDER BY c.nom_cours ASC
");
$stmtCours->execute([$id_enseignant]);
$cours = $stmtCours->fetchAll(PDO::FETCH_ASSOC);
$nbCours = count($cours);
$totalEtudiants = array_sum(array_column($cours, 'nb_etudiants'));

// ── 3. NOTES À SAISIR (étudiants inscrits sans note pour ce prof)
$stmtNotesASaisir = $pdo->prepare("
    SELECT COUNT(DISTINCT i.id_etudiant) AS manquantes
    FROM inscription i
    LEFT JOIN notes n ON n.id_etudiant = i.id_etudiant
        AND n.id_cours IN (SELECT id_cours FROM cours WHERE id_enseignant = ?)
    WHERE i.id_enseignant = ?
    AND n.id_notes IS NULL
");
$stmtNotesASaisir->execute([$id_enseignant, $id_enseignant]);
$notesManquantes = $stmtNotesASaisir->fetchColumn();

// ── 4. PROCHAINES SÉANCES (emploi du temps de ses cours)
$stmtSeances = $pdo->prepare("
    SELECT s.date_seance, s.heure_debut, s.heure_fin, s.salle, c.nom_cours
    FROM seance_emploidutemps s
    JOIN cours c ON s.id_cours = c.id_cours
    WHERE c.id_enseignant = ?
    AND s.date_seance >= CURDATE()
    ORDER BY s.date_seance ASC, s.heure_debut ASC
    LIMIT 4
");
$stmtSeances->execute([$id_enseignant]);
$seances = $stmtSeances->fetchAll(PDO::FETCH_ASSOC);

// ── 5. MESSAGES (discussions où il est enseignant)
$stmtMsg = $pdo->prepare("
    SELECT d.sujet, d.date_creation, e.nom AS etu_nom, e.prenom AS etu_prenom
    FROM discussion_messagerie d
    JOIN etudiant e ON d.id_etudiant = e.id
    WHERE d.id_enseignant = ?
    ORDER BY d.id_discussion DESC
    LIMIT 3
");
$stmtMsg->execute([$id_enseignant]);
$messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

// ── 6. ALERTES ABSENCES (étudiants avec beaucoup d'absences — si table présences existait)
// On affiche les dernières inscriptions pour simuler l'activité récente
$stmtActivite = $pdo->prepare("
    SELECT e.nom, e.prenom, i.date_inscription
    FROM inscription i
    JOIN etudiant e ON i.id_etudiant = e.id
    WHERE i.id_enseignant = ?
    ORDER BY i.id_inscription DESC
    LIMIT 4
");
$stmtActivite->execute([$id_enseignant]);
$activites = $stmtActivite->fetchAll(PDO::FETCH_ASSOC);

function formatDateSeance($date, $heure) {
    $ts    = strtotime($date);
    $today = date('Y-m-d');
    $tom   = date('Y-m-d', strtotime('+1 day'));
    $h     = substr($heure, 0, 5);
    if ($date === $today) return ['label' => "Auj. $h", 'isToday' => true];
    if ($date === $tom)   return ['label' => "Dem. $h", 'isToday' => false];
    $jours = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
    $j = $jours[date('D', $ts)] ?? date('D', $ts);
    return ['label' => "$j. $h", 'isToday' => false];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Enseignant - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --sky: #b8f0f0; --navy: #0d1b2a; --teal: #0a9396; --teal-light: #94d2bd; --white: #f8fffe; --alert: #e63946; --warning: #f4a261; --success: #2a9d8f; }
    html, body { min-height: 100vh; font-family: 'DM Sans', sans-serif; background: var(--sky); color: var(--navy); overflow-x: hidden; }
    .bg-blob { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.35; pointer-events: none; z-index: 0; }
    .blob1 { width: 480px; height: 480px; background: var(--teal); top: -100px; left: -80px; }
    .blob2 { width: 360px; height: 360px; background: #00b4d8; bottom: 60px; right: -60px; }

    nav { position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; background: var(--navy); padding: 0 2rem; height: 52px; z-index: 100; }
    .nav-logo-img { height: 32px; margin-right: 12px; border-radius: 4px; object-fit: contain; }
    .nav-brand { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.95rem; color: var(--white); letter-spacing: 0.04em; }
    .nav-links { margin-left: auto; display: flex; gap: 12px; align-items: center; }
    .nav-links a { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500; color: var(--sky); text-decoration: none; padding: 6px 14px; border-radius: 4px; transition: background .2s, color .2s; letter-spacing: 0.04em; text-transform: uppercase; }
    .nav-links a:hover, .nav-links a.active { background: var(--teal); color: #fff; }
    .nav-bell { position: relative; font-size: 1.1rem; color: var(--sky); cursor: pointer; margin: 0 4px; }
    .nav-bell::after { content: ''; position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: var(--alert); border-radius: 50%; border: 2px solid var(--navy); }
    .nav-logout { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600; color: #ffb3b8; text-decoration: none; padding: 6px 14px; border-radius: 4px; border: 1px solid rgba(230,57,70,0.4); transition: all .2s; }
    .nav-logout:hover { background: var(--alert); color: #fff; border-color: var(--alert); }

    .dashboard-wrapper { position: relative; z-index: 1; width: 100%; max-width: 1300px; margin: 0 auto; padding: 5rem 1.5rem 4rem; display: grid; grid-template-columns: 1fr; gap: 2.5rem; }
    @media (min-width: 992px) { .dashboard-wrapper { grid-template-columns: 2fr 1fr; } }
    .main-col, .side-col { display: flex; flex-direction: column; gap: 2rem; }

    .glass-panel { background: rgba(255,255,255,0.55); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.7); border-radius: 16px; box-shadow: 0 6px 28px rgba(0,0,0,.06); padding: 1.5rem; transition: box-shadow 0.25s; }
    .glass-panel:hover { box-shadow: 0 12px 36px rgba(10,147,150,.15); }

    .page-header h1 { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--navy); margin-bottom: 0.5rem; }
    .page-header p { font-size: 0.95rem; font-weight: 600; color: var(--navy); background: rgba(255,255,255,0.55); display: inline-block; padding: 6px 14px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.8); }
    .section-divider { border: none; border-top: 2px solid rgba(13,27,42,0.1); margin: 0.5rem 0; }

    .kpi-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
    .kpi-card { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; padding: 1.5rem 1rem; }
    .kpi-title { font-family: 'Syne', sans-serif; font-size: 0.9rem; font-weight: 700; color: var(--navy); margin-bottom: 10px; }
    .kpi-value { font-size: 2.4rem; font-weight: 800; line-height: 1; color: var(--teal); }
    .kpi-sub { font-size: 0.78rem; color: #6b8080; margin-top: 6px; }

    .card-container { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }

    .std-card h2 { font-family: 'Syne', sans-serif; font-size: 1.2rem; font-weight: 700; color: var(--navy); border-bottom: 2px solid rgba(13,27,42,0.1); padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .std-card h2 span.badge { background: var(--alert); color: var(--white); font-family: 'DM Sans', sans-serif; font-size: 0.75rem; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .std-card h2 span.badge.teal { background: var(--teal); }

    .cours-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .cours-table th { font-family: 'Syne', sans-serif; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #3d5a5a; padding: 8px 12px; text-align: left; border-bottom: 2px solid rgba(13,27,42,0.1); }
    .cours-table td { padding: 10px 12px; border-bottom: 1px solid rgba(13,27,42,0.06); color: var(--navy); }
    .cours-table tr:last-child td { border-bottom: none; }
    .cours-table .nb { font-weight: 800; color: var(--teal); }
    .btn-notes { font-size: 0.78rem; background: var(--teal); color: #fff; border: none; border-radius: 8px; padding: 5px 12px; cursor: pointer; font-family: 'Syne', sans-serif; font-weight: 700; text-decoration: none; transition: background .2s; display: inline-block; }
    .btn-notes:hover { background: #00b4d8; }
    .cours-empty { color: #6b8080; font-style: italic; font-size: 0.9rem; padding: 1rem 0; }

    .actions-header { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1.2rem; color: var(--navy); margin-bottom: 1rem; }
    .actions-grid { display: flex; flex-direction: column; gap: 1rem; }
    .action-btn { background: rgba(255,255,255,0.7); color: var(--navy); border: 1px solid rgba(255,255,255,0.9); padding: 1.2rem; border-radius: 12px; font-family: 'Syne', sans-serif; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; text-decoration: none; }
    .action-btn::after { content: '→'; font-size: 1.2rem; opacity: 0.5; transition: all 0.2s; }
    .action-btn:hover { background: var(--teal); color: var(--white); transform: translateY(-2px); }
    .action-btn:hover::after { opacity: 1; transform: translateX(4px); }

    .list-item { padding: 12px 0; border-bottom: 1px solid rgba(13,27,42,0.08); }
    .list-item:last-child { border-bottom: none; padding-bottom: 0; }
    .item-title { font-weight: 700; font-size: 0.95rem; display: block; color: var(--navy); }
    .item-sub { font-size: 0.85rem; color: #4a6a6a; margin-top: 4px; display: block; }

    .msg-btn { width: 100%; margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.6); color: var(--teal); font-family: 'Syne', sans-serif; font-weight: 700; border: 1px solid rgba(10,147,150,0.3); border-radius: 10px; text-decoration: none; display: block; text-align: center; transition: all 0.2s; }
    .msg-btn:hover { background: var(--teal); color: var(--white); }

    /* PROFIL CARD */
    .profil-card { text-align: center; }
    .profil-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #1d3557, #457b9d); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1rem; }
    .profil-name { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--navy); }
    .profil-badge { display: inline-block; margin-top: 10px; padding: 4px 14px; background: rgba(10,147,150,0.12); color: var(--teal); border-radius: 20px; font-family: 'Syne', sans-serif; font-size: 0.8rem; font-weight: 700; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem 1.5rem; margin-top: 1rem; text-align: left; }
    .info-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #6b8080; letter-spacing: 0.04em; }
    .info-val { font-size: 0.88rem; font-weight: 600; color: var(--navy); margin-top: 2px; }

    .btn-logout-card { display: block; width: 100%; margin-top: 1rem; padding: 10px; text-align: center; background: rgba(230,57,70,0.1); color: var(--alert); border: 1px solid rgba(230,57,70,0.3); border-radius: 10px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.2s; }
    .btn-logout-card:hover { background: var(--alert); color: #fff; }

    /* EDT séances */
    .edt-grid { display: flex; flex-direction: column; gap: 10px; margin-top: 0.5rem; }
    .edt-item { display: flex; gap: 14px; align-items: flex-start; padding: 12px 14px; background: rgba(255,255,255,0.6); border-radius: 10px; border-left: 4px solid var(--teal); }
    .edt-item.today { border-left-color: var(--alert); }
    .edt-time { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.82rem; color: var(--teal); min-width: 75px; }
    .edt-item.today .edt-time { color: var(--alert); }
    .edt-cours { font-weight: 700; font-size: 0.9rem; color: var(--navy); }
    .edt-detail { font-size: 0.8rem; color: #4a6a6a; margin-top: 2px; }
    .edt-empty { color: #6b8080; font-style: italic; font-size: 0.9rem; padding: 0.5rem 0; }

    .log-item { display: flex; gap: 12px; margin-bottom: 14px; }
    .log-time { font-size: 0.75rem; font-weight: 700; color: var(--teal); min-width: 50px; margin-top: 2px; }
    .log-text { font-size: 0.88rem; color: var(--navy); line-height: 1.4; }
    .activity-feed h3 { font-family: 'Syne', sans-serif; font-size: 1.2rem; margin-bottom: 1rem; color: var(--navy); border-bottom: 2px solid rgba(13,27,42,0.1); padding-bottom: 10px; }

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
      <a href="dashboard_enseignant.php" class="active">Tableau de bord</a>
      <a href="gestion_notes.php">Notes</a>
      <a href="gestion_presences.php">Présences</a>
      <a href="#"><?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></a>
      <div class="nav-bell" title="Notifications">🔔</div>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="dashboard-wrapper">

    <!-- ═══ COLONNE PRINCIPALE ═══ -->
    <div class="main-col">

      <div class="page-header">
        <h1>Bonjour, <?= htmlspecialchars($enseignant['prenom']) ?> 👋</h1>
        <p id="current-date"></p>
      </div>

      <hr class="section-divider">

      <!-- KPIs dynamiques -->
      <div class="kpi-container">
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Cours enseignés</div>
          <div class="kpi-value"><?= $nbCours ?></div>
          <div class="kpi-sub">ce semestre</div>
        </div>
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Étudiants inscrits</div>
          <div class="kpi-value"><?= $totalEtudiants ?></div>
          <div class="kpi-sub">au total</div>
        </div>
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Notes à saisir</div>
          <div class="kpi-value" style="<?= $notesManquantes > 0 ? 'color:var(--warning)' : '' ?>"><?= $notesManquantes ?></div>
          <div class="kpi-sub">étudiants sans note</div>
        </div>
      </div>

      <hr class="section-divider">

      <!-- MES COURS dynamiques -->
      <div class="glass-panel std-card">
        <h2>Mes cours <span class="badge teal">Semestre 2</span></h2>
        <?php if (empty($cours)): ?>
          <div class="cours-empty">Aucun cours assigné pour le moment.</div>
        <?php else: ?>
        <table class="cours-table">
          <thead>
            <tr>
              <th>Cours</th>
              <th>Description</th>
              <th>Capacité max</th>
              <th>Inscrits</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cours as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['nom_cours']) ?></strong></td>
              <td style="font-size:0.85rem;color:#4a6a6a"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
              <td><?= $c['capacite_max'] ?? '—' ?></td>
              <td><span class="nb"><?= $c['nb_etudiants'] ?></span></td>
              <td><a href="gestion_notes.php" class="btn-notes">Saisir notes</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <hr class="section-divider">

      <div class="card-container">
        <div>
          <div class="actions-header">Actions rapides</div>
          <div class="actions-grid">
            <a href="gestion_notes.php" class="action-btn">Saisir / Modifier des notes</a>
            <a href="gestion_presences.php" class="action-btn">Enregistrer les présences</a>
          </div>
        </div>

        <div class="glass-panel std-card">
          <h2>Messages
            <?php if (!empty($messages)): ?>
              <span class="badge"><?= count($messages) ?> discussion<?= count($messages) > 1 ? 's' : '' ?></span>
            <?php endif; ?>
          </h2>
          <?php if (empty($messages)): ?>
            <div class="list-item"><span class="item-sub" style="font-style:italic;color:#6b8080">Aucun message reçu.</span></div>
          <?php else: ?>
            <?php foreach ($messages as $m): ?>
            <div class="list-item">
              <span class="item-title">Étudiant : <?= htmlspecialchars($m['etu_prenom'] . ' ' . $m['etu_nom']) ?></span>
              <span class="item-sub">Sujet : <?= htmlspecialchars($m['sujet']) ?></span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <a href="messagerie_enseignant.php" class="msg-btn">Accéder à la messagerie</a>
        </div>
      </div>

    </div>

    <!-- ═══ COLONNE DROITE ═══ -->
    <div class="side-col">

      <!-- PROFIL DYNAMIQUE -->
      <div class="glass-panel profil-card">
        <div class="profil-avatar">👨‍🏫</div>
        <div class="profil-name"><?= htmlspecialchars($enseignant['prenom'] . ' ' . $enseignant['nom']) ?></div>
        <div class="profil-badge">Enseignant</div>
        <div class="info-grid">
          <div>
            <div class="info-lbl">Email</div>
            <div class="info-val" style="font-size:0.78rem;color:var(--teal)"><?= htmlspecialchars($enseignant['email']) ?></div>
          </div>
          <div>
            <div class="info-lbl">Téléphone</div>
            <div class="info-val">0<?= htmlspecialchars($enseignant['num_tel']) ?></div>
          </div>
          <div>
            <div class="info-lbl">Département</div>
            <div class="info-val"><?= htmlspecialchars($enseignant['departement']) ?></div>
          </div>
          <div>
            <div class="info-lbl">Diplôme</div>
            <div class="info-val" style="font-size:0.8rem"><?= htmlspecialchars($enseignant['diplomes']) ?></div>
          </div>
        </div>
        <a href="deconnexion.php" class="btn-logout-card">🚪 Se déconnecter</a>
      </div>

      <!-- EMPLOI DU TEMPS à venir -->
      <div class="glass-panel std-card">
        <h2>📅 Prochaines séances <span class="badge teal"><?= count($seances) ?></span></h2>
        <?php if (empty($seances)): ?>
          <div class="edt-empty">Aucune séance planifiée.</div>
        <?php else: ?>
        <div class="edt-grid">
          <?php foreach ($seances as $s):
            $fmt = formatDateSeance($s['date_seance'], $s['heure_debut']);
          ?>
          <div class="edt-item <?= $fmt['isToday'] ? 'today' : '' ?>">
            <div class="edt-time"><?= $fmt['label'] ?></div>
            <div>
              <div class="edt-cours"><?= htmlspecialchars($s['nom_cours']) ?></div>
              <div class="edt-detail">Salle <?= htmlspecialchars($s['salle']) ?> · <?= substr($s['heure_debut'],0,5) ?>–<?= substr($s['heure_fin'],0,5) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="gestion_presences.php" class="msg-btn" style="margin-top:1rem">Enregistrer les présences</a>
      </div>

      <!-- ACTIVITÉS RÉCENTES dynamiques -->
      <div class="glass-panel activity-feed">
        <h3>Activités récentes</h3>
        <?php if (!empty($activites)): ?>
          <?php foreach ($activites as $act): ?>
          <div class="log-item">
            <div class="log-time"><?= date('d/m', strtotime($act['date_inscription'])) ?></div>
            <div class="log-text"><strong>Inscription :</strong> <?= htmlspecialchars($act['prenom'] . ' ' . $act['nom']) ?> s'est inscrit(e) à votre cours.</div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="log-item">
            <div class="log-time">—</div>
            <div class="log-text">Aucune activité récente.</div>
          </div>
        <?php endif; ?>
        <div class="log-item">
          <div class="log-time">Système</div>
          <div class="log-text"><strong>Connexion :</strong> Session démarrée avec succès.</div>
        </div>
      </div>

    </div>
  </main>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>

  <script>
    const opts = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('current-date').textContent = new Date().toLocaleDateString('fr-FR', opts);
  </script>
</body>
</html>
