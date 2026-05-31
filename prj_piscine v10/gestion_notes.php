<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: connexion_et_incription.html"); exit();
}
require_once 'db.php';
$id_enseignant = $_SESSION['user_id'];

$message = ''; $messageType = '';

// ── SAUVEGARDER UNE NOTE ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_note') {
        $id_etudiant = intval($_POST['id_etudiant']);
        $id_cours    = intval($_POST['id_cours']);
        $valeur      = floatval($_POST['valeur']);
        $type_eval   = trim($_POST['type_eval']);
        if ($valeur < 0) $valeur = 0;
        if ($valeur > 20) $valeur = 20;
        // Upsert : modifier si existe, créer sinon
        $check = $pdo->prepare("SELECT id_notes FROM notes WHERE id_etudiant=? AND id_cours=? AND type_eval=?");
        $check->execute([$id_etudiant, $id_cours, $type_eval]);
        $existing = $check->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE notes SET valeur=? WHERE id_notes=?")->execute([$valeur, $existing['id_notes']]);
        } else {
            $pdo->prepare("INSERT INTO notes (valeur, type_eval, id_etudiant, id_cours) VALUES (?,?,?,?)")->execute([$valeur, $type_eval, $id_etudiant, $id_cours]);
        }
        $message = "✅ Note enregistrée."; $messageType = 'success';
    }
    elseif ($action === 'save_all') {
        $notes     = $_POST['notes']     ?? [];
        $id_cours  = intval($_POST['id_cours']);
        $type_eval = trim($_POST['type_eval']);
        $saved = 0;
        foreach ($notes as $id_etudiant => $valeur) {
            if ($valeur === '') continue;
            $v = max(0, min(20, floatval($valeur)));
            $id_etudiant = intval($id_etudiant);
            $check = $pdo->prepare("SELECT id_notes FROM notes WHERE id_etudiant=? AND id_cours=? AND type_eval=?");
            $check->execute([$id_etudiant, $id_cours, $type_eval]);
            $existing = $check->fetch();
            if ($existing) {
                $pdo->prepare("UPDATE notes SET valeur=? WHERE id_notes=?")->execute([$v, $existing['id_notes']]);
            } else {
                $pdo->prepare("INSERT INTO notes (valeur, type_eval, id_etudiant, id_cours) VALUES (?,?,?,?)")->execute([$v, $type_eval, $id_etudiant, $id_cours]);
            }
            $saved++;
        }
        $message = "✅ $saved note(s) enregistrée(s) avec succès."; $messageType = 'success';
    }
}

// ── DONNÉES ──────────────────────────────────────────────────
// Cours de cet enseignant
$cours = $pdo->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_enseignant=? ORDER BY nom_cours ASC");
$cours->execute([$id_enseignant]);
$cours = $cours->fetchAll(PDO::FETCH_ASSOC);

// Cours sélectionné
$id_cours_sel  = isset($_GET['id_cours']) ? intval($_GET['id_cours']) : ($cours[0]['id_cours'] ?? 0);
$type_eval_sel = $_GET['type_eval'] ?? 'CC1';
$types_eval    = ['CC1', 'CC2', 'EXAM'];

// Nom du cours sélectionné
$cours_nom = '';
foreach ($cours as $c) { if ($c['id_cours'] == $id_cours_sel) { $cours_nom = $c['nom_cours']; break; } }

