<?php
session_start();
require_once 'db.php';
require_once 'upload_helper.php';

if (!isset($_SESSION['admin'])) { header('Location: index.php'); exit; }

// Recharger l'admin depuis la BDD (données fraîches)
$stmt = $pdo->prepare("SELECT * FROM admin WHERE id=?");
$stmt->execute([$_SESSION['admin']['id']]);
$admin = $stmt->fetch();
if (!$admin) { session_destroy(); header('Location: index.php'); exit; }
$_SESSION['admin'] = $admin;

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // ── MODIFIER PROFIL ADMIN ──
    if ($act === 'update_admin') {
        $nom    = trim($_POST['nom']    ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email  = trim($_POST['email']  ?? '');
        $newpass = trim($_POST['newpass'] ?? '');

        if (!$nom || !$prenom || !$email) {
            $flash = 'error|Tous les champs sont obligatoires.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM admin WHERE email=? AND id!=?");
            $chk->execute([$email, $admin['id']]);
            if ($chk->fetch()) {
                $flash = 'error|Cet email est déjà utilisé.';
            } else {
                // Upload photo
                $photo = $admin['photo'];
                $newPhoto = uploadPhoto($_FILES['photo'] ?? [], 'admin_', $photo);
                if ($newPhoto !== false) $photo = $newPhoto;

                if ($newpass) {
                    if (strlen($newpass) < 6) {
                        $flash = 'error|Mot de passe minimum 6 caractères.';
                        goto end_admin_update;
                    }
                    $hash = password_hash($newpass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE admin SET nom=?,prenom=?,email=?,password=?,photo=? WHERE id=?")
                        ->execute([$nom,$prenom,$email,$hash,$photo,$admin['id']]);
                } else {
                    $pdo->prepare("UPDATE admin SET nom=?,prenom=?,email=?,photo=? WHERE id=?")
                        ->execute([$nom,$prenom,$email,$photo,$admin['id']]);
                }
                // Recharger session
                $s2 = $pdo->prepare("SELECT * FROM admin WHERE id=?");
                $s2->execute([$admin['id']]);
                $admin = $s2->fetch();
                $_SESSION['admin'] = $admin;
                $flash = 'success|Informations mises à jour avec succès !';
            }
        }
        end_admin_update:;
    }

    // ── AJOUTER UTILISATEUR ──
    if ($act === 'add_user') {
        $nom    = trim($_POST['nom']      ?? '');
        $prenom = trim($_POST['prenom']   ?? '');
        $email  = trim($_POST['email']    ?? '');
        $pass   = trim($_POST['password'] ?? '');

        if (!$nom || !$prenom || !$email || !$pass) {
            $flash = 'error|Tous les champs sont obligatoires.';
        } elseif (strlen($pass) < 6) {
            $flash = 'error|Mot de passe minimum 6 caractères.';
        } else {
            $photo = uploadPhoto($_FILES['photo'] ?? [], 'user_');
            if ($photo === false) $photo = null;
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO utilisateurs (nom,prenom,email,password,photo) VALUES (?,?,?,?,?)")
                    ->execute([$nom,$prenom,$email,$hash,$photo]);
                $flash = 'success|Utilisateur ajouté avec succès !';
            } catch (Exception $e) {
                $flash = 'error|Cet email est déjà utilisé.';
            }
        }
    }

    // ── MODIFIER UTILISATEUR ──
    if ($act === 'edit_user') {
        $id      = intval($_POST['id']);
        $nom     = trim($_POST['nom']    ?? '');
        $prenom  = trim($_POST['prenom'] ?? '');
        $email   = trim($_POST['email']  ?? '');
        $newpass = trim($_POST['newpass'] ?? '');

        $u = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
        $u->execute([$id]); $u = $u->fetch();
        if (!$u) { $flash = 'error|Utilisateur introuvable.'; goto end_edit; }

        $photo = $u['photo'];
        $newPhoto = uploadPhoto($_FILES['photo'] ?? [], 'user_', $photo);
        if ($newPhoto !== false) $photo = $newPhoto;

        if ($newpass) {
            if (strlen($newpass) < 6) { $flash = 'error|Mot de passe minimum 6 caractères.'; goto end_edit; }
            $hash = password_hash($newpass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,password=?,photo=? WHERE id=?")
                ->execute([$nom,$prenom,$email,$hash,$photo,$id]);
        } else {
            $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,photo=? WHERE id=?")
                ->execute([$nom,$prenom,$email,$photo,$id]);
        }
        $flash = 'success|Utilisateur modifié avec succès !';
        end_edit:;
    }

    // ── SUPPRIMER UTILISATEUR ──
    if ($act === 'del_user') {
        $id = intval($_POST['id']);
        // Supprimer la photo associée
        $u = $pdo->prepare("SELECT photo FROM utilisateurs WHERE id=?");
        $u->execute([$id]); $u = $u->fetch();
        if ($u && $u['photo'] && file_exists(__DIR__.'/'.$u['photo'])) {
            @unlink(__DIR__.'/'.$u['photo']);
        }
        $pdo->prepare("DELETE FROM utilisateurs WHERE id=?")->execute([$id]);
        $flash = 'success|Utilisateur supprimé.';
    }

    // ── DÉCONNEXION ──
    if ($act === 'logout') { session_destroy(); header('Location: index.php'); exit; }
}

$users   = $pdo->query("SELECT * FROM utilisateurs ORDER BY created_at DESC")->fetchAll();
$page    = $_GET['page'] ?? 'users';

$editUser = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
    $s->execute([intval($_GET['edit'])]); $editUser = $s->fetch();
}
$viewUser = null;
if (isset($_GET['voir'])) {
    $s = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
    $s->execute([intval($_GET['voir'])]); $viewUser = $s->fetch();
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin – AppliGestion</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#0f172a;--sb:#1e293b;--card:#1e293b;--border:#334155;--accent:#38bdf8;--accent2:#818cf8;--success:#34d399;--danger:#f87171;--warn:#fbbf24;--text:#e2e8f0;--muted:#94a3b8;--sw:248px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}
a{text-decoration:none;color:inherit;}

/* SIDEBAR */
.sb{width:var(--sw);background:var(--sb);border-right:1px solid var(--border);position:fixed;top:0;bottom:0;left:0;display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sb-top{padding:24px 20px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;}
.sb-photo{width:76px;height:76px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);}
.sb-ini{width:76px;height:76px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff;border:3px solid var(--accent);}
.sb-name{font-size:14px;font-weight:700;}
.sb-email{font-size:11px;color:var(--muted);}
.sb-badge{background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.25);color:var(--accent);font-size:10px;font-weight:600;padding:3px 11px;border-radius:20px;text-transform:uppercase;letter-spacing:.7px;}

