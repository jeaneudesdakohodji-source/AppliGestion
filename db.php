<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'appli_gestion');

try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border-radius:10px;margin:20px">
        <h2>Erreur de connexion MySQL</h2>
        <p>'.$e->getMessage().'</p>
        <p>Vérifiez que XAMPP est lancé et que la base <b>'.DB_NAME.'</b> existe.</p>
    </div>');
}