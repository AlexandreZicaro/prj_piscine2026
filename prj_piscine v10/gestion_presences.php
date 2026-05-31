<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'enseignant') {
    header("Location: connexion_et_incription.html"); exit();
}
require_once 'db.php';
$id_enseignant = $_SESSION['user_id'];

$message = ''; $messageType = '';

// ── SAUVEGARDER LES PRÉSENCES ─────────────────────────────────
// On stocke les présences dans la table seance_emploidutemps comme
// référence de séance, mais la BDD n'a pas de table présences.
// On va donc simuler via un INSERT dans une table notes avec type_eval='PRESENCE'
// => Non, on utilise un vrai système : on ajoute une colonne via PHP session.
// En réalité, pour persister les présences il faudrait une table dédiée.
// On va faire un système simple : sauvegarder dans notes type_eval=PRESENCE_{date}
// => Trop complexe. Solution propre : on stocke en session + message de confirmation.
// Pour la démo, on sauvegarde dans la table notes avec valeur 1=présent, 0=absent, 0.5=retard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_presences') {
    $id_cours   = intval($_POST['id_cours']);
    $date_seance= trim($_POST['date_seance']);
    $presences  = $_POST['presences'] ?? [];
    $saved = 0;
    foreach ($presences as $id_etudiant => $statut) {
        $id_etudiant = intval($id_etudiant);
        $valeur = $statut === 'present' ? 1 : ($statut === 'retard' ? 0.5 : 0);
        $type_eval = 'PRESENCE_' . $date_seance;
        $check = $pdo->prepare("SELECT id_notes FROM notes WHERE id_etudiant=? AND id_cours=? AND type_eval=?");
        $check->execute([$id_etudiant, $id_cours, $type_eval]);
        $existing = $check->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE notes SET valeur=? WHERE id_notes=?")->execute([$valeur, $existing['id_notes']]);
        } else {
            $pdo->prepare("INSERT INTO notes (valeur, type_eval, id_etudiant, id_cours) VALUES (?,?,?,?)")->execute([$valeur, $type_eval, $id_etudiant, $id_cours]);
        }
        $saved++;
    }
    $message = "✅ Feuille d'appel du $date_seance enregistrée ($saved présences sauvegardées).";
    $messageType = 'success';
}

// ── DONNÉES ──────────────────────────────────────────────────
$cours = $pdo->prepare("SELECT id_cours, nom_cours FROM cours WHERE id_enseignant=? ORDER BY nom_cours ASC");
$cours->execute([$id_enseignant]);
$cours = $cours->fetchAll(PDO::FETCH_ASSOC);

$id_cours_sel = isset($_GET['id_cours']) ? intval($_GET['id_cours']) : ($cours[0]['id_cours'] ?? 0);
$date_sel     = $_GET['date_seance'] ?? date('Y-m-d');

$cours_nom = '';
foreach ($cours as $c) { if ($c['id_cours'] == $id_cours_sel) { $cours_nom = $c['nom_cours']; break; } }