.sb-nav{flex:1;padding:10px 0;}
.nt{font-size:9px;letter-spacing:1.5px;text-transform:uppercase;color:var(--muted);padding:14px 20px 4px;}
.ni{display:flex;align-items:center;gap:10px;padding:11px 20px;color:var(--muted);font-size:13px;font-weight:500;transition:all .18s;position:relative;}
.ni:hover{color:var(--text);background:rgba(255,255,255,.04);}
.ni.active{color:var(--accent);background:rgba(56,189,248,.08);}
.ni.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--accent);border-radius:0 3px 3px 0;}
.ni i{width:16px;text-align:center;font-size:13px;}
.nb{margin-left:auto;background:var(--accent);color:var(--bg);font-size:9px;font-weight:700;padding:2px 6px;border-radius:20px;}
.sb-bot{padding:14px 20px;border-top:1px solid var(--border);}

/* MAIN */
.main{flex:1;margin-left:var(--sw);display:flex;flex-direction:column;min-height:100vh;}
.topbar{height:60px;background:var(--sb);border-bottom:1px solid var(--border);display:flex;align-items:center;padding:0 22px;position:sticky;top:0;z-index:90;}
.topbar h1{font-size:17px;font-weight:700;}
.tb-badge{margin-left:auto;background:rgba(56,189,248,.1);border:1px solid rgba(56,189,248,.2);color:var(--accent);font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;}
.mc{flex:1;padding:22px;}

/* FLASH */
.flash{padding:11px 15px;border-radius:9px;margin-bottom:16px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;}
.flash.success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--success);}
.flash.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--danger);}

