<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: connexion_et_incription.html");
    exit();
}
require_once 'db.php';

$message     = '';
$messageType = '';

// ── ACTIONS POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ajouter une séance
    if ($action === 'add_seance') {
        $id_cours   = intval($_POST['id_cours']);
        $date       = $_POST['date_seance'];
        $heure_debut= $_POST['heure_debut'];
        $heure_fin  = $_POST['heure_fin'];
        $salle      = trim($_POST['salle']);
        $stmt = $pdo->prepare("INSERT INTO seance_emploidutemps (date_seance, heure_debut, heure_fin, salle, id_cours) VALUES (?,?,?,?,?)");
        $stmt->execute([$date, $heure_debut, $heure_fin, $salle, $id_cours]);
        $message = "✅ Séance planifiée avec succès.";
        $messageType = 'success';
    }

    // Modifier une séance
    elseif ($action === 'edit_seance') {
        $id         = intval($_POST['id_seance']);
        $id_cours   = intval($_POST['id_cours']);
        $date       = $_POST['date_seance'];
        $heure_debut= $_POST['heure_debut'];
        $heure_fin  = $_POST['heure_fin'];
        $salle      = trim($_POST['salle']);
        $stmt = $pdo->prepare("UPDATE seance_emploidutemps SET date_seance=?, heure_debut=?, heure_fin=?, salle=?, id_cours=? WHERE id_seance=?");
        $stmt->execute([$date, $heure_debut, $heure_fin, $salle, $id_cours, $id]);
        $message = "✅ Séance modifiée avec succès.";
        $messageType = 'success';
    }

    // Supprimer une séance
    elseif ($action === 'delete_seance') {
        $id = intval($_POST['id_seance']);
        $pdo->prepare("DELETE FROM seance_emploidutemps WHERE id_seance=?")->execute([$id]);
        $message = "🗑️ Séance supprimée.";
        $messageType = 'danger';
    }
}

// ── FILTRES ──────────────────────────────────────────────────
$semaine = $_GET['semaine'] ?? date('Y-W'); // Ex: 2026-22
// Calculer lundi et vendredi de la semaine sélectionnée
$dt = new DateTime();
$dt->setISODate(...explode('-', $semaine));
$lundi    = $dt->format('Y-m-d');
$vendredi = (clone $dt)->modify('+4 days')->format('Y-m-d');

// Semaines disponibles (4 semaines autour d'aujourd'hui)
$semaines = [];
$base = new DateTime('monday this week');
$base->modify('-1 week');
for ($i = 0; $i < 6; $i++) {
    $semaines[] = [
        'val'   => $base->format('Y-W'),
        'label' => 'Sem. du ' . $base->format('d/m') . ' au ' . (clone $base)->modify('+4 days')->format('d/m/Y'),
    ];
    $base->modify('+1 week');
}