// Étudiants inscrits à ce cours (via id_enseignant)
$stmtEtu = $pdo->prepare("
    SELECT e.id, e.nom, e.prenom, e.email
    FROM inscription i
    JOIN etudiant e ON i.id_etudiant = e.id
    WHERE i.id_enseignant = ?
    ORDER BY e.nom ASC
");
$stmtEtu->execute([$id_enseignant]);
$etudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

// Notes existantes pour ce cours + type_eval
$notesExistantes = [];
if ($id_cours_sel) {
    $stmtN = $pdo->prepare("SELECT id_etudiant, valeur FROM notes WHERE id_cours=? AND type_eval=?");
    $stmtN->execute([$id_cours_sel, $type_eval_sel]);
    foreach ($stmtN->fetchAll(PDO::FETCH_ASSOC) as $n) {
        $notesExistantes[$n['id_etudiant']] = $n['valeur'];
    }
}

// Stats
$valeursStats = array_values($notesExistantes);
$moy  = count($valeursStats) ? round(array_sum($valeursStats)/count($valeursStats), 1) : null;
$max  = count($valeursStats) ? max($valeursStats) : null;
$min  = count($valeursStats) ? min($valeursStats) : null;
$taux = count($valeursStats) ? round(count(array_filter($valeursStats, fn($v) => $v >= 10)) / count($valeursStats) * 100) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion des Notes - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{--sky:#b8f0f0;--navy:#0d1b2a;--teal:#0a9396;--teal-light:#94d2bd;--white:#f8fffe;--alert:#e63946;--success:#2a9d8f;--warning:#f4a261}
    html,body{min-height:100vh;font-family:'DM Sans',sans-serif;background:var(--sky);color:var(--navy);overflow-x:hidden}
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
    .glass-panel{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.06);padding:1.5rem 2rem}
    .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
    .page-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy);background:rgba(255,255,255,.55);padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,.8)}
    .filters-wrapper{display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
    .filter-select{background:rgba(255,255,255,.8);border:1.5px solid #cce0e0;border-radius:10px;padding:10px 16px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--navy);outline:none;cursor:pointer;min-width:180px}
    .flash{padding:12px 18px;border-radius:10px;font-weight:600;font-size:.92rem}
    .flash.success{background:rgba(42,157,143,.15);color:var(--success);border:1px solid rgba(42,157,143,.3)}
    table{width:100%;border-collapse:collapse}
    th{font-family:'Syne',sans-serif;font-weight:700;padding:12px 10px;border-bottom:2px solid rgba(13,27,42,.2);font-size:.82rem;text-transform:uppercase;color:#3d5a5a}
    td{padding:12px 10px;border-bottom:1px solid rgba(13,27,42,.07);font-size:.95rem}
    tr:hover td{background:rgba(255,255,255,.5)}
    .note-input{width:75px;padding:7px 10px;border:1.5px solid #cce0e0;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.95rem;text-align:center;outline:none;background:rgba(255,255,255,.9);transition:border-color .2s}
    .note-input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(10,147,150,.12)}
    .note-input.modified{border-color:var(--warning);background:#fffbf5}
    .badge-mention{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700}
    .mention-tb{background:rgba(42,157,143,.15);color:var(--success)}
    .mention-ab{background:rgba(10,147,150,.15);color:var(--teal)}
    .mention-pass{background:rgba(244,162,97,.15);color:#c06a10}
    .mention-fail{background:rgba(230,57,70,.15);color:var(--alert)}
    .btn-save-all{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:10px;padding:12px 28px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer;transition:transform .2s,box-shadow .2s}
    .btn-save-all:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(10,147,150,.3)}
    .panel-title{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:1.2rem;padding-bottom:10px;border-bottom:2px solid rgba(13,27,42,.1);display:flex;justify-content:space-between;align-items:center}
    .stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
    .stat-card{text-align:center;padding:1.2rem}
    .stat-val{font-size:2rem;font-weight:800;color:var(--teal);font-family:'Syne',sans-serif}
    .stat-lbl{font-size:.78rem;font-weight:600;color:#4a6a6a;text-transform:uppercase;margin-top:4px}
    .empty-state{text-align:center;padding:2rem;color:#6b8080;font-style:italic}
    footer{position:fixed;bottom:0;left:0;right:0;height:42px;background:var(--navy);display:flex;align-items:center;justify-content:center;font-family:'DM Sans',sans-serif;font-size:.78rem;color:var(--teal-light);z-index:100}
    @media(max-width:700px){.stats-row{grid-template-columns:1fr 1fr}}
  </style>
</head>
<body>
  <div class="bg-blob blob1"></div><div class="bg-blob blob2"></div>
  <nav>
    <img src="logo.jpg" alt="Logo" class="nav-logo-img">
    <span class="nav-brand">SmartCampus</span>
    <div class="nav-links">
      <a href="dashboard_enseignant.php">Tableau de bord</a>
      <a href="gestion_notes.php" class="active">Notes</a>
      <a href="gestion_presences.php">Présences</a>
      <a href="#">Profil : Enseignant</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">

    <div class="page-header">
      <div class="page-title">📝 Gestion des Notes</div>
      <!-- Filtres via GET pour garder le cours sélectionné -->
      <form method="GET" action="gestion_notes.php" class="filters-wrapper">
        <select class="filter-select" name="id_cours" onchange="this.form.submit()">
          <?php if (empty($cours)): ?>
          <option>— Aucun cours —</option>
          <?php else: ?>
          <?php foreach ($cours as $c): ?>
          <option value="<?= $c['id_cours'] ?>" <?= $c['id_cours']==$id_cours_sel?'selected':'' ?>>
            <?= htmlspecialchars($c['nom_cours']) ?>
          </option>
          <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <select class="filter-select" name="type_eval" onchange="this.form.submit()">
          <?php foreach ($types_eval as $t): ?>
          <option value="<?= $t ?>" <?= $t===$type_eval_sel?'selected':'' ?>><?= $t === 'CC1' ? 'Contrôle Continu 1' : ($t==='CC2'?'Contrôle Continu 2':'Examen Final') ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if ($message): ?><div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="glass-panel">
      <div class="stats-row">
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $moy ?? '—' ?></div>
          <div class="stat-lbl">Moyenne classe</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $max ?? '—' ?></div>
          <div class="stat-lbl">Meilleure note</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $min ?? '—' ?></div>
          <div class="stat-lbl">Note la plus basse</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $taux !== null ? $taux.'%' : '—' ?></div>
          <div class="stat-lbl">Taux de réussite</div>
        </div>
      </div>
    </div>

    <!-- TABLEAU NOTES -->
    <div class="glass-panel">
      <div class="panel-title">
        Saisie des notes — <strong><?= htmlspecialchars($cours_nom ?: '—') ?></strong> / <?= htmlspecialchars($type_eval_sel) ?>
        <span style="font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:400;color:#6b8080"><?= count($etudiants) ?> étudiant(s) inscrit(s)</span>
      </div>

      <?php if (empty($etudiants)): ?>
        <div class="empty-state">Aucun étudiant inscrit à ce cours.<br>Allez dans <a href="gestion_cours.php" style="color:var(--teal)">Gestion des Cours</a> pour inscrire des élèves.</div>
      <?php else: ?>
      <form method="POST" action="gestion_notes.php?id_cours=<?= $id_cours_sel ?>&type_eval=<?= urlencode($type_eval_sel) ?>">
        <input type="hidden" name="action"    value="save_all">
        <input type="hidden" name="id_cours"  value="<?= $id_cours_sel ?>">
        <input type="hidden" name="type_eval" value="<?= htmlspecialchars($type_eval_sel) ?>">
        <div style="overflow-x:auto">
          <table>
            <thead>
              <tr>
                <th>#</th><th>Nom Prénom</th><th>Email</th>
                <th>Note /20</th><th>Mention</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($etudiants as $i => $e):
                $note = $notesExistantes[$e['id']] ?? '';
                $mention = '';
                if ($note !== '') {
                  if ($note >= 16) $mention = '<span class="badge-mention mention-tb">TB</span>';
                  elseif ($note >= 12) $mention = '<span class="badge-mention mention-ab">Bien</span>';
                  elseif ($note >= 10) $mention = '<span class="badge-mention mention-pass">Passable</span>';
                  else $mention = '<span class="badge-mention mention-fail">Insuffisant</span>';
                }
              ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></strong></td>
                <td style="font-size:.85rem;color:#4a6a6a"><?= htmlspecialchars($e['email']) ?></td>
                <td>
                  <input class="note-input" type="number" name="notes[<?= $e['id'] ?>]"
                         min="0" max="20" step="0.5"
                         value="<?= $note !== '' ? $note : '' ?>"
                         placeholder="—"
                         oninput="updateMention(this, 'mention-<?= $e['id'] ?>')">
                </td>
                <td id="mention-<?= $e['id'] ?>"><?= $mention ?: '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1.5rem">
          <button type="submit" class="btn-save-all">💾 Enregistrer toutes les notes</button>
        </div>
      </form>
      <?php endif; ?>
    </div>

  </main>
  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    function getMention(n) {
      if (n >= 16) return '<span class="badge-mention mention-tb">TB</span>';
      if (n >= 12) return '<span class="badge-mention mention-ab">Bien</span>';
      if (n >= 10) return '<span class="badge-mention mention-pass">Passable</span>';
      return '<span class="badge-mention mention-fail">Insuffisant</span>';
    }
    function updateMention(input, mentionId) {
      input.classList.add('modified');
      const v = parseFloat(input.value);
      const el = document.getElementById(mentionId);
      el.innerHTML = isNaN(v) ? '—' : getMention(v);
    }
  </script>
</body>
</html>
