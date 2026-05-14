<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
if (isset($_SESSION['user']))  { header('Location: user.php');  exit; }

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (!$email || !$pass) {
        $err = 'Veuillez remplir tous les champs.';
    } else {
        // ── 1. VÉRIFIER ADMIN ──
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($pass, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $admin;
            header('Location: admin.php'); exit;
        }

        // ── 2. VÉRIFIER USER ──
        $stmt2 = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt2->execute([$email]);
        $user = $stmt2->fetch();

        if ($user) {
            if (password_verify($pass, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                header('Location: user.php'); exit;
            } else {
                $err = 'Mot de passe incorrect.';
            }
        } else {
            // ── 3. NOUVEL USER → création automatique ──
            if (strlen($pass) < 6) {
                $err = 'Mot de passe trop court (minimum 6 caractères).';
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, password) VALUES ('', '', ?, ?)")
                    ->execute([$email, $hash]);
                $newId = $pdo->lastInsertId();
                $s3 = $pdo->prepare("SELECT * FROM utilisateurs WHERE id=?");
                $s3->execute([$newId]);
                $newUser = $s3->fetch();
                session_regenerate_id(true);
                $_SESSION['user'] = $newUser;
                $_SESSION['first_login'] = true;
                header('Location: user.php?view=modifier'); exit;
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AppliGestion – Connexion</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--bg:#0f172a;--card:#1e293b;--border:#334155;--accent:#38bdf8;--accent2:#818cf8;--danger:#f87171;--text:#e2e8f0;--muted:#94a3b8;}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;padding:20px;}
body::before{content:'';position:fixed;top:-200px;right:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(56,189,248,.1),transparent 70%);border-radius:50%;pointer-events:none;}
body::after{content:'';position:fixed;bottom:-150px;left:-150px;width:500px;height:500px;background:radial-gradient(circle,rgba(129,140,248,.08),transparent 70%);border-radius:50%;pointer-events:none;}

.wrap{display:flex;width:820px;max-width:96vw;border-radius:22px;overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.6);position:relative;z-index:1;animation:up .45s cubic-bezier(.16,1,.3,1);}
@keyframes up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

/* GAUCHE */
.left{width:42%;background:linear-gradient(160deg,#0f172a 0%,#1a2744 100%);padding:48px 34px;display:flex;flex-direction:column;align-items:center;justify-content:center;position:relative;overflow:hidden;}
.left::before{content:'';position:absolute;top:-60px;right:-60px;width:220px;height:220px;border:35px solid rgba(56,189,248,.06);border-radius:50%;}
.left::after{content:'';position:absolute;bottom:-40px;left:-40px;width:180px;height:180px;border:28px solid rgba(129,140,248,.05);border-radius:50%;}
.logo{width:66px;height:66px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:17px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff;margin-bottom:20px;box-shadow:0 8px 28px rgba(56,189,248,.28);position:relative;z-index:1;}
.left h1{font-size:23px;font-weight:700;color:#fff;text-align:center;margin-bottom:8px;position:relative;z-index:1;}
.left p{font-size:13px;color:rgba(255,255,255,.45);text-align:center;line-height:1.65;position:relative;z-index:1;}
.info-box{margin-top:28px;background:rgba(56,189,248,.07);border:1px solid rgba(56,189,248,.16);border-radius:12px;padding:15px 17px;width:100%;position:relative;z-index:1;}
.feat{display:flex;align-items:flex-start;gap:9px;font-size:12px;color:rgba(255,255,255,.52);margin-bottom:10px;line-height:1.5;}
.feat:last-child{margin-bottom:0;}
.feat i{color:var(--accent);width:14px;flex-shrink:0;margin-top:2px;}

/* DROITE */
.right{width:58%;background:var(--card);padding:52px 44px;display:flex;flex-direction:column;justify-content:center;}
.right h2{font-size:24px;font-weight:700;margin-bottom:5px;}
.right .sub{font-size:13px;color:var(--muted);margin-bottom:28px;line-height:1.5;}

.err{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.22);color:var(--danger);padding:11px 14px;border-radius:9px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px;}

.fg{margin-bottom:18px;}
.fg label{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:7px;}
.ri{position:relative;}
.ri i.il{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;pointer-events:none;}
.ri input{width:100%;background:#0f172a;border:1.5px solid var(--border);color:var(--text);padding:12px 40px;border-radius:9px;font-size:14px;font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;}
.ri input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(56,189,248,.12);}
.ri input::placeholder{color:var(--muted);}
.eye{position:absolute;right:13px;top:50%;transform:translateY(-50%);color:var(--muted);cursor:pointer;font-size:14px;transition:color .2s;}
.eye:hover{color:var(--accent);}

.btn{width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;padding:14px;border-radius:9px;font-size:15px;font-weight:700;cursor:pointer;font-family:inherit;transition:transform .15s,box-shadow .2s;display:flex;align-items:center;justify-content:center;gap:9px;margin-top:6px;}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(56,189,248,.3);}
.btn:active{transform:translateY(0);}

.note{margin-top:20px;background:rgba(56,189,248,.05);border:1px solid rgba(56,189,248,.13);border-radius:9px;padding:12px 15px;font-size:12px;color:var(--muted);line-height:1.75;text-align:center;}
.note i{color:var(--accent);}
.note strong{color:var(--text);}

@media(max-width:640px){.left{display:none;}.right{width:100%;padding:36px 24px;}}
</style>
</head>
<body>
<div class="wrap">

  <!-- GAUCHE -->
  <div class="left">
    <div class="logo"><i class="fas fa-users-cog"></i></div>
    <h1>AppliGestion</h1>
    <p>Plateforme de gestion des utilisateurs</p>
    <div class="info-box">
      <div class="feat"><i class="fas fa-shield-alt"></i> <span>Admin ? Entrez vos identifiants administrateur pour accéder au tableau de bord</span></div>
      <div class="feat"><i class="fas fa-user-check"></i> <span>Déjà inscrit ? Connectez-vous avec votre email et mot de passe</span></div>
      <div class="feat"><i class="fas fa-magic"></i> <span>Première visite ? Un compte est créé automatiquement si votre email est inconnu</span></div>
    </div>
  </div>

  <!-- DROITE -->
  <div class="right">
    <h2>Bon retour 👋</h2>
    <p class="sub">Entrez votre email et mot de passe pour accéder à votre espace.<br>Admin et utilisateurs se connectent ici.</p>

    <?php if ($err): ?>
    <div class="err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="fg">
        <label>Adresse e-mail</label>
        <div class="ri">
          <i class="fas fa-envelope il"></i>
          <input type="email" name="email" placeholder="votre@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="fg">
        <label>Mot de passe</label>
        <div class="ri">
          <i class="fas fa-lock il"></i>
          <input type="password" name="password" id="pwd" placeholder="••••••••" required minlength="6">
          <i class="fas fa-eye eye" onclick="togglePwd()" id="eyeIcon"></i>
        </div>
      </div>
      <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Continuer</button>
    </form>

    <div class="note">
      <i class="fas fa-info-circle"></i>
      Si votre email n'est pas encore enregistré, votre compte est créé automatiquement.<br>
      Mot de passe minimum <strong>6 caractères</strong>.
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const p = document.getElementById('pwd');
  const i = document.getElementById('eyeIcon');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.classList.toggle('fa-eye');
  i.classList.toggle('fa-eye-slash');
}
</script>
</body>
</html>