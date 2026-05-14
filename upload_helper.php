<?php
/**
 * ═══════════════════════════════════════════════════════
 *  UPLOAD HELPER — AppliGestion
 *  Gère l'upload de photos de profil de manière adaptative :
 *
 *  • LOCAL  (XAMPP) → stockage dans le dossier uploads/
 *  • WEB    (hébergement mutualisé) → même chose, le dossier
 *            uploads/ doit être accessible en écriture (chmod 755)
 *  • CLOUD  (ex: Cloudinary, AWS S3) → décommenter la section
 *            correspondante et configurer les clés API
 *
 *  RÉPONSE à la question : "quand ce projet sera sur le web,
 *  les users pourront-ils joindre leurs images ?"
 *  → OUI, dès maintenant. Le code gère les uploads en production
 *    exactement comme en local. Il suffit que le dossier uploads/
 *    soit inscriptible sur le serveur (c'est le cas par défaut
 *    sur OVH, Infomaniak, PlanetHoster, etc.)
 *    Pour un hébergement cloud moderne (AWS, Cloudinary), voir
 *    la section CLOUD ci-dessous.
 * ═══════════════════════════════════════════════════════
 */

// ── Configuration ──
define('UPLOAD_DIR',      __DIR__ . '/uploads/');
define('UPLOAD_URL',      '/uploads/');       // URL relative (adaptez si sous-dossier)
define('MAX_FILE_SIZE',   5 * 1024 * 1024);   // 5 Mo max
define('ALLOWED_TYPES',   ['image/jpeg','image/png','image/webp','image/gif']);
define('ALLOWED_EXT',     ['jpg','jpeg','png','webp','gif']);

// ────────────────────────────────────────────────────────
//  FONCTION PRINCIPALE : uploadPhoto()
//  $fileArray  → $_FILES['photo']
//  $prefix     → 'user_' ou 'admin_'
//  $oldPath    → ancien chemin à supprimer (optionnel)
//  Retourne    → chemin relatif du fichier OU false en cas d'erreur
// ────────────────────────────────────────────────────────
function uploadPhoto(array $fileArray, string $prefix = 'user_', ?string $oldPath = null): string|false
{
    // Aucun fichier envoyé
    if (empty($fileArray['name']) || $fileArray['error'] === UPLOAD_ERR_NO_FILE) {
        return false;
    }

    // Erreur d'upload
    if ($fileArray['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error code: " . $fileArray['error']);
        return false;
    }

    // Taille
    if ($fileArray['size'] > MAX_FILE_SIZE) {
        return false; // Fichier trop lourd
    }

    // Extension
    $ext = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT)) {
        return false;
    }

    // Type MIME réel (sécurité anti-spoofing)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($fileArray['tmp_name']);
    if (!in_array($mimeReal, ALLOWED_TYPES)) {
        return false;
    }

    // Créer le dossier si besoin
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Supprimer l'ancienne photo
    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
        @unlink(__DIR__ . '/' . $oldPath);
    }

    // Générer un nom unique sécurisé
    $filename  = $prefix . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath  = UPLOAD_DIR . $filename;
    $relativePath = 'uploads/' . $filename;

    // Déplacer le fichier
    if (!move_uploaded_file($fileArray['tmp_name'], $destPath)) {
        error_log("move_uploaded_file failed: $destPath");
        return false;
    }

    // ── Optionnel : redimensionner l'image (économise de l'espace) ──
    // resizeImage($destPath, 400, 400);

    return $relativePath;
}