/* STATS */
.sg{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;}
.sc{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:18px;transition:transform .2s;}
.sc:hover{transform:translateY(-2px);}
.si{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;margin-bottom:10px;}
.sv{font-size:26px;font-weight:700;}
.sl{font-size:11px;color:var(--muted);}
.si-bl{background:rgba(56,189,248,.12);color:var(--accent);}
.si-gr{background:rgba(52,211,153,.12);color:var(--success);}
.si-pu{background:rgba(129,140,248,.12);color:var(--accent2);}

/* SEARCH */
.sbox{background:var(--card);border:1px solid var(--border);border-radius:9px;padding:10px 14px;display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.sbox i{color:var(--muted);font-size:14px;flex-shrink:0;}
.sbox input{background:none;border:none;outline:none;color:var(--text);font-family:inherit;font-size:14px;width:100%;}
.sbox input::placeholder{color:var(--muted);}
.sbox .clear{color:var(--muted);cursor:pointer;display:none;}
.sbox .clear:hover{color:var(--danger);}
.search-count{font-size:12px;color:var(--muted);margin-bottom:10px;}
.hl{background:rgba(56,189,248,.25);color:var(--accent);border-radius:3px;padding:0 2px;}

/* TABLE */
.tw{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.tw-head{padding:13px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.tw-head h3{font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 15px;text-align:left;font-size:10px;letter-spacing:1px;text-transform:uppercase;color:var(--muted);background:rgba(0,0,0,.2);}
tbody td{padding:12px 15px;border-top:1px solid var(--border);font-size:13px;vertical-align:middle;}
tbody tr:hover td{background:rgba(255,255,255,.02);}
.uph{width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--border);}
.upi{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;}
.ab{display:flex;gap:5px;}
.acb{width:28px;height:28px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;cursor:pointer;border:none;transition:all .15s;}
.ace{background:rgba(56,189,248,.1);color:var(--accent);}
.ace:hover{background:rgba(56,189,248,.2);}
.acd{background:rgba(248,113,113,.1);color:var(--danger);}
.acd:hover{background:rgba(248,113,113,.2);}
.acv{background:rgba(52,211,153,.1);color:var(--success);}
.acv:hover{background:rgba(52,211,153,.2);}
.tf{padding:11px 15px;border-top:1px solid var(--border);font-size:12px;color:var(--muted);}
.no-results{text-align:center;padding:40px;color:var(--muted);}

/* CARD FORM */
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px;}
.card h3{font-size:14px;font-weight:700;margin-bottom:15px;color:var(--accent);display:flex;align-items:center;gap:7px;padding-bottom:11px;border-bottom:1px solid var(--border);}
.fc{width:100%;background:var(--bg);border:1.5px solid var(--border);color:var(--text);padding:10px 12px;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .2s;}
.fc:focus{border-color:var(--accent);}
.fg{margin-bottom:13px;}
.fg label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:5px;}
.fr2{display:grid;grid-template-columns:1fr 1fr;gap:13px;}
.fa{display:flex;gap:10px;justify-content:flex-end;margin-top:13px;}
.req{color:var(--danger);}
.btn{padding:9px 17px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .18s;display:inline-flex;align-items:center;gap:7px;font-family:inherit;}
.btn-p{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;}
.btn-p:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(56,189,248,.3);}
.btn-s{background:var(--bg);color:var(--muted);border:1px solid var(--border);}
.btn-s:hover{color:var(--text);}
.upz{border:2px dashed var(--border);border-radius:9px;padding:16px;text-align:center;cursor:pointer;position:relative;transition:border-color .2s;}
.upz:hover{border-color:var(--accent);}
.upz input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.upz i{font-size:20px;color:var(--accent);margin-bottom:5px;display:block;}
.upz p{font-size:12px;color:var(--muted);}
.upz small{font-size:11px;color:var(--accent);}
/* Preview photo */
.photo-preview{width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);display:none;margin:8px auto 0;}

