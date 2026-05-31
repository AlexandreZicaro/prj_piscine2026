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

    // ── MODIFIER UN ÉTUDIANT ──
    if ($action === 'edit_etudiant') {
        $id         = intval($_POST['id_etudiant']);
        $nom        = trim($_POST['nom']);
        $prenom     = trim($_POST['prenom']);
        $email      = trim($_POST['email']);
        $classe     = trim($_POST['classe']);
        $promo      = trim($_POST['promo']);
        $diplome    = trim($_POST['diplome']);
        $tel        = trim($_POST['num_tel']);
        $naissance  = trim($_POST['date_naissance']);

        // Vérif email unique (sauf pour cet étudiant)
        $check = $pdo->prepare("SELECT id FROM etudiant WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            $message     = "⚠️ Cet email est déjà utilisé par un autre compte.";
            $messageType = 'warning';
        } else {
            $stmt = $pdo->prepare("
                UPDATE etudiant
                SET nom=?, prenom=?, email=?, classe=?, promo=?, diplome=?, num_tel=?, date_naissance=?
                WHERE id=?
            ");
            $stmt->execute([$nom, $prenom, $email, $classe, $promo, $diplome, $tel, $naissance, $id]);
            $message     = "✅ L'étudiant(e) $prenom $nom a été modifié(e) avec succès.";
            $messageType = 'success';
        }
    }

    // ── SUPPRIMER UN ÉTUDIANT ──
    elseif ($action === 'delete_etudiant') {
        $id   = intval($_POST['id_etudiant']);
        $stmt = $pdo->prepare("SELECT nom, prenom FROM etudiant WHERE id=?");
        $stmt->execute([$id]);
        $etu  = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($etu) {
            $pdo->prepare("DELETE FROM etudiant WHERE id=?")->execute([$id]);
            $message     = "🗑️ L'étudiant(e) {$etu['prenom']} {$etu['nom']} a été supprimé(e) définitivement.";
            $messageType = 'danger';
        }
    }
}

// ── RÉCUPÉRATION DES DONNÉES ──────────────────────────────────
// Recherche/filtre
$search = trim($_GET['search'] ?? '');
$promo  = trim($_GET['promo']  ?? '');

$sql    = "SELECT e.*,
                  COALESCE(ROUND(AVG(n.valeur),1), NULL) AS moyenne
           FROM etudiant e
           LEFT JOIN notes n ON n.id_etudiant = e.id
           WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql    .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ?)";
    $like    = "%$search%";
    $params  = array_merge($params, [$like, $like, $like]);
}
if ($promo !== '') {
    $sql    .= " AND e.promo = ?";
    $params[] = $promo;
}