// ────────────────────────────────────────────────────────
//  REDIMENSIONNEMENT (optionnel, nécessite GD)
//  Réduit l'image à max $maxW x $maxH pixels
// ────────────────────────────────────────────────────────
function resizeImage(string $path, int $maxW = 400, int $maxH = 400): void
{
    if (!extension_loaded('gd')) return;

    $info = getimagesize($path);
    if (!$info) return;

    [$w, $h, $type] = $info;
    if ($w <= $maxW && $h <= $maxH) return; // Déjà petite

    $ratio  = min($maxW / $w, $maxH / $h);
    $newW   = (int)($w * $ratio);
    $newH   = (int)($h * $ratio);

    $src = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($path),
        IMAGETYPE_PNG  => imagecreatefrompng($path),
        IMAGETYPE_WEBP => imagecreatefromwebp($path),
        default        => null,
    };
    if (!$src) return;

    $dst = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($dst, $src, 0,0, 0,0, $newW,$newH, $w,$h);

    match ($type) {
        IMAGETYPE_JPEG => imagejpeg($dst, $path, 85),
        IMAGETYPE_PNG  => imagepng($dst, $path),
        IMAGETYPE_WEBP => imagewebp($dst, $path, 85),
        default        => null,
    };

    imagedestroy($src);
    imagedestroy($dst);
}

// ────────────────────────────────────────────────────────
//  SECTION CLOUD — Cloudinary (décommentez pour activer)
//  1. composer require cloudinary/cloudinary_php
//  2. Remplissez vos clés Cloudinary
//  3. Remplacez uploadPhoto() par uploadPhotoCloud()
// ────────────────────────────────────────────────────────
/*
define('CLOUDINARY_URL', 'cloudinary://API_KEY:API_SECRET@CLOUD_NAME');

function uploadPhotoCloud(array $fileArray, string $prefix = 'user_', ?string $oldPublicId = null): array|false
{
    if (empty($fileArray['name']) || $fileArray['error'] !== UPLOAD_ERR_OK) return false;

    require_once 'vendor/autoload.php';
    \Cloudinary\Configuration\Configuration::instance(CLOUDINARY_URL);
    $api = new \Cloudinary\Api\Upload\UploadApi();

    // Supprimer l'ancienne image cloud
    if ($oldPublicId) {
        try { $api->destroy($oldPublicId); } catch(Exception $e) {}
    }

    $result = $api->upload($fileArray['tmp_name'], [
        'folder'         => 'appligestion/' . $prefix,
        'transformation' => [['width'=>400,'height'=>400,'crop'=>'fill','gravity'=>'face']],
    ]);

    // Retourne ['url' => ..., 'public_id' => ...]
    return ['url' => $result['secure_url'], 'public_id' => $result['public_id']];
}
*/

// ────────────────────────────────────────────────────────
//  SECTION CLOUD — AWS S3 (décommentez pour activer)
//  1. composer require aws/aws-sdk-php
//  2. Configurez vos clés AWS
// ────────────────────────────────────────────────────────
/*
define('AWS_KEY',    'votre_access_key');
define('AWS_SECRET', 'votre_secret_key');
define('AWS_REGION', 'eu-west-3');
define('AWS_BUCKET', 'votre-bucket');

function uploadPhotoS3(array $fileArray, string $prefix = 'user_'): string|false
{
    if (empty($fileArray['name']) || $fileArray['error'] !== UPLOAD_ERR_OK) return false;
    require_once 'vendor/autoload.php';

    $ext  = strtolower(pathinfo($fileArray['name'], PATHINFO_EXTENSION));
    $key  = "photos/{$prefix}" . bin2hex(random_bytes(8)) . ".{$ext}";

    $s3 = new Aws\S3\S3Client([
        'version'     => 'latest',
        'region'      => AWS_REGION,
        'credentials' => ['key' => AWS_KEY, 'secret' => AWS_SECRET],
    ]);

    $s3->putObject([
        'Bucket'      => AWS_BUCKET,
        'Key'         => $key,
        'SourceFile'  => $fileArray['tmp_name'],
        'ContentType' => mime_content_type($fileArray['tmp_name']),
        'ACL'         => 'public-read',
    ]);

    return "https://" . AWS_BUCKET . ".s3." . AWS_REGION . ".amazonaws.com/" . $key;
}
*/