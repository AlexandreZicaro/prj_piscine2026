<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: connexion_et_incription.html");
    exit();
}
require_once 'db.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_cours') {
        $nom = trim($_POST['nom_cours']); $description = trim($_POST['description']);
        $capacite = intval($_POST['capacite_max']); $id_ens = intval($_POST['id_enseignant']);
        $pdo->prepare("INSERT INTO cours (nom_cours, description, capacite_max, id_enseignant, id_administrateur) VALUES (?,?,?,?,?)")
            ->execute([$nom, $description, $capacite, $id_ens, $_SESSION['user_id']]);
        $message = "✅ Cours \"$nom\" créé avec succès."; $messageType = 'success';
    }
    elseif ($action === 'edit_cours') {
        $id = intval($_POST['id_cours']); $nom = trim($_POST['nom_cours']);
        $description = trim($_POST['description']); $capacite = intval($_POST['capacite_max']);
        $id_ens = intval($_POST['id_enseignant']);
        $pdo->prepare("UPDATE cours SET nom_cours=?, description=?, capacite_max=?, id_enseignant=? WHERE id_cours=?")
            ->execute([$nom, $description, $capacite, $id_ens, $id]);
        $message = "✅ Cours \"$nom\" modifié avec succès."; $messageType = 'success';
    }
    elseif ($action === 'delete_cours') {
        $id = intval($_POST['id_cours']);
        $s = $pdo->prepare("SELECT nom_cours FROM cours WHERE id_cours=?"); $s->execute([$id]);
        $nom = $s->fetchColumn();
        $pdo->prepare("DELETE FROM cours WHERE id_cours=?")->execute([$id]);
        $message = "🗑️ Cours \"$nom\" supprimé."; $messageType = 'danger';
    }
    elseif ($action === 'affecter_prof') {
        $pdo->prepare("UPDATE cours SET id_enseignant=? WHERE id_cours=?")
            ->execute([intval($_POST['id_enseignant']), intval($_POST['id_cours'])]);
        $message = "✅ Enseignant affecté avec succès."; $messageType = 'success';
    }
    elseif ($action === 'inscrire_etudiant') {
        $id_cours = intval($_POST['id_cours']); $id_etudiant = intval($_POST['id_etudiant']);
        $s = $pdo->prepare("SELECT id_enseignant FROM cours WHERE id_cours=?"); $s->execute([$id_cours]);
        $id_ens = $s->fetchColumn();
        if (!$id_ens) {
            $message = "⚠️ Ce cours n'a pas d'enseignant affecté."; $messageType = 'warning';
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM inscription WHERE id_etudiant=? AND id_enseignant=?");
            $check->execute([$id_etudiant, $id_ens]);
            if ($check->fetchColumn() > 0) {
                $message = "⚠️ Cet étudiant est déjà inscrit à ce cours."; $messageType = 'warning';
            } else {
                $pdo->prepare("INSERT INTO inscription (date_inscription, id_etudiant, id_enseignant) VALUES (CURDATE(),?,?)")
                    ->execute([$id_etudiant, $id_ens]);
                $message = "✅ Étudiant inscrit au cours avec succès."; $messageType = 'success';
            }
        }
    }
    elseif ($action === 'desinscrire_etudiant') {
        $pdo->prepare("DELETE FROM inscription WHERE id_inscription=?")->execute([intval($_POST['id_inscription'])]);
        $message = "✅ Étudiant désinscrit."; $messageType = 'success';
    }
}

