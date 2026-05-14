<?php
session_start();
require_once 'db.php';
require_once 'upload_helper.php';

if (!isset($_SESSION['user'])) { header('Location: index.php'); exit; }

// Recharger l'utilisateur depuis la BDD
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
$stmt->execute([$_SESSION['user']['id']]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
$_SESSION['user'] = $user;

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'update_profil') {
        $nom     = trim($_POST['nom']     ?? '');
        $prenom  = trim($_POST['prenom']  ?? '');
        $email   = trim($_POST['email']   ?? '');
        $newpass = trim($_POST['newpass'] ?? '');
        $confpass = trim($_POST['confpass'] ?? '');

        if (!$nom || !$prenom || !$email) {
            $flash = 'error|Tous les champs sont obligatoires.';
        } elseif ($newpass && strlen($newpass) < 6) {
            $flash = 'error|Le mot de passe doit faire au moins 6 caractères.';
        } elseif ($newpass && $newpass !== $confpass) {
            $flash = 'error|Les mots de passe ne correspondent pas.';
        } else {
            $chk = $pdo->prepare("SELECT id FROM utilisateurs WHERE email=? AND id!=?");
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                $flash = 'error|Cet email est déjà utilisé.';
            } else {
                // Upload photo (fonctionne en local ET sur le web)
                $photo = $user['photo'];
                $newPhoto = uploadPhoto($_FILES['photo'] ?? [], 'user_', $photo);
                if ($newPhoto !== false) $photo = $newPhoto;

                if ($newpass) {
                    $hash = password_hash($newpass, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,password=?,photo=? WHERE id=?")
                        ->execute([$nom,$prenom,$email,$hash,$photo,$user['id']]);
                } else {
                    $pdo->prepare("UPDATE utilisateurs SET nom=?,prenom=?,email=?,photo=? WHERE id=?")
                        ->execute([$nom,$prenom,$email,$photo,$user['id']]);
                }

                $s2 = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
                $s2->execute([$user['id']]);
                $user = $s2->fetch();
                $_SESSION['user'] = $user;
                unset($_SESSION['first_login']);
                $flash = 'success|Informations enregistrées avec succès !';
            }
        }
    }

    if ($act === 'logout') { session_destroy(); header('Location: index.php'); exit; }
}

$view = $_GET['view'] ?? 'profil';
$firstLogin = $_SESSION['first_login'] ?? false;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mon Espace – AppliGestion</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#0f172a;--card:#1e293b;--border:#334155;--accent:#34d399;--accent2:#059669;--danger:#f87171;--text:#e2e8f0;--muted:#94a3b8;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
body::before{content:'';position:fixed;top:-150px;right:-150px;width:500px;height:500px;background:radial-gradient(circle,rgba(52,211,153,.08),transparent 70%);border-radius:50%;pointer-events:none;}
body::after{content:'';position:fixed;bottom:-100px;left:-100px;width:400px;height:400px;background:radial-gradient(circle,rgba(5,150,105,.06),transparent 70%);border-radius:50%;pointer-events:none;}

.wrap{width:500px;max-width:98vw;position:relative;z-index:1;}

/* BANNER première connexion */
.first-banner{background:linear-gradient(135deg,rgba(52,211,153,.15),rgba(5,150,105,.1));border:1px solid rgba(52,211,153,.3);border-radius:14px;padding:14px 18px;margin-bottom:16px;font-size:13px;color:var(--accent);display:flex;align-items:center;gap:10px;}
.first-banner i{font-size:18px;flex-shrink:0;}

/* HEADER */
.hcard{background:linear-gradient(135deg,rgba(52,211,153,.15),rgba(5,150,105,.08));border:1px solid rgba(52,211,153,.22);border-radius:18px;padding:28px 24px 22px;margin-bottom:14px;text-align:center;position:relative;}
.logout-btn{position:absolute;top:14px;right:14px;}
.logout-btn button{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--danger);padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px;transition:background .2s;}
.logout-btn button:hover{background:rgba(248,113,113,.2);}