// ── DONNÉES ──────────────────────────────────────────────────
// Toutes les séances de la semaine sélectionnée
$stmtSeances = $pdo->prepare("
    SELECT s.*, c.nom_cours, e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM seance_emploidutemps s
    JOIN cours c ON s.id_cours = c.id_cours
    JOIN enseignant e ON c.id_enseignant = e.id
    WHERE s.date_seance BETWEEN ? AND ?
    ORDER BY s.date_seance ASC, s.heure_debut ASC
");
$stmtSeances->execute([$lundi, $vendredi]);
$seances = $stmtSeances->fetchAll(PDO::FETCH_ASSOC);

// Tous les cours (pour le select de la modale)
$cours = $pdo->query("
    SELECT c.id_cours, c.nom_cours, e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM cours c
    JOIN enseignant e ON c.id_enseignant = e.id
    ORDER BY c.nom_cours ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Organiser les séances par jour (0=lundi ... 4=vendredi)
$jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
$seancesParJour = array_fill(0, 5, []);
foreach ($seances as $s) {
    $dow = (int)(new DateTime($s['date_seance']))->format('N') - 1; // 0=Lundi
    if ($dow >= 0 && $dow <= 4) $seancesParJour[$dow][] = $s;
}

// Créneaux horaires affichés
$creneaux = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00'];

function heureEnMinutes($h) { [$hh,$mm] = explode(':', $h); return $hh*60+$mm; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion Emploi du Temps - SmartCampus</title>
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
    .nav-links a { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 500; color: var(--sky); text-decoration: none; padding: 6px 14px; border-radius: 4px; transition: background .2s; letter-spacing: 0.04em; text-transform: uppercase; }
    .nav-links a:hover, .nav-links a.active { background: var(--teal); color: #fff; }
    .nav-logout { font-family: 'DM Sans', sans-serif; font-size: 0.78rem; font-weight: 600; color: #ffb3b8; text-decoration: none; padding: 6px 14px; border-radius: 4px; border: 1px solid rgba(230,57,70,0.4); transition: all .2s; margin-left: 4px; }
    .nav-logout:hover { background: var(--alert); color: #fff; border-color: var(--alert); }

    .page-wrapper { position: relative; z-index: 1; width: 100%; max-width: 1400px; margin: 0 auto; padding: 6rem 1.5rem 5rem; display: flex; flex-direction: column; gap: 1.5rem; }
    .glass-panel { background: rgba(255,255,255,0.65); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.8); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.06); padding: 1.5rem; }

    /* TOOLBAR */
    .top-actions { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
    .page-title { font-family: 'Syne', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--navy); background: rgba(255,255,255,0.55); padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.8); }
    .filters-wrapper { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .filter-select { background: rgba(255,255,255,0.8); border: 1.5px solid #cce0e0; border-radius: 10px; padding: 10px 18px; font-family: 'DM Sans', sans-serif; font-size: 0.92rem; color: var(--navy); outline: none; cursor: pointer; min-width: 220px; }
    .btn-add { background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 10px; padding: 12px 20px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: transform .2s; }
    .btn-add:hover { transform: translateY(-2px); }
    .nav-week { display: flex; gap: 8px; align-items: center; }
    .btn-week { background: rgba(255,255,255,0.7); border: 1.5px solid #cce0e0; border-radius: 8px; padding: 8px 14px; font-family: 'Syne', sans-serif; font-size: 0.85rem; font-weight: 700; cursor: pointer; color: var(--navy); text-decoration: none; transition: all .2s; }
    .btn-week:hover { background: var(--teal); color: #fff; border-color: var(--teal); }

    /* FLASH */
    .flash { padding: 14px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; }
    .flash.success { background: rgba(42,157,143,0.15); color: var(--success); border: 1px solid rgba(42,157,143,0.3); }
    .flash.danger  { background: rgba(230,57,70,0.1);  color: var(--alert);   border: 1px solid rgba(230,57,70,0.3); }

    /* GRILLE EDT */
    .edt-scroller { overflow-x: auto; }
    .edt-grid {
      display: grid;
      grid-template-columns: 70px repeat(5, minmax(160px, 1fr));
      min-width: 900px;
    }
    .edt-header { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.9rem; text-align: center; padding: 12px 6px; border-bottom: 2px solid rgba(13,27,42,.15); color: var(--navy); }
    .edt-header.today { color: var(--teal); border-bottom-color: var(--teal); }
    .edt-time-col { display: flex; flex-direction: column; }
    .edt-time-slot { height: 70px; display: flex; align-items: flex-start; justify-content: center; padding-top: 6px; font-size: 0.78rem; font-weight: 700; color: #6b8080; border-right: 1px solid rgba(13,27,42,.1); }
    .edt-day-col { position: relative; border-right: 1px solid rgba(13,27,42,.06); }
    .edt-day-bg { height: 70px; border-bottom: 1px solid rgba(13,27,42,.05); }
    .edt-day-bg:last-child { border-bottom: none; }

    /* Cartes cours positionnées en absolu */
    .course-block {
      position: absolute; left: 3px; right: 3px;
      border-radius: 10px; padding: 8px 10px;
      cursor: pointer; transition: transform .2s, box-shadow .2s;
      border-left: 4px solid transparent;
      overflow: hidden;
    }
    .course-block:hover { transform: translateX(2px); box-shadow: 0 4px 14px rgba(0,0,0,.12); }
    .cb-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 0.82rem; line-height: 1.2; }
    .cb-info  { font-size: 0.74rem; margin-top: 3px; opacity: .8; }
    .cb-salle { font-size: 0.72rem; font-weight: 700; margin-top: 4px; background: rgba(255,255,255,0.45); display: inline-block; padding: 1px 6px; border-radius: 4px; }

    /* Couleurs par cours (rotation sur id) */
    .color-0 { background: rgba(10,147,150,.15);  border-left-color: #0a9396; color: #005f73; }
    .color-1 { background: rgba(0,180,216,.15);   border-left-color: #00b4d8; color: #0077b6; }
    .color-2 { background: rgba(244,162,97,.15);  border-left-color: #f4a261; color: #9a031e; }
    .color-3 { background: rgba(42,157,143,.15);  border-left-color: #2a9d8f; color: #1a5c53; }
    .color-4 { background: rgba(230,57,70,.12);   border-left-color: #e63946; color: #6b0012; }

    .empty-day { color: #9bb0b0; font-style: italic; font-size: 0.82rem; padding: 20px 8px; text-align: center; }

    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(13,27,42,.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 300; opacity: 0; pointer-events: none; transition: opacity .3s; }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal-box { background: var(--white); border-radius: 20px; width: 520px; max-width: 94vw; padding: 2rem; box-shadow: 0 28px 80px rgba(0,0,0,.2); position: relative; max-height: 92vh; overflow-y: auto; }
    .modal-title { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--navy); margin-bottom: 1.5rem; }
    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem; }
    .form-group label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #3d5a5a; letter-spacing: .04em; }
    .form-group input, .form-group select { padding: 10px 14px; border: 1.5px solid #cce0e0; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.93rem; color: var(--navy); background: #f0fafa; outline: none; transition: border-color .2s; width: 100%; }
    .form-group input:focus, .form-group select:focus { border-color: var(--teal); background: #fff; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .modal-btns { display: flex; gap: 1rem; margin-top: 1.5rem; }
    .btn-save { flex: 1; padding: 12px; background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; }
    .btn-delete { padding: 12px 18px; background: var(--alert); color: #fff; border: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; }
    .btn-close { position: absolute; top: 16px; right: 18px; background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #6b8080; }

    /* INFO SEMAINE */
    .week-info { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 1rem; color: var(--navy); text-align: center; padding: 10px; }

    footer { position: fixed; bottom: 0; left: 0; right: 0; height: 42px; background: var(--navy); display: flex; align-items: center; justify-content: center; font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--teal-light); z-index: 100; }
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

    <!-- TOOLBAR -->
    <div class="top-actions">
      <div class="page-title">📅 Emploi du Temps</div>
      <div class="filters-wrapper">
        <!-- Navigation semaine -->
        <?php
          $prevSem = (new DateTime($lundi))->modify('-1 week')->format('Y-W');
          $nextSem = (new DateTime($lundi))->modify('+1 week')->format('Y-W');
        ?>
        <div class="nav-week">
          <a href="?semaine=<?= $prevSem ?>" class="btn-week">← Sem. préc.</a>
          <form method="GET" style="margin:0">
            <select class="filter-select" name="semaine" onchange="this.form.submit()">
              <?php foreach ($semaines as $s): ?>
              <option value="<?= $s['val'] ?>" <?= $s['val'] === $semaine ? 'selected' : '' ?>>
                <?= $s['label'] ?>
              </option>
              <?php endforeach; ?>
            </select>
          </form>
          <a href="?semaine=<?= $nextSem ?>" class="btn-week">Sem. suiv. →</a>
        </div>
        <button class="btn-add" onclick="openAddModal()">📅+ Planifier un cours</button>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- RÉSUMÉ SEMAINE -->
    <div class="glass-panel week-info">
      Semaine du <strong><?= (new DateTime($lundi))->format('d/m/Y') ?></strong>
      au <strong><?= (new DateTime($vendredi))->format('d/m/Y') ?></strong>
      — <?= count($seances) ?> séance<?= count($seances) > 1 ? 's' : '' ?> planifiée<?= count($seances) > 1 ? 's' : '' ?>
    </div>

    <!-- GRILLE EDT -->
    <div class="glass-panel edt-scroller">
      <div class="edt-grid">

        <!-- En-têtes -->
        <div class="edt-header" style="border-right:1px solid rgba(13,27,42,.1)">Heure</div>
        <?php
          $today = date('Y-m-d');
          $datesCols = [];
          for ($i = 0; $i < 5; $i++) {
              $d = (new DateTime($lundi))->modify("+$i days");
              $datesCols[] = $d->format('Y-m-d');
              $isToday = $d->format('Y-m-d') === $today;
              echo '<div class="edt-header ' . ($isToday ? 'today' : '') . '">';
              echo $jours[$i] . '<br><small style="font-weight:400;font-size:0.78rem">' . $d->format('d/m') . '</small>';
              echo '</div>';
          }
        ?>

        <!-- Colonne heures -->
        <div class="edt-time-col" style="border-right:1px solid rgba(13,27,42,.1)">
          <?php foreach ($creneaux as $h): ?>
          <div class="edt-time-slot"><?= $h ?></div>
          <?php endforeach; ?>
        </div>

        <!-- Colonnes jours -->
        <?php foreach ($datesCols as $di => $dateCol): ?>
        <div class="edt-day-col" style="position:relative">
          <!-- Fond grille -->
          <?php foreach ($creneaux as $h): ?>
          <div class="edt-day-bg"></div>
          <?php endforeach; ?>

          <!-- Séances du jour -->
          <?php if (empty($seancesParJour[$di])): ?>
            <div style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;">
              <span class="empty-day">Libre</span>
            </div>
          <?php else: ?>
            <?php foreach ($seancesParJour[$di] as $s):
              $debut  = heureEnMinutes(substr($s['heure_debut'],0,5));
              $fin    = heureEnMinutes(substr($s['heure_fin'],  0,5));
              $debut8 = heureEnMinutes('08:00');
              $topPx  = ($debut - $debut8) / 60 * 70;
              $hautPx = ($fin - $debut)    / 60 * 70 - 4;
              $couleur= 'color-' . ($s['id_cours'] % 5);
            ?>
            <div class="course-block <?= $couleur ?>"
                 style="top:<?= $topPx ?>px; height:<?= $hautPx ?>px;"
                 onclick='openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>
              <div class="cb-title"><?= htmlspecialchars($s['nom_cours']) ?></div>
              <div class="cb-info"><?= htmlspecialchars($s['ens_prenom'].' '.$s['ens_nom']) ?></div>
              <div class="cb-salle">📍 <?= htmlspecialchars($s['salle']) ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

      </div>
    </div>

    <!-- LISTE DÉTAILLÉE DES SÉANCES -->
    <?php if (!empty($seances)): ?>
    <div class="glass-panel">
      <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;margin-bottom:1rem;color:var(--navy);">
        📋 Liste des séances de la semaine
      </div>
      <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse">
          <thead>
            <tr>
              <?php foreach(['Cours','Enseignant','Date','Heure début','Heure fin','Salle','Action'] as $th): ?>
              <th style="font-family:'Syne',sans-serif;font-size:0.78rem;font-weight:700;text-transform:uppercase;color:#3d5a5a;padding:10px 12px;border-bottom:2px solid rgba(13,27,42,.15);text-align:left"><?= $th ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($seances as $s): ?>
            <tr style="border-bottom:1px solid rgba(13,27,42,.07)">
              <td style="padding:12px;font-weight:700"><?= htmlspecialchars($s['nom_cours']) ?></td>
              <td style="padding:12px;font-size:.88rem"><?= htmlspecialchars($s['ens_prenom'].' '.$s['ens_nom']) ?></td>
              <td style="padding:12px"><?= (new DateTime($s['date_seance']))->format('d/m/Y') ?></td>
              <td style="padding:12px"><?= substr($s['heure_debut'],0,5) ?></td>
              <td style="padding:12px"><?= substr($s['heure_fin'],0,5) ?></td>
              <td style="padding:12px"><?= htmlspecialchars($s['salle']) ?></td>
              <td style="padding:12px">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'
                  style="background:rgba(10,147,150,.1);color:var(--teal);border:none;border-radius:6px;padding:5px 10px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif">
                  ✏️ Éditer
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <!-- ══ MODAL AJOUT / EDIT ══ -->
  <div class="modal-overlay" id="edtModal">
    <div class="modal-box">
      <button class="btn-close" onclick="closeModal()">✕</button>
      <div class="modal-title" id="modalTitle">Planifier un cours</div>

      <form method="POST" action="gestion_edt.php">
        <input type="hidden" name="action"    id="form-action" value="add_seance">
        <input type="hidden" name="id_seance" id="form-id-seance">

        <div class="form-group">
          <label>Cours</label>
          <select name="id_cours" id="form-id-cours" required>
            <?php if (empty($cours)): ?>
            <option value="">— Aucun cours créé —</option>
            <?php else: ?>
            <?php foreach ($cours as $c): ?>
            <option value="<?= $c['id_cours'] ?>">
              <?= htmlspecialchars($c['nom_cours']) ?> — <?= htmlspecialchars($c['ens_prenom'].' '.$c['ens_nom']) ?>
            </option>
            <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Date de la séance</label>
          <input type="date" name="date_seance" id="form-date" required
                 min="<?= date('Y-m-d') ?>"
                 value="<?= $lundi ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Heure de début</label>
            <input type="time" name="heure_debut" id="form-debut" required min="07:00" max="20:00" value="08:00">
          </div>
          <div class="form-group">
            <label>Heure de fin</label>
            <input type="time" name="heure_fin" id="form-fin" required min="07:00" max="20:00" value="10:00">
          </div>
        </div>

        <div class="form-group">
          <label>Salle</label>
          <input type="text" name="salle" id="form-salle" placeholder="Ex: Amphi A, Salle B204" required>
        </div>

        <div class="modal-btns">
          <button type="button" class="btn-delete" id="btnDelete" style="display:none"
                  onclick="submitDelete()">🗑️ Supprimer</button>
          <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>

  <script>
    function openAddModal() {
      document.getElementById('modalTitle').textContent = '📅 Planifier un nouveau cours';
      document.getElementById('form-action').value   = 'add_seance';
      document.getElementById('form-id-seance').value = '';
      document.getElementById('form-id-cours').selectedIndex = 0;
      document.getElementById('form-date').value  = '<?= $lundi ?>';
      document.getElementById('form-debut').value = '08:00';
      document.getElementById('form-fin').value   = '10:00';
      document.getElementById('form-salle').value = '';
      document.getElementById('btnDelete').style.display = 'none';
      document.getElementById('edtModal').classList.add('open');
    }

    function openEditModal(s) {
      document.getElementById('modalTitle').textContent = '✏️ Modifier la séance';
      document.getElementById('form-action').value    = 'edit_seance';
      document.getElementById('form-id-seance').value = s.id_seance;
      document.getElementById('form-id-cours').value  = s.id_cours;
      document.getElementById('form-date').value  = s.date_seance;
      document.getElementById('form-debut').value = s.heure_debut.substring(0,5);
      document.getElementById('form-fin').value   = s.heure_fin.substring(0,5);
      document.getElementById('form-salle').value = s.salle;
      document.getElementById('btnDelete').style.display = 'block';
      document.getElementById('edtModal').classList.add('open');
    }

    function submitDelete() {
      if (!confirm('⚠️ Supprimer définitivement cette séance ?')) return;
      document.getElementById('form-action').value = 'delete_seance';
      document.querySelector('#edtModal form').submit();
    }

    function closeModal() {
      document.getElementById('edtModal').classList.remove('open');
    }
    document.getElementById('edtModal').addEventListener('click', e => {
      if (e.target === document.getElementById('edtModal')) closeModal();
    });
  </script>
</body>
</html>