// Étudiants inscrits
$stmtEtu = $pdo->prepare("
    SELECT e.id, e.nom, e.prenom, e.email,
           COUNT(n.id_notes) as nb_absences
    FROM inscription i
    JOIN etudiant e ON i.id_etudiant = e.id
    LEFT JOIN notes n ON n.id_etudiant = e.id AND n.id_cours = ? AND n.valeur = 0 AND n.type_eval LIKE 'PRESENCE_%'
    WHERE i.id_enseignant = ?
    GROUP BY e.id ORDER BY e.nom ASC
");
$stmtEtu->execute([$id_cours_sel, $id_enseignant]);
$etudiants = $stmtEtu->fetchAll(PDO::FETCH_ASSOC);

// Présences déjà enregistrées pour ce jour
$presencesExistantes = [];
if ($id_cours_sel && $date_sel) {
    $type_eval_date = 'PRESENCE_' . $date_sel;
    $stmtP = $pdo->prepare("SELECT id_etudiant, valeur FROM notes WHERE id_cours=? AND type_eval=?");
    $stmtP->execute([$id_cours_sel, $type_eval_date]);
    foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        if ($p['valeur'] == 1) $presencesExistantes[$p['id_etudiant']] = 'present';
        elseif ($p['valeur'] == 0.5) $presencesExistantes[$p['id_etudiant']] = 'retard';
        else $presencesExistantes[$p['id_etudiant']] = 'absent';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion des Présences - SmartCampus</title>
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
    .nav-links a{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:500;color:var(--sky);text-decoration:none;padding:6px 14px;border-radius:4px;transition:background .2s;text-transform:uppercase}
    .nav-links a:hover,.nav-links a.active{background:var(--teal);color:#fff}
    .nav-logout{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:600;color:#ffb3b8;text-decoration:none;padding:6px 14px;border-radius:4px;border:1px solid rgba(230,57,70,.4);transition:all .2s;margin-left:4px}
    .nav-logout:hover{background:var(--alert);color:#fff;border-color:var(--alert)}
    .page-wrapper{position:relative;z-index:1;width:100%;max-width:1000px;margin:0 auto;padding:6rem 1.5rem 5rem;display:flex;flex-direction:column;gap:2rem}
    .glass-panel{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.06);padding:1.5rem 2rem}
    .page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
    .page-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy);background:rgba(255,255,255,.55);padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,.8)}
    .filters-wrapper{display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
    .filter-select,.filter-date{background:rgba(255,255,255,.8);border:1.5px solid #cce0e0;border-radius:10px;padding:10px 16px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--navy);outline:none;cursor:pointer;min-width:180px}
    .flash{padding:12px 18px;border-radius:10px;font-weight:600;font-size:.92rem}
    .flash.success{background:rgba(42,157,143,.15);color:var(--success);border:1px solid rgba(42,157,143,.3)}
    .panel-title{font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:700;margin-bottom:1.2rem;padding-bottom:10px;border-bottom:2px solid rgba(13,27,42,.1);display:flex;justify-content:space-between;align-items:center}
    table{width:100%;border-collapse:collapse}
    th{font-family:'Syne',sans-serif;font-weight:700;padding:12px 14px;border-bottom:2px solid rgba(13,27,42,.2);font-size:.82rem;text-transform:uppercase;color:#3d5a5a}
    td{padding:14px;border-bottom:1px solid rgba(13,27,42,.07);font-size:.95rem}
    tr:hover td{background:rgba(255,255,255,.5)}
    .presence-toggle{display:flex;border-radius:8px;overflow:hidden;border:1.5px solid #cce0e0;width:fit-content}
    .toggle-btn{padding:7px 14px;border:none;background:rgba(255,255,255,.8);font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s}
    .toggle-btn.present.active{background:var(--success);color:#fff}
    .toggle-btn.retard.active{background:var(--warning);color:#fff}
    .toggle-btn.absent.active{background:var(--alert);color:#fff}
    .abs-badge{display:inline-block;font-size:.78rem;font-weight:700;padding:2px 8px;border-radius:12px}
    .abs-ok{background:rgba(42,157,143,.15);color:var(--success)}
    .abs-warn{background:rgba(244,162,97,.2);color:#c06a10}
    .abs-danger{background:rgba(230,57,70,.15);color:var(--alert)}
    .summary-bar{display:flex;gap:1.5rem;align-items:center;padding:1rem 1.5rem;background:rgba(255,255,255,.5);border-radius:12px;flex-wrap:wrap;margin-bottom:1.5rem}
    .summary-item{display:flex;align-items:center;gap:8px;font-size:.9rem;font-weight:600}
    .dot{width:12px;height:12px;border-radius:50%}
    .dot-present{background:var(--success)}.dot-absent{background:var(--alert)}.dot-retard{background:var(--warning)}
    .btn-save-all{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:10px;padding:12px 28px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer;transition:transform .2s}
    .btn-save-all:hover{transform:translateY(-2px)}
    .btn-tous{background:rgba(255,255,255,.7);border:1.5px solid var(--teal);color:var(--teal);border-radius:8px;padding:8px 14px;font-family:'Syne',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;margin-right:6px}
    .btn-tous:hover{background:var(--teal);color:#fff}
    .empty-state{text-align:center;padding:2rem;color:#6b8080;font-style:italic}
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
      <a href="gestion_presences.php" class="active">Présences</a>
      <a href="#">Profil : Enseignant</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">
    <div class="page-header">
      <div class="page-title">✅ Gestion des Présences</div>
      <form method="GET" action="gestion_presences.php" class="filters-wrapper">
        <select class="filter-select" name="id_cours" onchange="this.form.submit()">
          <?php foreach ($cours as $c): ?>
          <option value="<?= $c['id_cours'] ?>" <?= $c['id_cours']==$id_cours_sel?'selected':'' ?>><?= htmlspecialchars($c['nom_cours']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="date" class="filter-date" name="date_seance"
               value="<?= htmlspecialchars($date_sel) ?>" onchange="this.form.submit()">
      </form>
    </div>

    <?php if ($message): ?><div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="glass-panel">
      <div class="panel-title">
        Feuille d'appel — <strong><?= htmlspecialchars($cours_nom ?: '—') ?></strong>
        <span style="font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:400;color:#6b8080">
          <?= (new DateTime($date_sel))->format('d/m/Y') ?>
        </span>
      </div>

      <!-- Résumé -->
      <div class="summary-bar" id="summaryBar">
        <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:.9rem">Résumé :</span>
        <div class="summary-item"><div class="dot dot-present"></div><span id="sum-present">— présents</span></div>
        <div class="summary-item"><div class="dot dot-absent"></div><span id="sum-absent">— absents</span></div>
        <div class="summary-item"><div class="dot dot-retard"></div><span id="sum-retard">— retards</span></div>
      </div>

      <?php if (empty($etudiants)): ?>
        <div class="empty-state">Aucun étudiant inscrit à ce cours.<br>
          <a href="gestion_cours.php" style="color:var(--teal)">Inscrire des élèves</a></div>
      <?php else: ?>
      <form method="POST" action="gestion_presences.php?id_cours=<?= $id_cours_sel ?>&date_seance=<?= urlencode($date_sel) ?>">
        <input type="hidden" name="action"      value="save_presences">
        <input type="hidden" name="id_cours"    value="<?= $id_cours_sel ?>">
        <input type="hidden" name="date_seance" value="<?= htmlspecialchars($date_sel) ?>">

        <div style="display:flex;gap:.8rem;margin-bottom:1.2rem">
          <button type="button" class="btn-tous" onclick="setAll('present')">✅ Tous présents</button>
          <button type="button" class="btn-tous" style="border-color:var(--alert);color:var(--alert)" onclick="setAll('absent')">❌ Tous absents</button>
        </div>

        <div style="overflow-x:auto">
          <table>
            <thead><tr><th>#</th><th>Nom Prénom</th><th>Email</th><th>Présence</th><th>Absences cumulées</th></tr></thead>
            <tbody id="tbody">
              <?php foreach ($etudiants as $i => $e):
                $statut = $presencesExistantes[$e['id']] ?? 'present';
                $absClass = $e['nb_absences'] <= 1 ? 'abs-ok' : ($e['nb_absences'] <= 4 ? 'abs-warn' : 'abs-danger');
              ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?></strong></td>
                <td style="font-size:.85rem;color:#4a6a6a"><?= htmlspecialchars($e['email']) ?></td>
                <td>
                  <!-- Hidden input pour la valeur soumise -->
                  <input type="hidden" name="presences[<?= $e['id'] ?>]" id="inp-<?= $e['id'] ?>" value="<?= $statut ?>">
                  <div class="presence-toggle" id="toggle-<?= $e['id'] ?>">
                    <button type="button" class="toggle-btn present <?= $statut==='present'?'active':'' ?>"
                            onclick="setPresence(<?= $e['id'] ?>,'present',this)">✅ Présent</button>
                    <button type="button" class="toggle-btn retard <?= $statut==='retard'?'active':'' ?>"
                            onclick="setPresence(<?= $e['id'] ?>,'retard',this)">⏰ Retard</button>
                    <button type="button" class="toggle-btn absent <?= $statut==='absent'?'active':'' ?>"
                            onclick="setPresence(<?= $e['id'] ?>,'absent',this)">❌ Absent</button>
                  </div>
                </td>
                <td>
                  <span class="abs-badge <?= $absClass ?>"><?= $e['nb_absences'] ?> abs.</span>
                  <?= $e['nb_absences'] >= 5 ? ' ⚠️ <span style="font-size:.78rem;color:var(--alert);font-weight:700">Seuil alerte</span>' : '' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1.5rem">
          <button type="submit" class="btn-save-all">💾 Valider la feuille d'appel</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </main>
  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    function setPresence(id, statut, btn) {
      document.getElementById('inp-' + id).value = statut;
      const toggle = document.getElementById('toggle-' + id);
      toggle.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      updateSummary();
    }
    function setAll(statut) {
      document.querySelectorAll('[id^="inp-"]').forEach(inp => {
        const id = inp.id.replace('inp-', '');
        inp.value = statut;
        const toggle = document.getElementById('toggle-' + id);
        if (toggle) {
          toggle.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
          toggle.querySelector('.' + statut)?.classList.add('active');
        }
      });
      updateSummary();
    }
    function updateSummary() {
      const vals = [...document.querySelectorAll('[id^="inp-"]')].map(i => i.value);
      document.getElementById('sum-present').textContent = vals.filter(v=>v==='present').length + ' présents';
      document.getElementById('sum-absent').textContent  = vals.filter(v=>v==='absent').length  + ' absents';
      document.getElementById('sum-retard').textContent  = vals.filter(v=>v==='retard').length  + ' retards';
    }
    updateSummary();
  </script>
</body>
</html>