/* Avatar cliquable pour changer la photo rapidement */
.avatar-wrap{position:relative;width:88px;height:88px;margin:0 auto 12px;cursor:pointer;}
.avatar-wrap img,.avatar-wrap .ini{width:88px;height:88px;border-radius:50%;border:3px solid var(--accent);}
.avatar-wrap .ini{background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:700;color:#fff;}
.avatar-wrap img{object-fit:cover;}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;font-size:18px;color:#fff;}
.avatar-wrap:hover .avatar-overlay{display:flex;}
.avatar-input{display:none;}

.hname{font-size:20px;font-weight:700;margin-bottom:3px;}
.hemail{font-size:12px;color:rgba(255,255,255,.5);}
.hbadge{display:inline-block;background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.28);color:var(--accent);font-size:10px;font-weight:600;padding:2px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.7px;margin-top:8px;}

/* TABS */
.tabs{display:flex;gap:8px;margin-bottom:14px;}
.tab{flex:1;padding:10px;border-radius:9px;text-align:center;font-size:13px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--card);color:var(--muted);transition:all .2s;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:7px;}
.tab:hover{color:var(--text);border-color:var(--accent);}
.tab.active{background:rgba(52,211,153,.1);border-color:var(--accent);color:var(--accent);}

/* FLASH */
.flash{padding:11px 14px;border-radius:9px;margin-bottom:14px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;}
.flash.success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--accent);}
.flash.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--danger);}

/* PROFIL VUE */
.pcard{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
.pchead{background:rgba(0,0,0,.2);padding:13px 17px;border-bottom:1px solid var(--border);font-size:13px;font-weight:700;color:var(--accent);display:flex;align-items:center;gap:8px;}
.ig{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px;}
.ii{background:rgba(0,0,0,.2);border-radius:8px;padding:11px 13px;}
.ii label{font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:block;margin-bottom:3px;}
.ii span{font-size:13px;font-weight:500;}
.edit-link{padding:14px;border-top:1px solid var(--border);display:flex;justify-content:center;}
.btn-edit{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;padding:10px 28px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px;transition:transform .15s,box-shadow .2s;}
.btn-edit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(52,211,153,.3);}

/* FORM EDIT */
.fcard{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:22px;}
.fsect{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--accent);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:7px;}
.fsect:first-child{margin-top:0;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:6px;}
.ri{position:relative;}
.ri i.il{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none;}
.fc{width:100%;background:var(--bg);border:1.5px solid var(--border);color:var(--text);padding:10px 38px;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;}
.fc:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(52,211,153,.1);}
.fc::placeholder{color:var(--muted);}
.eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);color:var(--muted);cursor:pointer;font-size:13px;}
.eye:hover{color:var(--accent);}

/* Upload zone */
.upz{border:2px dashed var(--border);border-radius:10px;padding:18px;text-align:center;cursor:pointer;position:relative;transition:border-color .2s,background .2s;}
.upz:hover{border-color:var(--accent);background:rgba(52,211,153,.03);}
.upz input{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.upz i{font-size:22px;color:var(--accent);margin-bottom:7px;display:block;}
.upz p{font-size:12px;color:var(--muted);}
.upz small{font-size:11px;color:var(--accent);}
/* Preview */
.photo-preview{width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);display:none;margin:10px auto 0;}

/* Hint */
.hint{background:rgba(52,211,153,.05);border:1px solid rgba(52,211,153,.14);border-radius:8px;padding:10px 13px;font-size:12px;color:var(--muted);margin-bottom:13px;line-height:1.6;}
.hint i{color:var(--accent);}

