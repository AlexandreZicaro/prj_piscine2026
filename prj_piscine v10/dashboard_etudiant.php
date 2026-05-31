<?php
session_start();
// Sécurité : seuls les étudiants connectés peuvent accéder
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: connexion_et_incription.html");
    exit();
}

require_once 'db.php';

$id_etudiant = $_SESSION['user_id'];

// ── 1. INFOS PERSONNELLES de l'étudiant
$stmt = $pdo->prepare("SELECT * FROM etudiant WHERE id = ?");
$stmt->execute([$id_etudiant]);
$etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etudiant) {
    session_destroy();
    header("Location: connexion_et_incription.html");
    exit();
}

// ── 2. NOTES depuis la table notes — exclure les présences (PRESENCE_*)
$stmtNotes = $pdo->prepare("
    SELECT n.valeur, n.type_eval, c.nom_cours, e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM notes n
    JOIN cours c ON n.id_cours = c.id_cours
    JOIN enseignant e ON c.id_enseignant = e.id
    WHERE n.id_etudiant = ?
    AND n.type_eval NOT LIKE 'PRESENCE_%'
    ORDER BY n.id_notes DESC
");
$stmtNotes->execute([$id_etudiant]);
$notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

// Calcul moyenne générale (notes uniquement)
$moyenneGenerale = 0;
$nbNotes = count($notes);
if ($nbNotes > 0) {
    $somme = array_sum(array_column($notes, 'valeur'));
    $moyenneGenerale = round($somme / $nbNotes, 1);
}

// ── 2b. ABSENCES depuis les présences enregistrées
$stmtAbs = $pdo->prepare("
    SELECT n.valeur, n.type_eval, c.nom_cours
    FROM notes n
    JOIN cours c ON n.id_cours = c.id_cours
    WHERE n.id_etudiant = ?
    AND n.type_eval LIKE 'PRESENCE_%'
    ORDER BY n.type_eval DESC
");
$stmtAbs->execute([$id_etudiant]);
$presences = $stmtAbs->fetchAll(PDO::FETCH_ASSOC);
$nbAbsences = count(array_filter($presences, fn($p) => $p['valeur'] == 0));
$nbRetards  = count(array_filter($presences, fn($p) => $p['valeur'] == 0.5));

// ── 3. EMPLOI DU TEMPS : séances via les cours de l'étudiant (via inscriptions)
$stmtEdt = $pdo->prepare("
    SELECT s.date_seance, s.heure_debut, s.heure_fin, s.salle,
           c.nom_cours,
           e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM inscription i
    JOIN cours c ON i.id_enseignant = c.id_enseignant
    JOIN seance_emploidutemps s ON s.id_cours = c.id_cours
    JOIN enseignant e ON c.id_enseignant = e.id
    WHERE i.id_etudiant = ?
    ORDER BY s.date_seance ASC, s.heure_debut ASC
    LIMIT 6
");
$stmtEdt->execute([$id_etudiant]);
$seances = $stmtEdt->fetchAll(PDO::FETCH_ASSOC);

// ── 4. NOMBRE DE COURS (via inscriptions)
$stmtCours = $pdo->prepare("SELECT COUNT(*) FROM inscription WHERE id_etudiant = ?");
$stmtCours->execute([$id_etudiant]);
$nbCours = $stmtCours->fetchColumn();

// ── 5. MESSAGES NON LUS de l'étudiant (discussions le concernant)
$stmtMsg = $pdo->prepare("
    SELECT d.sujet, d.date_creation, e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM discussion_messagerie d
    JOIN enseignant e ON d.id_enseignant = e.id
    WHERE d.id_etudiant = ?
    ORDER BY d.id_discussion DESC
    LIMIT 3
");
$stmtMsg->execute([$id_etudiant]);
$messages = $stmtMsg->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers PHP
function getMention($n) {
    if ($n >= 16) return ['label' => 'TB',          'class' => 'mention-tb'];
    if ($n >= 12) return ['label' => 'Bien',        'class' => 'mention-ab'];
    if ($n >= 10) return ['label' => 'Passable',    'class' => 'mention-pass'];
    return              ['label' => 'Insuffisant', 'class' => 'mention-fail'];
}

function formatDateSeance($date, $heure) {
    $ts    = strtotime($date);
    $today = date('Y-m-d');
    $tom   = date('Y-m-d', strtotime('+1 day'));
    $h     = substr($heure, 0, 5);
    if ($date === $today) return ['label' => "Auj. $h", 'isToday' => true];
    if ($date === $tom)   return ['label' => "Dem. $h", 'isToday' => false];
    $jours = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
    $j     = $jours[date('D', $ts)] ?? date('D', $ts);
    return ['label' => "$j. $h", 'isToday' => false];
}

// Calcul âge depuis date_naissance
$age = '';
if (!empty($etudiant['date_naissance']) && $etudiant['date_naissance'] !== '0000-00-00') {
    $diff = (new DateTime())->diff(new DateTime($etudiant['date_naissance']));
    $age  = $diff->y . ' ans';
}

// Formater numéro de téléphone
$tel = $etudiant['num_tel'] ? '0' . $etudiant['num_tel'] : 'Non renseigné';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard Étudiant - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --sky: #b8f0f0; --navy: #0d1b2a; --teal: #0a9396; --teal-light: #94d2bd;
      --white: #f8fffe; --alert: #e63946; --warning: #f4a261; --success: #2a9d8f;
    }
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
    .nav-bell { position: relative; font-size: 1.1rem; color: var(--sky); cursor: pointer; margin: 0 8px; }
    .nav-bell::after { content: ''; position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: var(--alert); border-radius: 50%; border: 2px solid var(--navy); }

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
    .std-card h2 span.badge { background: var(--teal); color: var(--white); font-family: 'DM Sans', sans-serif; font-size: 0.75rem; padding: 4px 10px; border-radius: 12px; font-weight: 600; }
    .std-card h2 span.badge.red { background: var(--alert); }

    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th { font-family: 'Syne', sans-serif; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #3d5a5a; padding: 8px 12px; text-align: left; border-bottom: 2px solid rgba(13,27,42,0.1); }
    td { padding: 10px 12px; border-bottom: 1px solid rgba(13,27,42,0.06); }
    tr:last-child td { border-bottom: none; }

    .badge-mention { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .mention-tb   { background: rgba(42,157,143,0.15); color: var(--success); }
    .mention-ab   { background: rgba(10,147,150,0.15);  color: var(--teal); }
    .mention-pass { background: rgba(244,162,97,0.15);  color: #c06a10; }
    .mention-fail { background: rgba(230,57,70,0.15);   color: var(--alert); }

    .edt-grid { display: flex; flex-direction: column; gap: 10px; }
    .edt-item { display: flex; gap: 14px; align-items: flex-start; padding: 14px 16px; background: rgba(255,255,255,0.6); border-radius: 12px; border-left: 4px solid var(--teal); transition: transform 0.2s; }
    .edt-item:hover { transform: translateX(4px); }
    .edt-item.today { border-left-color: var(--alert); background: rgba(230,57,70,0.05); }
    .edt-time { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.82rem; color: var(--teal); min-width: 80px; padding-top: 2px; }
    .edt-item.today .edt-time { color: var(--alert); }
    .cours-name { font-weight: 700; font-size: 0.95rem; color: var(--navy); }
    .cours-detail { font-size: 0.82rem; color: #4a6a6a; margin-top: 3px; }

    .edt-empty { color: #6b8080; font-style: italic; font-size: 0.9rem; padding: 1rem 0; }

    .action-btn { background: rgba(255,255,255,0.7); color: var(--navy); border: 1px solid rgba(255,255,255,0.9); padding: 1rem; border-radius: 12px; font-family: 'Syne', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; text-decoration: none; }
    .action-btn::after { content: '→'; font-size: 1.2rem; opacity: 0.5; transition: all 0.2s; }
    .action-btn:hover { background: var(--teal); color: var(--white); transform: translateY(-2px); }
    .action-btn:hover::after { opacity: 1; transform: translateX(4px); }

    .list-item { padding: 12px 0; border-bottom: 1px solid rgba(13,27,42,0.08); }
    .list-item:last-child { border-bottom: none; padding-bottom: 0; }
    .item-title { font-weight: 700; font-size: 0.95rem; display: block; color: var(--navy); }
    .item-sub { font-size: 0.85rem; color: #4a6a6a; margin-top: 4px; display: block; }

    .msg-btn { width: 100%; margin-top: 15px; padding: 12px; background: rgba(255,255,255,0.6); color: var(--teal); font-family: 'Syne', sans-serif; font-weight: 700; border: 1px solid rgba(10,147,150,0.3); border-radius: 10px; text-decoration: none; display: block; text-align: center; transition: all 0.2s; }
    .msg-btn:hover { background: var(--teal); color: var(--white); }

    .log-item { display: flex; gap: 12px; margin-bottom: 15px; }
    .log-time { font-size: 0.75rem; font-weight: 700; color: var(--teal); min-width: 50px; margin-top: 2px; }
    .log-text { font-size: 0.88rem; color: var(--navy); line-height: 1.4; }

    /* PROFIL */
    .profil-card { text-align: center; }
    .profil-avatar { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--teal), #00b4d8); display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1rem; }
    .profil-name { font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--navy); }
    .profil-detail { font-size: 0.85rem; color: #4a6a6a; margin-top: 4px; line-height: 1.7; }
    .profil-badge { display: inline-block; margin-top: 10px; padding: 4px 14px; background: rgba(10,147,150,0.12); color: var(--teal); border-radius: 20px; font-family: 'Syne', sans-serif; font-size: 0.8rem; font-weight: 700; }

    /* INFOS PERSO */
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem 1.5rem; margin-top: 0.8rem; }
    .info-row { display: flex; flex-direction: column; }
    .info-lbl { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #6b8080; letter-spacing: 0.04em; }
    .info-val { font-size: 0.88rem; font-weight: 600; color: var(--navy); margin-top: 2px; }

    .btn-logout { display: block; width: 100%; margin-top: 1rem; padding: 10px; text-align: center; background: rgba(230,57,70,0.1); color: var(--alert); border: 1px solid rgba(230,57,70,0.3); border-radius: 10px; font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.85rem; text-decoration: none; transition: all 0.2s; }
    .btn-logout:hover { background: var(--alert); color: #fff; }

    /* Note vide */
    .notes-empty { color: #6b8080; font-style: italic; font-size: 0.9rem; padding: 1rem 0; text-align: center; }

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
      <a href="dashboard_etudiant.php" class="active">Mon espace</a>
      <a href="messagerie_etudiant.php">Messagerie</a>
      <a href="#">Profil : <?= htmlspecialchars($etudiant['prenom']) ?></a>
      <div class="nav-bell" title="Notifications">🔔</div>
    </div>
  </nav>

  <main class="dashboard-wrapper">

    <!-- ═══════════════════ COLONNE PRINCIPALE ═══════════════════ -->
    <div class="main-col">

      <div class="page-header">
        <h1>Bienvenue, <?= htmlspecialchars($etudiant['prenom']) ?> 👋</h1>
        <p id="current-date"></p>
      </div>

      <hr class="section-divider">

      <!-- KPIs dynamiques -->
      <div class="kpi-container">
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Moyenne Générale</div>
          <div class="kpi-value"><?= $nbNotes > 0 ? $moyenneGenerale : '—' ?></div>
          <div class="kpi-sub">sur 20 — <?= $nbNotes ?> note<?= $nbNotes > 1 ? 's' : '' ?> enregistrée<?= $nbNotes > 1 ? 's' : '' ?></div>
        </div>
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Cours inscrits</div>
          <div class="kpi-value"><?= $nbCours ?: '0' ?></div>
          <div class="kpi-sub">ce semestre</div>
        </div>
        <div class="glass-panel kpi-card">
          <div class="kpi-title">Absences</div>
          <div class="kpi-value" style="<?= $nbAbsences >= 5 ? 'color:var(--alert)' : '' ?>"><?= $nbAbsences ?></div>
          <div class="kpi-sub"><?= $nbRetards ?> retard<?= $nbRetards > 1 ? 's' : '' ?> — <?= count($presences) ?> séance<?= count($presences) > 1 ? 's' : '' ?> enregistrée<?= count($presences) > 1 ? 's' : '' ?></div>
        </div>
      </div>

      <hr class="section-divider">

      <!-- EMPLOI DU TEMPS -->
      <div class="glass-panel std-card">
        <h2>📅 Emploi du temps <span class="badge">À venir</span></h2>
        <div class="edt-grid">
          <?php if (empty($seances)): ?>
            <div class="edt-empty">Aucune séance planifiée pour le moment.</div>
          <?php else: ?>
            <?php foreach ($seances as $s):
              $fmt = formatDateSeance($s['date_seance'], $s['heure_debut']);
              $heureDebut = substr($s['heure_debut'], 0, 5);
              $heureFin   = substr($s['heure_fin'],   0, 5);
            ?>
            <div class="edt-item <?= $fmt['isToday'] ? 'today' : '' ?>">
              <div class="edt-time"><?= $fmt['label'] ?></div>
              <div class="edt-info">
                <div class="cours-name"><?= htmlspecialchars($s['nom_cours']) ?></div>
                <div class="cours-detail">
                  Salle <?= htmlspecialchars($s['salle']) ?> — 
                  <?= htmlspecialchars($s['ens_prenom'] . ' ' . $s['ens_nom']) ?> · 
                  <?= $heureDebut ?> – <?= $heureFin ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <hr class="section-divider">

      <!-- NOTES + MESSAGES -->
      <div class="card-container">

        <!-- NOTES DYNAMIQUES -->
        <div class="glass-panel std-card">
          <h2>📝 Mes notes <span class="badge"><?= $nbNotes ?> résultat<?= $nbNotes > 1 ? 's' : '' ?></span></h2>
          <?php if (empty($notes)): ?>
            <div class="notes-empty">Aucune note disponible pour le moment.</div>
          <?php else: ?>
          <table>
            <thead>
              <tr><th>Cours</th><th>Éval.</th><th>Note</th><th>Mention</th></tr>
            </thead>
            <tbody>
              <?php foreach ($notes as $n):
                $m = getMention($n['valeur']);
              ?>
              <tr>
                <td><?= htmlspecialchars($n['nom_cours']) ?></td>
                <td style="font-size:0.8rem;color:#6b8080"><?= htmlspecialchars($n['type_eval']) ?></td>
                <td><strong><?= $n['valeur'] ?>/20</strong></td>
                <td><span class="badge-mention <?= $m['class'] ?>"><?= $m['label'] ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <!-- ABSENCES -->
        <div class="glass-panel std-card">
          <h2>📋 Mes absences
            <?php if ($nbAbsences >= 5): ?>
              <span class="badge red">⚠️ Seuil atteint</span>
            <?php elseif ($nbAbsences > 0): ?>
              <span class="badge"><?= $nbAbsences ?> abs.</span>
            <?php endif; ?>
          </h2>
          <?php if (empty($presences)): ?>
            <div class="notes-empty">Aucune séance enregistrée pour le moment.</div>
          <?php else: ?>
          <table>
            <thead>
              <tr><th>Cours</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody>
              <?php foreach ($presences as $p):
                $dateStr = str_replace('PRESENCE_', '', $p['type_eval']);
                try { $dateF = (new DateTime($dateStr))->format('d/m/Y'); } catch(Exception $e) { $dateF = $dateStr; }
                if ($p['valeur'] == 1)   { $statut = '<span style="color:#2a9d8f;font-weight:700">✅ Présent(e)</span>'; }
                elseif ($p['valeur'] == 0.5) { $statut = '<span style="color:#f4a261;font-weight:700">⏰ Retard</span>'; }
                else { $statut = '<span style="color:#e63946;font-weight:700">❌ Absent(e)</span>'; }
              ?>
              <tr>
                <td><?= htmlspecialchars($p['nom_cours']) ?></td>
                <td style="font-size:0.85rem;color:#4a6a6a"><?= $dateF ?></td>
                <td><?= $statut ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <!-- MESSAGES DYNAMIQUES -->
        <div class="glass-panel std-card">
          <h2>💬 Messages
            <?php if (count($messages) > 0): ?>
              <span class="badge red"><?= count($messages) ?> discussion<?= count($messages) > 1 ? 's' : '' ?></span>
            <?php endif; ?>
          </h2>
          <?php if (empty($messages)): ?>
            <div class="list-item">
              <span class="item-sub" style="font-style:italic;color:#6b8080">Aucun message pour le moment.</span>
            </div>
          <?php else: ?>
            <?php foreach ($messages as $msg): ?>
            <div class="list-item">
              <span class="item-title"><?= htmlspecialchars($msg['ens_prenom'] . ' ' . $msg['ens_nom']) ?></span>
              <span class="item-sub">Sujet : <?= htmlspecialchars($msg['sujet']) ?></span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <a href="messagerie_etudiant.php" class="msg-btn">Accéder à la messagerie</a>
        </div>

      </div>

    </div>

    <!-- ═══════════════════ COLONNE DROITE ═══════════════════ -->
    <div class="side-col">

      <!-- CARTE PROFIL DYNAMIQUE -->
      <div class="glass-panel profil-card">
        <div class="profil-avatar">🎓</div>
        <div class="profil-name"><?= htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']) ?></div>
        <div class="profil-badge">Étudiant actif</div>

        <!-- Infos personnelles depuis la BDD -->
        <div class="info-grid" style="text-align:left;margin-top:1.2rem;">
          <div class="info-row">
            <span class="info-lbl">Email</span>
            <span class="info-val" style="font-size:0.8rem;color:var(--teal)"><?= htmlspecialchars($etudiant['email']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-lbl">Téléphone</span>
            <span class="info-val"><?= htmlspecialchars($tel) ?></span>
          </div>
          <div class="info-row">
            <span class="info-lbl">Classe</span>
            <span class="info-val"><?= htmlspecialchars($etudiant['classe']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-lbl">Promotion</span>
            <span class="info-val"><?= htmlspecialchars($etudiant['promo'] ?? '—') ?></span>
          </div>
          <?php if ($age): ?>
          <div class="info-row">
            <span class="info-lbl">Âge</span>
            <span class="info-val"><?= $age ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($etudiant['diplome']) && $etudiant['diplome'] !== 'Non défini'): ?>
          <div class="info-row">
            <span class="info-lbl">Dernier diplôme</span>
            <span class="info-val"><?= htmlspecialchars($etudiant['diplome']) ?></span>
          </div>
          <?php endif; ?>
        </div>

        <a href="deconnexion.php" class="btn-logout">🚪 Se déconnecter</a>
      </div>

      <!-- ACTIONS RAPIDES -->
      <div class="glass-panel std-card">
        <h2>⚡ Actions rapides</h2>
        <div style="display:flex;flex-direction:column;gap:0.8rem;">
          <a href="messagerie_etudiant.php" class="action-btn">Envoyer un message</a>
          <a href="#" class="action-btn" onclick="alert('Fonctionnalité disponible prochainement.')">Télécharger mon EDT</a>
          <a href="#" class="action-btn" onclick="alert('Fonctionnalité disponible prochainement.')">Télécharger relevé de notes</a>
        </div>
      </div>

      <!-- ACTIVITÉS RÉCENTES -->
      <div class="glass-panel">
        <h3 style="font-family:'Syne',sans-serif;font-size:1.2rem;margin-bottom:1rem;color:var(--navy);border-bottom:2px solid rgba(13,27,42,0.1);padding-bottom:10px;">Activités récentes</h3>
        <?php if (!empty($notes)): ?>
        <div class="log-item">
          <div class="log-time">Récent</div>
          <div class="log-text"><strong>Note publiée :</strong> <?= htmlspecialchars($notes[0]['nom_cours']) ?> — <?= $notes[0]['valeur'] ?>/20.</div>
        </div>
        <?php endif; ?>
        <?php if (!empty($messages)): ?>
        <div class="log-item">
          <div class="log-time">Récent</div>
          <div class="log-text"><strong>Message reçu :</strong> <?= htmlspecialchars($messages[0]['sujet']) ?></div>
        </div>
        <?php endif; ?>
        <div class="log-item">
          <div class="log-time">Système</div>
          <div class="log-text"><strong>Connexion :</strong> Session démarrée avec succès.</div>
        </div>
        <div class="log-item">
          <div class="log-time">Info</div>
          <div class="log-text"><strong>SmartCampus :</strong> Bienvenue sur votre espace étudiant.</div>
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
