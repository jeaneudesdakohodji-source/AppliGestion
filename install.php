<?php
/**
 * APPLIGESTION — Script d'installation
 * Exécutez ce fichier UNE SEULE FOIS : http://localhost/appligestion/install.php
 */
require_once 'db.php';

$queries = [
// ── TABLE ADMIN ──
"CREATE TABLE IF NOT EXISTS admin (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL DEFAULT '',
    prenom     VARCHAR(100) NOT NULL DEFAULT '',
    email      VARCHAR(180) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    photo      VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)",

// ── TABLE UTILISATEURS ──
"CREATE TABLE IF NOT EXISTS utilisateurs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nom        VARCHAR(100) NOT NULL DEFAULT '',
    prenom     VARCHAR(100) NOT NULL DEFAULT '',
    email      VARCHAR(180) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    photo      VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)",
];

$errors = [];
foreach ($queries as $q) {
    try { $pdo->exec($q); } catch (Exception $e) { $errors[] = $e->getMessage(); }
}

// Créer le dossier uploads
if (!is_dir('uploads')) mkdir('uploads', 0755, true);

// Admin par défaut
$nb = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
if ($nb == 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admin (nom, prenom, email, password) VALUES (?,?,?,?)")
        ->execute(['Admin', 'Super', 'admin@appli.com', $hash]);
}

// User de démo
$nu = $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
if ($nu == 0) {
    $hash2 = password_hash('user123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO utilisateurs (nom, prenom, email, password) VALUES (?,?,?,?)")
        ->execute(['Dupont', 'Jean', 'jean@user.com', $hash2]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Installation AppliGestion</title>
<style>
body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
.box{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;max-width:560px;width:90%;}
h1{color:#38bdf8;margin-bottom:20px;font-size:22px;}
.ok{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:#34d399;padding:10px 14px;border-radius:8px;margin:8px 0;font-size:13px;}
.er{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#f87171;padding:10px 14px;border-radius:8px;margin:8px 0;font-size:13px;}
.btn{display:inline-block;margin-top:22px;padding:12px 28px;background:linear-gradient(135deg,#38bdf8,#818cf8);color:#fff;border-radius:9px;text-decoration:none;font-weight:700;font-size:15px;}
</style>
</head>
<body>
<div class="box">
  <h1>🚀 AppliGestion — Installation</h1>
  <?php if (empty($errors)): ?>
    <div class="ok">✅ Tables créées avec succès</div>
    <div class="ok">✅ Dossier <b>uploads/</b> prêt</div>
  <?php else: ?>
    <?php foreach($errors as $e): ?><div class="er">⚠️ <?=htmlspecialchars($e)?></div><?php endforeach; ?>
  <?php endif; ?>
  <br>
  <div class="ok">👑 Admin : <b>admin@appli.com</b> / <b>admin123</b></div>
  <div class="ok">👤 User démo : <b>jean@user.com</b> / <b>user123</b></div>
  <br>
  <a href="index.php" class="btn">→ Accéder à la connexion</a>
</div>
</body>
</html>