/* Bouton save */
.btn-save{width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;padding:13px;border-radius:9px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;transition:transform .15s,box-shadow .2s;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;}
.btn-save:hover{transform:translateY(-1px);box-shadow:0 6px 22px rgba(52,211,153,.28);}
.btn-save:active{transform:translateY(0);}
</style>
</head>
<body>
<div class="wrap">

  <?php if ($firstLogin): ?>
  <div class="first-banner">
    <i class="fas fa-party-horn"></i>
    <span>Bienvenue ! Votre compte a été créé. Complétez votre profil ci-dessous.</span>
  </div>
  <?php endif; ?>

  <!-- HEADER -->
  <div class="hcard">
    <div class="logout-btn">
      <form method="POST"><input type="hidden" name="action" value="logout">
        <button type="submit"><i class="fas fa-sign-out-alt"></i> Déconnexion</button>
      </form>
    </div>

    <!-- Avatar cliquable → quick upload photo -->
    <form method="POST" enctype="multipart/form-data" id="quickPhotoForm">
      <input type="hidden" name="action" value="update_profil">
      <input type="hidden" name="nom" value="<?=htmlspecialchars($user['nom'])?>">
      <input type="hidden" name="prenom" value="<?=htmlspecialchars($user['prenom'])?>">
      <input type="hidden" name="email" value="<?=htmlspecialchars($user['email'])?>">
      <div class="avatar-wrap" onclick="document.getElementById('quickPhotoInput').click()" title="Cliquez pour changer la photo">
        <?php if ($user['photo']): ?>
          <img src="<?= htmlspecialchars($user['photo']) ?>" id="headerAvatar" alt="">
        <?php else: ?>
          <div class="ini" id="headerIni"><?= strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1)) ?></div>
        <?php endif; ?>
        <div class="avatar-overlay"><i class="fas fa-camera"></i></div>
        <input type="file" name="photo" id="quickPhotoInput" class="avatar-input"
               accept="image/jpeg,image/png,image/webp,image/gif"
               onchange="quickUpload(this)">
      </div>
    </form>

    <div class="hname"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></div>
    <div class="hemail"><?= htmlspecialchars($user['email']) ?></div>
    <div class="hbadge"><i class="fas fa-user"></i> Utilisateur</div>
  </div>

  <!-- TABS -->
  <div class="tabs">
    <a href="user.php?view=profil" class="tab <?= $view==='profil'?'active':'' ?>">
      <i class="fas fa-id-card"></i> Mon profil
    </a>
    <a href="user.php?view=modifier" class="tab <?= $view==='modifier'?'active':'' ?>">
      <i class="fas fa-user-edit"></i> Modifier
    </a>
  </div>

  <!-- FLASH -->
  <?php if ($flash): [$t,$m] = explode('|',$flash,2); ?>
  <div class="flash <?= $t ?>"><i class="fas <?= $t==='success'?'fa-check-circle':'fa-exclamation-circle' ?>"></i> <?= htmlspecialchars($m) ?></div>
  <?php endif; ?>

  <!-- VUE PROFIL -->
  <?php if ($view === 'profil'): ?>
  <div class="pcard">
    <div class="pchead"><i class="fas fa-id-card"></i> Mes informations</div>
    <div class="ig">
      <div class="ii"><label>Prénom</label><span><?= htmlspecialchars($user['prenom']?:'—') ?></span></div>
      <div class="ii"><label>Nom</label><span><?= htmlspecialchars($user['nom']?:'—') ?></span></div>
      <div class="ii"><label>Email</label><span style="font-size:12px"><?= htmlspecialchars($user['email']) ?></span></div>
      <div class="ii"><label>Inscrit le</label><span><?= date('d/m/Y', strtotime($user['created_at'])) ?></span></div>
      <div class="ii"><label>Dernière MAJ</label><span><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></span></div>
      <div class="ii"><label>Photo</label><span><?= $user['photo'] ? '✅ Définie' : '❌ Non définie' ?></span></div>
    </div>
    <?php if ($user['photo']): ?>
    <div style="padding:0 16px 16px;text-align:center">
      <img src="<?= htmlspecialchars($user['photo']) ?>" style="width:80px;height:80px;border-radius:12px;object-fit:cover;border:2px solid var(--border);" alt="">
      <div style="font-size:11px;color:var(--muted);margin-top:6px">Cliquez sur la photo en haut pour la changer</div>
    </div>
    <?php endif; ?>
    <div class="edit-link">
      <a href="user.php?view=modifier" class="btn-edit"><i class="fas fa-edit"></i> Modifier mes informations</a>
    </div>
  </div>

  <!-- VUE MODIFIER -->
  <?php else: ?>
  <div class="fcard">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_profil">

      <div class="fsect"><i class="fas fa-user"></i> Informations personnelles</div>
      <div class="fg">
        <label>Prénom <span style="color:var(--danger)">*</span></label>
        <div class="ri"><i class="fas fa-user il"></i>
          <input type="text" name="prenom" class="fc" value="<?= htmlspecialchars($user['prenom']) ?>" required>
        </div>
      </div>
      <div class="fg">
        <label>Nom <span style="color:var(--danger)">*</span></label>
        <div class="ri"><i class="fas fa-id-badge il"></i>
          <input type="text" name="nom" class="fc" value="<?= htmlspecialchars($user['nom']) ?>" required>
        </div>
      </div>

      <!-- Upload photo -->
      <div class="fg">
        <label>Photo de profil</label>
        <?php if ($user['photo']): ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
          <img src="<?=htmlspecialchars($user['photo'])?>" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--accent);">
          <span style="font-size:12px;color:var(--muted)">Photo actuelle — choisissez-en une nouvelle pour la remplacer</span>
        </div>
        <?php endif; ?>
        <div class="upz">
          <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImg(event,'photoPreview')">
          <i class="fas fa-camera"></i>
          <p><?= $user['photo'] ? 'Changer la photo' : 'Ajouter une photo de profil' ?></p>
          <span style="font-size:11px;color:var(--muted)">JPG, PNG, WEBP — Max 5 Mo • Fonctionne en local <b style="color:var(--accent)">et sur le web</b></span>
        </div>
        <img id="photoPreview" class="photo-preview" src="" alt="">
      </div>

      <div class="fsect"><i class="fas fa-key"></i> Informations de connexion</div>
      <div class="hint">
        <i class="fas fa-info-circle"></i>
        Modifiez votre email et/ou mot de passe. Utilisez ces nouvelles informations à la prochaine connexion.
      </div>
      <div class="fg">
        <label>Email <span style="color:var(--danger)">*</span></label>
        <div class="ri"><i class="fas fa-envelope il"></i>
          <input type="email" name="email" class="fc" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
      </div>
      <div class="fg">
        <label>Nouveau mot de passe <span style="color:var(--muted);font-size:10px;text-transform:none;letter-spacing:0">(vide = inchangé)</span></label>
        <div class="ri"><i class="fas fa-lock il"></i>
          <input type="password" name="newpass" id="np" class="fc" placeholder="Nouveau mot de passe...">
          <i class="fas fa-eye eye" onclick="tog('np','e1')" id="e1"></i>
        </div>
      </div>
      <div class="fg">
        <label>Confirmer le mot de passe</label>
        <div class="ri"><i class="fas fa-lock il"></i>
          <input type="password" name="confpass" id="cp" class="fc" placeholder="Répéter le mot de passe...">
          <i class="fas fa-eye eye" onclick="tog('cp','e2')" id="e2"></i>
        </div>
      </div>

      <button type="submit" class="btn-save">
        <i class="fas fa-save"></i> Enregistrer toutes les modifications
      </button>
    </form>
  </div>
  <?php endif; ?>