$cours = $pdo->query("
    SELECT c.*, e.nom AS ens_nom, e.prenom AS ens_prenom
    FROM cours c LEFT JOIN enseignant e ON c.id_enseignant = e.id
    ORDER BY c.nom_cours ASC
")->fetchAll(PDO::FETCH_ASSOC);

$enseignants = $pdo->query("SELECT id, nom, prenom, departement FROM enseignant ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
$etudiants   = $pdo->query("SELECT id, nom, prenom, classe FROM etudiant ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

// CORRECTION BUG : inscrits indexés par id_cours
$inscrits = [];
foreach ($cours as $c) {
    if (!$c['id_enseignant']) { $inscrits[$c['id_cours']] = []; continue; }
    $st = $pdo->prepare("
        SELECT i.id_inscription, i.id_etudiant, e.nom, e.prenom, e.classe
        FROM inscription i JOIN etudiant e ON i.id_etudiant = e.id
        WHERE i.id_enseignant = ?
    ");
    $st->execute([$c['id_enseignant']]);
    $inscrits[$c['id_cours']] = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Gestion des Cours - SmartCampus</title>
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
    .page-wrapper{position:relative;z-index:1;width:100%;max-width:1200px;margin:0 auto;padding:6rem 1.5rem 5rem;display:flex;flex-direction:column;gap:2rem}
    .glass-panel{background:rgba(255,255,255,.65);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.8);border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.06);padding:1.5rem 2rem}
    .header-row{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem}
    .page-title{font-family:'Syne',sans-serif;font-size:1.8rem;font-weight:800;color:var(--navy);background:rgba(255,255,255,.55);padding:10px 20px;border-radius:12px;border:1px solid rgba(255,255,255,.8)}
    .btn-add{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:10px;padding:12px 24px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;text-decoration:none;transition:transform .2s}
    .btn-add:hover{transform:translateY(-2px)}
    .flash{padding:14px 20px;border-radius:12px;font-weight:600;font-size:.95rem}
    .flash.success{background:rgba(42,157,143,.15);color:var(--success);border:1px solid rgba(42,157,143,.3)}
    .flash.danger{background:rgba(230,57,70,.1);color:var(--alert);border:1px solid rgba(230,57,70,.3)}
    .flash.warning{background:rgba(244,162,97,.15);color:#c06a10;border:1px solid rgba(244,162,97,.4)}
    table{width:100%;border-collapse:collapse}
    th{font-family:'Syne',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;color:#3d5a5a;padding:10px 12px;border-bottom:2px solid rgba(13,27,42,.15);text-align:left}
    td{padding:12px;border-bottom:1px solid rgba(13,27,42,.07);font-size:.92rem;vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(255,255,255,.5);cursor:pointer}
    .badge-ens{display:inline-block;background:rgba(10,147,150,.12);color:var(--teal);padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700}
    .badge-none{display:inline-block;background:rgba(230,57,70,.1);color:var(--alert);padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700}
    .badge-count{display:inline-block;background:rgba(13,27,42,.08);color:var(--navy);padding:3px 10px;border-radius:20px;font-size:.8rem;font-weight:700}
    .btn-action{background:none;border:none;font-size:.82rem;font-weight:700;cursor:pointer;padding:5px 10px;border-radius:6px;font-family:'DM Sans',sans-serif;transition:all .2s}
    .btn-action.edit{color:var(--teal);background:rgba(10,147,150,.1)}.btn-action.edit:hover{background:var(--teal);color:#fff}
    .btn-action.danger{color:var(--alert);background:rgba(230,57,70,.1)}.btn-action.danger:hover{background:var(--alert);color:#fff}
    .detail-panel{display:none}.detail-panel.open{display:block}
    .detail-title{font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:800;color:var(--navy);margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:2px solid rgba(13,27,42,.08);display:flex;justify-content:space-between;align-items:center}
    .detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:2rem}
    @media(max-width:800px){.detail-grid{grid-template-columns:1fr}}
    .section-label{font-family:'Syne',sans-serif;font-size:.8rem;font-weight:700;text-transform:uppercase;color:#6b8080;margin-bottom:.8rem;letter-spacing:.05em}
    .inline-form{display:flex;gap:.8rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
    .form-select{background:rgba(255,255,255,.9);border:1.5px solid #cce0e0;border-radius:10px;padding:10px 14px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--navy);outline:none;flex:1;min-width:180px;transition:border-color .2s}
    .form-select:focus{border-color:var(--teal)}
    .btn-submit{background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-family:'Syne',sans-serif;font-weight:700;font-size:.88rem;cursor:pointer;white-space:nowrap;transition:transform .2s}
    .btn-submit:hover{transform:translateY(-1px)}
    .inscrits-list{display:flex;flex-direction:column;gap:8px;max-height:300px;overflow-y:auto}
    .inscrit-item{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:rgba(255,255,255,.6);border-radius:10px;border-left:3px solid var(--teal)}
    .inscrit-name{font-weight:700;font-size:.9rem}.inscrit-classe{font-size:.78rem;color:#6b8080;margin-top:2px}
    .btn-rm{background:none;border:none;color:var(--alert);font-size:1rem;cursor:pointer;padding:4px 8px;border-radius:6px;transition:background .2s}.btn-rm:hover{background:rgba(230,57,70,.12)}
    .inscrits-empty{color:#6b8080;font-style:italic;font-size:.88rem;padding:.5rem 0}
    .danger-zone{border-top:2px dashed rgba(230,57,70,.3);padding-top:1rem;margin-top:1rem}
    .btn-danger{background:var(--alert);color:#fff;border:none;border-radius:10px;padding:10px 20px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer;width:100%;transition:opacity .2s}.btn-danger:hover{opacity:.85}
    .modal-overlay{position:fixed;inset:0;background:rgba(13,27,42,.5);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:300;opacity:0;pointer-events:none;transition:opacity .3s}
    .modal-overlay.open{opacity:1;pointer-events:all}
    .modal-box{background:var(--white);border-radius:20px;width:520px;max-width:94vw;padding:2rem;box-shadow:0 28px 80px rgba(0,0,0,.2);position:relative;max-height:90vh;overflow-y:auto}
    .modal-title{font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:800;color:var(--navy);margin-bottom:1.5rem}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.full-width{grid-column:span 2}
    .form-group{display:flex;flex-direction:column;gap:6px}
    .form-group label{font-size:.78rem;font-weight:700;text-transform:uppercase;color:#3d5a5a;letter-spacing:.04em}
    .form-group input,.form-group select,.form-group textarea{padding:10px 14px;border:1.5px solid #cce0e0;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.92rem;color:var(--navy);background:#f0fafa;outline:none;transition:border-color .2s}
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--teal);background:#fff}
    .form-group textarea{resize:vertical;min-height:80px}
    .modal-btns{display:flex;gap:1rem;margin-top:1.5rem}
    .btn-save{flex:1;padding:12px;background:linear-gradient(135deg,var(--teal),#00b4d8);color:#fff;border:none;border-radius:12px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer}
    .btn-cancel{padding:12px 24px;background:#e0f0ef;color:var(--navy);border:none;border-radius:12px;font-family:'Syne',sans-serif;font-weight:700;cursor:pointer}
    .btn-close-modal{position:absolute;top:16px;right:18px;background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6b8080}
    .empty-state{text-align:center;padding:2.5rem;color:#6b8080;font-style:italic}
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
      <a href="#" class="active">Gestion Cours</a>
      <a href="deconnexion.php" class="nav-logout">🚪 Déconnexion</a>
    </div>
  </nav>
  <main class="page-wrapper">
    <div class="header-row">
      <div class="page-title">📚 Gestion des Cours & Affectations</div>
      <button class="btn-add" onclick="openModal()">📚+ Créer un cours</button>
    </div>
    <?php if ($message): ?><div class="flash <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <div class="glass-panel">
      <?php if (empty($cours)): ?>
        <div class="empty-state">Aucun cours créé. Cliquez sur "Créer un cours" pour commencer.</div>
      <?php else: ?>
      <table>
        <thead><tr><th>Cours</th><th>Description</th><th>Capacité</th><th>Enseignant</th><th>Élèves inscrits</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach ($cours as $c):
            $nbI = count($inscrits[$c['id_cours']] ?? []);
          ?>
          <tr onclick="openDetail(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($inscrits[$c['id_cours']] ?? []),ENT_QUOTES) ?>)">
            <td><strong><?= htmlspecialchars($c['nom_cours']) ?></strong></td>
            <td style="color:#4a6a6a;font-size:.85rem"><?= htmlspecialchars($c['description'] ?? '—') ?></td>
            <td><?= $c['capacite_max'] ?? '—' ?></td>
            <td><?php if($c['ens_nom']): ?><span class="badge-ens"><?= htmlspecialchars($c['ens_prenom'].' '.$c['ens_nom']) ?></span><?php else: ?><span class="badge-none">Non affecté</span><?php endif; ?></td>
            <td><span class="badge-count"><?= $nbI ?> élève<?= $nbI>1?'s':'' ?></span></td>
            <td onclick="event.stopPropagation()" style="white-space:nowrap">
              <button class="btn-action edit" onclick="openModalEdit(<?= htmlspecialchars(json_encode($c),ENT_QUOTES) ?>)">✏️ Modifier</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce cours ?')">
                <input type="hidden" name="action" value="delete_cours">
                <input type="hidden" name="id_cours" value="<?= $c['id_cours'] ?>">
                <button type="submit" class="btn-action danger">🗑️ Supprimer</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <div class="glass-panel detail-panel" id="detailPanel">
      <div class="detail-title">
        <span id="det-nom">—</span>
        <button onclick="document.getElementById('detailPanel').classList.remove('open')" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#6b8080">✕</button>
      </div>
      <div class="detail-grid">
        <div>
          <div class="section-label">👨‍🏫 Affecter un enseignant</div>
          <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="affecter_prof">
            <input type="hidden" name="id_cours" id="aff-id-cours">
            <select name="id_enseignant" class="form-select" id="aff-sel-ens" required>
              <?php foreach($enseignants as $e): ?>
              <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'].' '.$e['nom']) ?> — <?= htmlspecialchars($e['departement']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-submit">✅ Valider</button>
          </form>
          <div class="section-label" style="margin-top:1.5rem">🎓 Inscrire un élève</div>
          <form method="POST" class="inline-form">
            <input type="hidden" name="action" value="inscrire_etudiant">
            <input type="hidden" name="id_cours" id="ins-id-cours">
            <select name="id_etudiant" class="form-select" required>
              <?php foreach($etudiants as $et): ?>
              <option value="<?= $et['id'] ?>"><?= htmlspecialchars($et['prenom'].' '.$et['nom']) ?> — <?= htmlspecialchars($et['classe']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-submit">➕ Inscrire</button>
          </form>
        </div>
        <div>
          <div class="section-label">📋 Élèves inscrits à ce cours</div>
          <div class="inscrits-list" id="inscritsList"><div class="inscrits-empty">Cliquez sur un cours.</div></div>
          <div class="danger-zone">
            <form method="POST" onsubmit="return confirm('Supprimer ce cours définitivement ?')">
              <input type="hidden" name="action" value="delete_cours">
              <input type="hidden" name="id_cours" id="del-id-cours">
              <button type="submit" class="btn-danger">🗑️ Supprimer ce cours</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal-overlay" id="courseModal">
    <div class="modal-box">
      <button class="btn-close-modal" onclick="closeModal()">✕</button>
      <div class="modal-title" id="modalTitle">Créer un nouveau cours</div>
      <form method="POST" class="form-grid">
        <input type="hidden" name="action" id="form-action" value="create_cours">
        <input type="hidden" name="id_cours" id="form-id">
        <div class="form-group full-width"><label>Nom du cours</label><input type="text" name="nom_cours" id="form-nom" required placeholder="Ex: Bases de données NoSQL"></div>
        <div class="form-group full-width"><label>Description</label><textarea name="description" id="form-desc" placeholder="Description..."></textarea></div>
        <div class="form-group"><label>Capacité max</label><input type="number" name="capacite_max" id="form-cap" min="1" max="200" placeholder="30"></div>
        <div class="form-group"><label>Enseignant responsable</label>
          <select name="id_enseignant" id="form-ens" required>
            <?php foreach($enseignants as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'].' '.$e['nom']) ?> — <?= htmlspecialchars($e['departement']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="modal-btns full-width">
          <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
          <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
      </form>
    </div>
  </div>
  <footer>© 2026 SmartCampus — Tous droits réservés</footer>
  <script>
    const inscritsData = <?= json_encode($inscrits) ?>;
    function openDetail(cours, inscrits) {
      document.getElementById('det-nom').textContent = cours.nom_cours;
      document.getElementById('aff-id-cours').value  = cours.id_cours;
      document.getElementById('ins-id-cours').value  = cours.id_cours;
      document.getElementById('del-id-cours').value  = cours.id_cours;
      const selEns = document.getElementById('aff-sel-ens');
      if (selEns && cours.id_enseignant) selEns.value = cours.id_enseignant;
      const list = document.getElementById('inscritsList');
      const data = inscritsData[cours.id_cours] || [];
      list.innerHTML = data.length ? data.map(i => `
        <div class="inscrit-item">
          <div><div class="inscrit-name">${i.prenom} ${i.nom}</div><div class="inscrit-classe">${i.classe}</div></div>
          <form method="POST" style="margin:0" onsubmit="return confirm('Désinscrire cet élève ?')">
            <input type="hidden" name="action" value="desinscrire_etudiant">
            <input type="hidden" name="id_inscription" value="${i.id_inscription}">
            <button type="submit" class="btn-rm">✕</button>
          </form>
        </div>`).join('') : '<div class="inscrits-empty">Aucun élève inscrit.</div>';
      document.getElementById('detailPanel').classList.add('open');
      document.getElementById('detailPanel').scrollIntoView({behavior:'smooth',block:'start'});
    }
    function openModal() {
      document.getElementById('modalTitle').textContent = 'Créer un nouveau cours';
      document.getElementById('form-action').value='create_cours'; document.getElementById('form-id').value='';
      document.getElementById('form-nom').value=''; document.getElementById('form-desc').value=''; document.getElementById('form-cap').value='';
      document.getElementById('courseModal').classList.add('open');
    }
    function openModalEdit(cours) {
      document.getElementById('modalTitle').textContent = 'Modifier le cours';
      document.getElementById('form-action').value='edit_cours'; document.getElementById('form-id').value=cours.id_cours;
      document.getElementById('form-nom').value=cours.nom_cours; document.getElementById('form-desc').value=cours.description||'';
      document.getElementById('form-cap').value=cours.capacite_max||''; document.getElementById('form-ens').value=cours.id_enseignant||'';
      document.getElementById('courseModal').classList.add('open');
    }
    function closeModal() { document.getElementById('courseModal').classList.remove('open'); }
    document.getElementById('courseModal').addEventListener('click',e=>{if(e.target===document.getElementById('courseModal'))closeModal();});
  </script>
</body>
</html>