$sql .= " GROUP BY e.id ORDER BY e.nom ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Promos disponibles
$promos = $pdo->query("SELECT DISTINCT promo FROM etudiant ORDER BY promo DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion Étudiants - SmartCampus</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --sky: #b8f0f0; --navy: #0d1b2a; --teal: #0a9396; --teal-light: #94d2bd; --white: #f8fffe; --alert: #e63946; --alert-hover: #c1121f; --warning: #f4a261; --success: #2a9d8f; }
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

    .page-wrapper { position: relative; z-index: 1; width: 100%; max-width: 1200px; margin: 0 auto; padding: 6rem 1.5rem 5rem; display: flex; flex-direction: column; gap: 2rem; }

    .glass-panel { background: rgba(255,255,255,0.65); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.8); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.06); padding: 1.5rem 2rem; }

    /* HEADER */
    .header-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; }
    .page-title { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--navy); background: rgba(255,255,255,0.55); padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.8); }
    .btn-add { background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 10px; padding: 12px 24px; font-family: 'Syne', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: transform .2s, box-shadow .2s; }
    .btn-add:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(10,147,150,.3); }

    /* FLASH */
    .flash { padding: 14px 20px; border-radius: 12px; font-weight: 600; font-size: 0.95rem; }
    .flash.success { background: rgba(42,157,143,0.15); color: var(--success); border: 1px solid rgba(42,157,143,0.3); }
    .flash.danger  { background: rgba(230,57,70,0.1);  color: var(--alert);   border: 1px solid rgba(230,57,70,0.3); }
    .flash.warning { background: rgba(244,162,97,0.15); color: #c06a10;       border: 1px solid rgba(244,162,97,0.4); }

    /* FILTRES */
    .filters-wrapper { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; width: 100%; }
    .search-bar { display: flex; align-items: center; background: rgba(255,255,255,0.8); border: 1.5px solid #cce0e0; border-radius: 10px; padding: 0 14px; flex: 1; min-width: 250px; transition: border-color .2s; }
    .search-bar:focus-within { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(10,147,150,.12); }
    .search-bar input { border: none; background: transparent; outline: none; padding: 12px 10px; width: 100%; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; color: var(--navy); }
    .filter-select { background: rgba(255,255,255,0.8); border: 1.5px solid #cce0e0; border-radius: 10px; padding: 12px 20px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; color: var(--navy); outline: none; cursor: pointer; min-width: 160px; }
    .btn-filter { background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 10px; padding: 12px 20px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; transition: transform .2s; }
    .btn-filter:hover { transform: translateY(-2px); }

    /* TABLEAU */
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; text-align: left; }
    th { font-family: 'Syne', sans-serif; font-weight: 700; color: var(--navy); padding: 15px 10px; border-bottom: 2px solid rgba(13,27,42,.2); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; }
    td { padding: 14px 10px; border-bottom: 1px solid rgba(13,27,42,.08); font-size: 0.95rem; }
    tr:hover td { background: rgba(255,255,255,0.4); cursor: pointer; }
    .action-links { display: flex; gap: 6px; }
    .btn-action { background: none; border: none; font-size: 0.82rem; font-weight: 700; cursor: pointer; padding: 5px 10px; border-radius: 6px; font-family: 'DM Sans', sans-serif; transition: all .2s; }
    .btn-action.edit { color: var(--teal); background: rgba(10,147,150,.1); }
    .btn-action.edit:hover { background: var(--teal); color: #fff; }
    .btn-action.del { color: var(--alert); background: rgba(230,57,70,.1); }
    .btn-action.del:hover { background: var(--alert); color: #fff; }

    .badge-moyenne { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
    .moy-good  { background: rgba(42,157,143,.15); color: var(--success); }
    .moy-avg   { background: rgba(10,147,150,.15);  color: var(--teal); }
    .moy-low   { background: rgba(244,162,97,.15);  color: #c06a10; }
    .moy-fail  { background: rgba(230,57,70,.15);   color: var(--alert); }
    .moy-none  { background: rgba(13,27,42,.08);    color: #6b8080; }

    /* STATS */
    .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; }
    .stat-card { text-align: center; padding: 1rem; }
    .stat-val { font-family: 'Syne', sans-serif; font-size: 2rem; font-weight: 800; color: var(--teal); }
    .stat-lbl { font-size: 0.78rem; font-weight: 600; color: #4a6a6a; text-transform: uppercase; margin-top: 4px; }

    /* MODAL */
    .modal-overlay { position: fixed; inset: 0; background: rgba(13,27,42,.5); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 300; opacity: 0; pointer-events: none; transition: opacity .3s; }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal-box { background: var(--white); border-radius: 20px; width: 580px; max-width: 94vw; padding: 2rem; box-shadow: 0 28px 80px rgba(0,0,0,.2); position: relative; max-height: 90vh; overflow-y: auto; }
    .modal-title { font-family: 'Syne', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--navy); margin-bottom: 1.5rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .full-width { grid-column: span 2; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; color: #3d5a5a; letter-spacing: 0.04em; }
    .form-group input, .form-group select { padding: 10px 14px; border: 1.5px solid #cce0e0; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 0.93rem; color: var(--navy); background: #f0fafa; outline: none; transition: border-color .2s; }
    .form-group input:focus, .form-group select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(10,147,150,.12); background: #fff; }
    .modal-btns { display: flex; gap: 1rem; margin-top: 1.5rem; }
    .btn-save { flex: 1; padding: 12px; background: linear-gradient(135deg, var(--teal), #00b4d8); color: #fff; border: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; }
    .btn-cancel { padding: 12px 24px; background: #e0f0ef; color: var(--navy); border: none; border-radius: 12px; font-family: 'Syne', sans-serif; font-weight: 700; cursor: pointer; }
    .btn-close { position: absolute; top: 16px; right: 18px; background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #6b8080; }

    .empty-state { text-align: center; padding: 2.5rem; color: #6b8080; font-style: italic; }

    footer { position: fixed; bottom: 0; left: 0; right: 0; height: 42px; background: var(--navy); display: flex; align-items: center; justify-content: center; font-family: 'DM Sans', sans-serif; font-size: 0.78rem; color: var(--teal-light); z-index: 100; }

    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } .full-width { grid-column: span 1; } .stats-row { grid-template-columns: 1fr; } }
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
      <a href="gestion_etudiant.php" class="active">Profil : Administrateur</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>

  <main class="page-wrapper">

    <!-- HEADER -->
    <div class="header-row">
      <div class="page-title">🎓 Gestion étudiants</div>
      <a href="ajout_etudiant.php" class="btn-add">👤+ Ajouter un étudiant</a>
    </div>

    <!-- FLASH MESSAGE -->
    <?php if ($message): ?>
    <div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- STATISTIQUES -->
    <div class="glass-panel">
      <div class="stats-row">
        <?php
          $total  = count($etudiants);
          $avecNote = count(array_filter($etudiants, fn($e) => $e['moyenne'] !== null));
          $moyGlobale = $avecNote > 0
            ? round(array_sum(array_column(array_filter($etudiants, fn($e) => $e['moyenne'] !== null), 'moyenne')) / $avecNote, 1)
            : '—';
        ?>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $total ?></div>
          <div class="stat-lbl">Étudiants au total</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= $moyGlobale ?></div>
          <div class="stat-lbl">Moyenne générale /20</div>
        </div>
        <div class="stat-card glass-panel">
          <div class="stat-val"><?= count($promos) ?></div>
          <div class="stat-lbl">Promotions actives</div>
        </div>
      </div>
    </div>

    <!-- FILTRES -->
    <form method="GET" action="gestion_etudiant.php">
      <div class="glass-panel">
        <div class="filters-wrapper">
          <div class="search-bar">
            <span style="font-size:1.1rem;color:var(--teal)">🔍</span>
            <input type="text" name="search" placeholder="Rechercher par nom, prénom ou email..."
                   value="<?= htmlspecialchars($search) ?>">
          </div>
          <select class="filter-select" name="promo">
            <option value="">Toutes les promos</option>
            <?php foreach ($promos as $p): ?>
            <option value="<?= htmlspecialchars($p) ?>" <?= $promo === $p ? 'selected' : '' ?>>
              Promo <?= htmlspecialchars($p) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-filter">🔍 Filtrer</button>
          <?php if ($search || $promo): ?>
          <a href="gestion_etudiant.php" style="font-size:0.85rem;color:var(--teal);font-weight:600;text-decoration:none;">✕ Réinitialiser</a>
          <?php endif; ?>
        </div>
      </div>
    </form>

    <!-- TABLEAU ÉTUDIANTS -->
    <div class="glass-panel table-container">
      <?php if (empty($etudiants)): ?>
        <div class="empty-state">Aucun étudiant trouvé pour ces critères.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Nom Prénom</th>
            <th>Email</th>
            <th>Classe / Filière</th>
            <th>Promo</th>
            <th>Moyenne</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($etudiants as $e): ?>
          <?php
            $moy = $e['moyenne'];
            if ($moy === null) { $moyClass = 'moy-none'; $moyLabel = '—'; }
            elseif ($moy >= 16) { $moyClass = 'moy-good';  $moyLabel = $moy; }
            elseif ($moy >= 12) { $moyClass = 'moy-avg';   $moyLabel = $moy; }
            elseif ($moy >= 10) { $moyClass = 'moy-low';   $moyLabel = $moy; }
            else                { $moyClass = 'moy-fail';  $moyLabel = $moy; }
          ?>
          <tr>
            <td style="color:#6b8080;font-size:0.85rem">#<?= $e['id'] ?></td>
            <td><strong><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></strong></td>
            <td style="color:#4a6a6a;font-size:0.88rem"><?= htmlspecialchars($e['email']) ?></td>
            <td><?= htmlspecialchars($e['classe']) ?></td>
            <td><?= htmlspecialchars($e['promo'] ?? '—') ?></td>
            <td><span class="badge-moyenne <?= $moyClass ?>"><?= $moyLabel ?>/20</span></td>
            <td>
              <div class="action-links">
                <!-- Bouton Éditer : ouvre la modale -->
                <button class="btn-action edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)'>
                  ✏️ Éditer
                </button>
                <!-- Formulaire Supprimer -->
                <form method="POST" style="margin:0"
                      onsubmit="return confirm('⚠️ Supprimer définitivement <?= htmlspecialchars($e['prenom'] . ' ' . $e['nom'], ENT_QUOTES) ?> ?')">
                  <input type="hidden" name="action"      value="delete_etudiant">
                  <input type="hidden" name="id_etudiant" value="<?= $e['id'] ?>">
                  <button type="submit" class="btn-action del">🗑️ Supprimer</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="padding:12px 0 4px;font-size:0.82rem;color:#6b8080;text-align:right">
        <?= count($etudiants) ?> étudiant<?= count($etudiants) > 1 ? 's' : '' ?> affiché<?= count($etudiants) > 1 ? 's' : '' ?>
      </div>
      <?php endif; ?>
    </div>

  </main>

  <!-- ══ MODALE ÉDITION ══ -->
  <div class="modal-overlay" id="editModal">
    <div class="modal-box">
      <button class="btn-close" onclick="closeEditModal()">✕</button>
      <div class="modal-title">✏️ Modifier le profil étudiant</div>

      <form method="POST" action="gestion_etudiant.php" class="form-grid">
        <input type="hidden" name="action"      value="edit_etudiant">
        <input type="hidden" name="id_etudiant" id="edit-id">

        <div class="form-group">
          <label>Nom</label>
          <input type="text" name="nom" id="edit-nom" required>
        </div>
        <div class="form-group">
          <label>Prénom</label>
          <input type="text" name="prenom" id="edit-prenom" required>
        </div>
        <div class="form-group full-width">
          <label>Adresse e-mail</label>
          <input type="email" name="email" id="edit-email" required>
        </div>
        <div class="form-group">
          <label>Classe / Filière</label>
          <input type="text" name="classe" id="edit-classe" required>
        </div>
        <div class="form-group">
          <label>Promotion (année)</label>
          <input type="text" name="promo" id="edit-promo" maxlength="10">
        </div>
        <div class="form-group full-width">
          <label>Dernier diplôme obtenu</label>
          <input type="text" name="diplome" id="edit-diplome">
        </div>
        <div class="form-group">
          <label>Téléphone</label>
          <input type="number" name="num_tel" id="edit-tel">
        </div>
        <div class="form-group">
          <label>Date de naissance</label>
          <input type="date" name="date_naissance" id="edit-naissance">
        </div>

        <div class="modal-btns full-width">
          <button type="button" class="btn-cancel" onclick="closeEditModal()">Annuler</button>
          <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
        </div>
      </form>
    </div>
  </div>

  <footer>© 2026 SmartCampus — Tous droits réservés</footer>

  <script>
    function openEditModal(e) {
      document.getElementById('edit-id').value        = e.id;
      document.getElementById('edit-nom').value       = e.nom;
      document.getElementById('edit-prenom').value    = e.prenom;
      document.getElementById('edit-email').value     = e.email;
      document.getElementById('edit-classe').value    = e.classe;
      document.getElementById('edit-promo').value     = e.promo  ?? '';
      document.getElementById('edit-diplome').value   = e.diplome ?? '';
      document.getElementById('edit-tel').value       = e.num_tel ?? '';
      document.getElementById('edit-naissance').value = e.date_naissance ?? '';
      document.getElementById('editModal').classList.add('open');
    }
    function closeEditModal() {
      document.getElementById('editModal').classList.remove('open');
    }
    document.getElementById('editModal').addEventListener('click', e => {
      if (e.target === document.getElementById('editModal')) closeEditModal();
    });
  </script>
</body>
</html>