</div>

<script>
// Toggle mot de passe
function tog(id, ic) {
  const i = document.getElementById(id), e = document.getElementById(ic);
  i.type = i.type === 'password' ? 'text' : 'password';
  e.classList.toggle('fa-eye'); e.classList.toggle('fa-eye-slash');
}

// Prévisualisation avant upload
function previewImg(e, targetId) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const img = document.getElementById(targetId);
    if (img) { img.src = ev.target.result; img.style.display = 'block'; }
  };
  reader.readAsDataURL(file);
}

// Quick upload via avatar header
function quickUpload(input) {
  if (!input.files[0]) return;
  // Prévisualiser immédiatement
  const reader = new FileReader();
  reader.onload = ev => {
    const av = document.getElementById('headerAvatar');
    const ini = document.getElementById('headerIni');
    if (av) av.src = ev.target.result;
    if (!av) {
      const newImg = document.createElement('img');
      newImg.id = 'headerAvatar';
      newImg.className = '';
      newImg.style.cssText = 'width:88px;height:88px;border-radius:50%;border:3px solid var(--accent);object-fit:cover;';
      newImg.src = ev.target.result;
      if (ini) ini.replaceWith(newImg);
    }
    if (ini) ini.style.display = 'none';
  };
  reader.readAsDataURL(input.files[0]);
  // Soumettre automatiquement
  document.getElementById('quickPhotoForm').submit();
}
</script>
</body>
</html>