/* MODAL */
.mo{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:500;align-items:center;justify-content:center;}
.mo.on{display:flex;}
.modal{background:var(--sb);border:1px solid var(--border);border-radius:16px;padding:26px;width:460px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.6);animation:mi .2s ease;}
@keyframes mi{from{transform:scale(.93);opacity:0}to{transform:scale(1);opacity:1}}
.mh{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.mh h3{font-size:16px;font-weight:700;}
.mc2{width:28px;height:28px;border-radius:7px;background:var(--bg);border:none;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;}
.mc2:hover{color:var(--danger);}
.bp{width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);display:block;margin:0 auto 12px;}
.bni{width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#fff;margin:0 auto 12px;}
.pn{text-align:center;font-size:17px;font-weight:700;margin-bottom:3px;}
.pe{text-align:center;font-size:12px;color:var(--muted);margin-bottom:16px;}
.ig{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.ii{background:rgba(0,0,0,.2);border-radius:8px;padding:10px 12px;}
.ii label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:block;margin-bottom:3px;}
.ii span{font-size:13px;font-weight:500;}

@media(max-width:900px){.sg{grid-template-columns:1fr 1fr;}}
@media(max-width:768px){.sb{display:none;}.main{margin-left:0;}.fr2{grid-template-columns:1fr;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sb">
  <div class="sb-top">
    <?php if ($admin['photo']): ?>
      <img src="<?= htmlspecialchars($admin['photo']) ?>" class="sb-photo" alt="">
    <?php else: ?>
      <div class="sb-ini"><?= strtoupper(substr($admin['prenom'],0,1).substr($admin['nom'],0,1)) ?></div>
    <?php endif; ?>
    <div class="sb-name"><?= htmlspecialchars($admin['prenom'].' '.$admin['nom']) ?></div>
    <div class="sb-email"><?= htmlspecialchars($admin['email']) ?></div>
    <div class="sb-badge"><i class="fas fa-shield-alt"></i> Administrateur</div>
  </div>
  <nav class="sb-nav">
    <div class="nt">Gestion</div>
    <a href="admin.php?page=users" class="ni <?= $page==='users'?'active':'' ?>">
      <i class="fas fa-users"></i> Utilisateurs <span class="nb"><?= count($users) ?></span>
    </a>
    <a href="admin.php?page=add" class="ni <?= $page==='add'?'active':'' ?>">
      <i class="fas fa-user-plus"></i> Ajouter un utilisateur
    </a>
    <div class="nt">Compte</div>
    <a href="admin.php?page=profil" class="ni <?= $page==='profil'?'active':'' ?>">
      <i class="fas fa-user-cog"></i> Mon profil & Paramètres
    </a>
  </nav>
  <div class="sb-bot">
    <form method="POST"><input type="hidden" name="action" value="logout">
      <button type="submit" style="background:none;border:none;color:var(--muted);cursor:pointer;font-size:13px;display:flex;align-items:center;gap:8px;font-family:inherit;padding:0;transition:color .2s;" onmouseover="this.style.color='var(--danger)'" onmouseout="this.style.color='var(--muted)'">
        <i class="fas fa-sign-out-alt"></i> Se déconnecter
      </button>
    </form>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <h1><?php $titles=['users'=>'Utilisateurs','add'=>'Ajouter un utilisateur','profil'=>'Mon profil & Paramètres']; echo $titles[$page]??'Admin'; ?></h1>
    <div class="tb-badge"><i class="fas fa-shield-alt"></i> Admin connecté</div>
  </header>
  <div class="mc">

  <?php if ($flash): [$t,$m]=explode('|',$flash,2); ?>
  <div class="flash <?=$t?>"><i class="fas <?=$t==='success'?'fa-check-circle':'fa-exclamation-circle'?>"></i> <?=htmlspecialchars($m)?></div>
  <?php endif; ?>

  <?php /* ═════ USERS ═════ */ if ($page==='users'): ?>

  <div class="sg">
    <div class="sc"><div class="si si-bl"><i class="fas fa-users"></i></div><div class="sv"><?=count($users)?></div><div class="sl">Total utilisateurs</div></div>
    <div class="sc"><div class="si si-gr"><i class="fas fa-image"></i></div><div class="sv"><?=count(array_filter($users,fn($u)=>$u['photo']))?></div><div class="sl">Avec photo</div></div>
    <div class="sc"><div class="si si-pu"><i class="fas fa-calendar-day"></i></div><div class="sv"><?=count(array_filter($users,fn($u)=>date('Y-m-d',strtotime($u['created_at']))===date('Y-m-d')))?></div><div class="sl">Inscrits aujourd'hui</div></div>
  </div>

  <div class="sbox">
    <i class="fas fa-search"></i>
    <input type="text" id="searchInput" placeholder="Rechercher par nom, prénom ou email..." oninput="doSearch(this.value)">
    <i class="fas fa-times clear" id="clearBtn" onclick="clearSearch()"></i>
  </div>
  <div class="search-count" id="searchCount"></div>

  <div class="tw">
    <div class="tw-head">
      <h3><i class="fas fa-users" style="color:var(--accent)"></i> Liste des utilisateurs</h3>
      <a href="admin.php?page=add" class="btn btn-p"><i class="fas fa-plus"></i> Ajouter</a>
    </div>
    <table>
      <thead><tr><th>Photo</th><th>Nom complet</th><th>Email</th><th>Inscrit le</th><th>Actions</th></tr></thead>
      <tbody id="userTable">
      <?php foreach ($users as $u): ?>
      <tr class="user-row" data-search="<?= strtolower(htmlspecialchars($u['prenom'].' '.$u['nom'].' '.$u['email'])) ?>">
        <td>
          <?php if ($u['photo']): ?>
            <img src="<?= htmlspecialchars($u['photo']) ?>" class="uph" alt="">
          <?php else: ?>
            <div class="upi"><?= strtoupper(substr($u['prenom'],0,1).substr($u['nom'],0,1)) ?></div>
          <?php endif; ?>
        </td>
        <td><div style="font-weight:600" class="td-name"><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></div></td>
        <td style="color:var(--muted);font-size:12px" class="td-email"><?= htmlspecialchars($u['email']) ?></td>
        <td style="color:var(--muted);font-size:12px"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td>
          <div class="ab">
            <a href="admin.php?page=users&voir=<?=$u['id']?>" class="acb acv" title="Voir"><i class="fas fa-eye"></i></a>
            <a href="admin.php?page=users&edit=<?=$u['id']?>" class="acb ace" title="Modifier"><i class="fas fa-edit"></i></a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer cet utilisateur ?')">
              <input type="hidden" name="action" value="del_user">
              <input type="hidden" name="id" value="<?=$u['id']?>">
              <button type="submit" class="acb acd" title="Supprimer"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div id="noResults" class="no-results" style="display:none"><i class="fas fa-search" style="font-size:28px;display:block;margin-bottom:10px;color:var(--border)"></i>Aucun résultat</div>
    <div class="tf" id="tableFooter"><?=count($users)?> utilisateur<?=count($users)>1?'s':''?></div>
  </div>

  <!-- MODAL VOIR -->
  <?php if ($viewUser): ?>
  <div class="mo on">
    <div class="modal">
      <div class="mh">
        <h3><i class="fas fa-id-card" style="color:var(--accent)"></i> Profil utilisateur</h3>
        <a href="admin.php?page=users"><button class="mc2"><i class="fas fa-times"></i></button></a>
      </div>
      <?php if ($viewUser['photo']): ?>
        <img src="<?=htmlspecialchars($viewUser['photo'])?>" class="bp" alt="">
      <?php else: ?>
        <div class="bni"><?=strtoupper(substr($viewUser['prenom'],0,1).substr($viewUser['nom'],0,1))?></div>
      <?php endif; ?>
      <div class="pn"><?=htmlspecialchars($viewUser['prenom'].' '.$viewUser['nom'])?></div>
      <div class="pe"><?=htmlspecialchars($viewUser['email'])?></div>
      <div class="ig">
        <div class="ii"><label>Prénom</label><span><?=htmlspecialchars($viewUser['prenom'])?></span></div>
        <div class="ii"><label>Nom</label><span><?=htmlspecialchars($viewUser['nom'])?></span></div>
        <div class="ii"><label>Email</label><span style="font-size:12px"><?=htmlspecialchars($viewUser['email'])?></span></div>
        <div class="ii"><label>Inscrit le</label><span><?=date('d/m/Y',strtotime($viewUser['created_at']))?></span></div>
        <div class="ii"><label>Dernière MAJ</label><span><?=date('d/m/Y H:i',strtotime($viewUser['updated_at']))?></span></div>
        <div class="ii"><label>Photo</label><span><?=$viewUser['photo']?'✅ Oui':'❌ Non'?></span></div>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end">
        <a href="admin.php?page=users&edit=<?=$viewUser['id']?>" class="btn btn-p"><i class="fas fa-edit"></i> Modifier</a>
        <a href="admin.php?page=users" class="btn btn-s">Fermer</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- FORM EDIT INLINE -->
  <?php if ($editUser): ?>
  <div class="card">
    <h3><i class="fas fa-edit"></i> Modifier — <?=htmlspecialchars($editUser['prenom'].' '.$editUser['nom'])?></h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="id" value="<?=$editUser['id']?>">
      <div class="fr2">
        <div class="fg"><label>Prénom <span class="req">*</span></label><input type="text" name="prenom" class="fc" value="<?=htmlspecialchars($editUser['prenom'])?>" required></div>
        <div class="fg"><label>Nom <span class="req">*</span></label><input type="text" name="nom" class="fc" value="<?=htmlspecialchars($editUser['nom'])?>" required></div>
        <div class="fg"><label>Email <span class="req">*</span></label><input type="email" name="email" class="fc" value="<?=htmlspecialchars($editUser['email'])?>" required></div>
        <div class="fg"><label>Nouveau mot de passe <span style="color:var(--muted)">(vide = inchangé)</span></label><input type="password" name="newpass" class="fc" placeholder="Nouveau mot de passe..."></div>
      </div>
      <div class="fg">
        <label>Changer la photo (optionnel)</label>
        <?php if($editUser['photo']): ?>
        <div style="margin-bottom:10px"><img src="<?=htmlspecialchars($editUser['photo'])?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--accent)"></div>
        <?php endif; ?>
        <div class="upz">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImg(event,'prevEdit')">
          <i class="fas fa-camera"></i><p>JPG, PNG, WEBP — Max 5Mo</p>
          <small id="edn"></small>
        </div>
        <img id="prevEdit" class="photo-preview" src="" alt="">
      </div>
      <div class="fa">
        <a href="admin.php?page=users" class="btn btn-s"><i class="fas fa-times"></i> Annuler</a>
        <button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Sauvegarder</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php /* ═════ ADD USER ═════ */ elseif ($page==='add'): ?>
  <div class="card" style="max-width:700px">
    <h3><i class="fas fa-user-plus"></i> Nouvel utilisateur</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_user">
      <div class="fr2">
        <div class="fg"><label>Prénom <span class="req">*</span></label><input type="text" name="prenom" class="fc" required autofocus></div>
        <div class="fg"><label>Nom <span class="req">*</span></label><input type="text" name="nom" class="fc" required></div>
        <div class="fg"><label>Email <span class="req">*</span></label><input type="email" name="email" class="fc" required></div>
        <div class="fg"><label>Mot de passe <span class="req">*</span></label><input type="password" name="password" class="fc" required minlength="6"></div>
      </div>
      <div class="fg">
        <label>Photo de profil (optionnel)</label>
        <div class="upz">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImg(event,'prevAdd')">
          <i class="fas fa-camera"></i><p>JPG, PNG, WEBP — Max 5Mo</p><small id="apn"></small>
        </div>
        <img id="prevAdd" class="photo-preview" src="" alt="">
      </div>
      <div style="background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.14);border-radius:8px;padding:10px 13px;font-size:12px;color:var(--muted);margin-bottom:12px;">
        <i class="fas fa-info-circle" style="color:var(--accent)"></i>
        L'utilisateur pourra se connecter avec son email et mot de passe, et modifier sa photo depuis son espace personnel.
      </div>
      <div class="fa">
        <a href="admin.php?page=users" class="btn btn-s"><i class="fas fa-times"></i> Annuler</a>
        <button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>

  <?php /* ═════ PROFIL ADMIN ═════ */ elseif ($page==='profil'): ?>
  <div class="card" style="max-width:600px">
    <h3><i class="fas fa-user-cog"></i> Modifier mes informations</h3>
    <div style="text-align:center;margin-bottom:18px;">
      <?php if ($admin['photo']): ?>
        <img src="<?=htmlspecialchars($admin['photo'])?>" id="adminPreview" style="width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid var(--accent);" alt="">
      <?php else: ?>
        <div id="adminIni" style="width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#fff;margin:0 auto;">
          <?=strtoupper(substr($admin['prenom'],0,1).substr($admin['nom'],0,1))?>
        </div>
        <img id="adminPreview" class="photo-preview" src="" style="width:84px;height:84px;margin:8px auto 0;" alt="">
      <?php endif; ?>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_admin">
      <div class="fr2">
        <div class="fg"><label>Prénom <span class="req">*</span></label><input type="text" name="prenom" class="fc" value="<?=htmlspecialchars($admin['prenom'])?>" required></div>
        <div class="fg"><label>Nom <span class="req">*</span></label><input type="text" name="nom" class="fc" value="<?=htmlspecialchars($admin['nom'])?>" required></div>
        <div class="fg"><label>Email <span class="req">*</span></label><input type="email" name="email" class="fc" value="<?=htmlspecialchars($admin['email'])?>" required></div>
        <div class="fg"><label>Nouveau mot de passe <span style="color:var(--muted)">(vide = inchangé)</span></label><input type="password" name="newpass" class="fc" placeholder="Nouveau mot de passe..."></div>
      </div>
      <div class="fg">
        <label>Changer ma photo</label>
        <div class="upz">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" onchange="previewImg(event,'adminPreview')">
          <i class="fas fa-camera"></i><p>JPG, PNG, WEBP — Max 5Mo</p><small id="pp"></small>
        </div>
      </div>
      <div style="background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.14);border-radius:8px;padding:10px 13px;font-size:12px;color:var(--muted);margin-bottom:13px;">
        <i class="fas fa-info-circle" style="color:var(--accent)"></i> Ces informations seront utilisées pour vos prochaines connexions.
      </div>
      <div class="fa">
        <button type="submit" class="btn btn-p"><i class="fas fa-save"></i> Enregistrer les modifications</button>
      </div>
    </form>
  </div>

  <?php endif; ?>
  </div>
</div>

<script>
// Recherche dynamique
function doSearch(val) {
  const q = val.trim().toLowerCase();
  const rows = document.querySelectorAll('.user-row');
  const noRes = document.getElementById('noResults');
  const count = document.getElementById('searchCount');
  let visible = 0;
  document.getElementById('clearBtn').style.display = q ? 'block' : 'none';
  rows.forEach(row => {
    const data = row.getAttribute('data-search');
    const nameCell = row.querySelector('.td-name');
    const emailCell = row.querySelector('.td-email');
    if (!q || data.includes(q)) {
      row.style.display = '';
      visible++;
      nameCell.innerHTML = q ? highlight(nameCell.textContent, q) : nameCell.textContent;
      emailCell.innerHTML = q ? highlight(emailCell.textContent, q) : emailCell.textContent;
    } else {
      row.style.display = 'none';
    }
  });
  noRes.style.display = visible === 0 ? 'block' : 'none';
  count.textContent = q ? `${visible} résultat${visible>1?'s':''} pour "${val.trim()}"` : '';
}
function highlight(text, query) {
  const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')','gi');
  return text.replace(re,'<span class="hl">$1</span>');
}
function clearSearch() {
  document.getElementById('searchInput').value = '';
  doSearch('');
}

// Prévisualisation photo avant upload
function previewImg(e, targetId) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = document.getElementById(targetId);
    img.src = ev.target.result;
    img.style.display = 'block';
    const ini = document.getElementById('adminIni');
    if (ini) ini.style.display = 'none';
  };
  reader.readAsDataURL(file);
  // Afficher le nom du fichier
  const noms = {'prevEdit':'edn','prevAdd':'apn','adminPreview':'pp'};
  const nomId = noms[targetId];
  if (nomId) document.getElementById(nomId).textContent = file.name;
}
</script>
</body>
</html>