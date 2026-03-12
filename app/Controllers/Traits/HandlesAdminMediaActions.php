<?php

namespace App\Controllers\Traits;

use App\Models\AppSettings;
use App\Models\ScreenMedia;

trait HandlesAdminMediaActions
{
    private function cropAllowedImagePrefixes(): array
    {
        return [
            '/upload/image/',
            '/upload/domanda/image/',
        ];
    }

    private function cropNormalizeRelativePath(string $path): string
    {
        $normalized = '/' . ltrim(str_replace('\\', '/', trim($path)), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?: $normalized;
        return $normalized;
    }

    private function cropIsAllowedImagePath(string $path): bool
    {
        foreach ($this->cropAllowedImagePrefixes() as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    private function cropAbsolutePathFromRelative(string $path): ?string
    {
        $normalized = $this->cropNormalizeRelativePath($path);
        if (!$this->cropIsAllowedImagePath($normalized)) {
            return null;
        }

        if (strpos($normalized, '..') !== false) {
            return null;
        }

        return BASE_PATH . '/public' . $normalized;
    }

    private function cropSupportedExtension(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        return in_array($ext, $allowed, true) ? $ext : '';
    }

    private function cropMimeForExtension(string $ext): string
    {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            return 'image/jpeg';
        }
        if ($ext === 'png') {
            return 'image/png';
        }
        if ($ext === 'webp') {
            return 'image/webp';
        }
        return '';
    }

    private function cropListImageFiles(): array
    {
        $sources = [
            ['dir' => BASE_PATH . '/public/upload/image', 'prefix' => '/upload/image/'],
            ['dir' => BASE_PATH . '/public/upload/domanda/image', 'prefix' => '/upload/domanda/image/'],
        ];

        $result = [];
        $seen = [];
        foreach ($sources as $src) {
            $dir = (string) ($src['dir'] ?? '');
            $prefix = (string) ($src['prefix'] ?? '');
            if ($dir === '' || !is_dir($dir)) {
                continue;
            }

            $files = @scandir($dir);
            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                if (!is_file($fullPath)) {
                    continue;
                }

                $ext = $this->cropSupportedExtension($file);
                if ($ext === '') {
                    continue;
                }

                $rel = $prefix . $file;
                if (isset($seen[$rel])) {
                    continue;
                }
                $seen[$rel] = true;

                $result[] = [
                    'file_path' => $rel,
                    'label' => ltrim($rel, '/'),
                ];
            }
        }

        usort($result, static function (array $a, array $b): int {
            return strcmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $result;
    }

    private function handleAdminSettingsAction(string $action): bool
    {
        switch ($action) {
            case 'settings-get':
                $this->json([
                    'success' => true,
                    'settings' => (new AppSettings())->all()
                ]);
                return true;

            case 'settings-logo-upload':
                if (!isset($_FILES['logo_file']) || !is_array($_FILES['logo_file'])) {
                    $this->json([
                        'success' => false,
                        'error' => 'File logo mancante'
                    ]);
                    return true;
                }

                $file = $_FILES['logo_file'];

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->json([
                        'success' => false,
                        'error' => 'Upload logo non valido'
                    ]);
                    return true;
                }

                $maxSizeBytes = 8 * 1024 * 1024;
                $size = (int) ($file['size'] ?? 0);
                if ($size <= 0 || $size > $maxSizeBytes) {
                    $this->json([
                        'success' => false,
                        'error' => 'Dimensione logo non valida (max 8MB)'
                    ]);
                    return true;
                }

                $tmpName = (string) ($file['tmp_name'] ?? '');
                $detectedMime = @mime_content_type($tmpName) ?: '';
                if (strpos($detectedMime, 'image/') !== 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Il logo deve essere un\'immagine'
                    ]);
                    return true;
                }

                $originalName = (string) ($file['name'] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                if (!in_array($ext, $allowedExt, true)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Formato logo non supportato'
                    ]);
                    return true;
                }

                $uploadDir = BASE_PATH . '/public/upload/image';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Impossibile creare cartella logo'
                    ]);
                    return true;
                }

                $fileName = 'admin-logo-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($tmpName, $destPath)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Errore salvataggio logo'
                    ]);
                    return true;
                }

                $logoPath = '/upload/image/' . $fileName;
                $settingsModel = new AppSettings();
                $settingsModel->saveConfigurazioni(['logo' => $logoPath]);

                $this->json([
                    'success' => true,
                    'logo_path' => $logoPath,
                    'settings' => $settingsModel->all()
                ]);
                return true;

            case 'settings-save':
                $settingsModel = new AppSettings();

                $rawConfig = (string) ($_POST['configurazioni_json'] ?? '{}');
                $decoded = json_decode($rawConfig, true);
                $configurazioni = is_array($decoded) ? $decoded : [];

                $showModuleTags = (int) ($_POST['show_module_tags'] ?? (($configurazioni['show_module_tags'] ?? '1'))) === 1;
                $configurazioni['show_module_tags'] = $showModuleTags ? '1' : '0';

                $settingsModel->saveConfigurazioni($configurazioni);

                $this->json([
                    'success' => true,
                    'settings' => $settingsModel->all()
                ]);
                return true;
        }

        return false;
    }

    private function handleAdminMediaAction(string $action): bool
    {
        switch ($action) {
            case 'media-list':
                $this->json([
                    'success' => true,
                    'media' => (new ScreenMedia())->lista('screen')
                ]);
                return true;

            case 'media-attiva':
                $mediaId = (int) ($_POST['media_id'] ?? 0);
                $attiva = (int) ($_POST['attiva'] ?? 1) === 1;
                if ($mediaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Media non valido'
                    ]);
                    return true;
                }

                $ok = (new ScreenMedia())->impostaAttiva($mediaId, $attiva, 'screen');
                $this->json([
                    'success' => $ok,
                    'media_id' => $mediaId,
                    'attiva' => $attiva,
                    'error' => $ok ? null : 'Media non trovato'
                ]);
                return true;

            case 'media-disattiva':
                $ok = (new ScreenMedia())->disattivaTutte('screen');
                $this->json([
                    'success' => $ok
                ]);
                return true;

            case 'media-elimina':
                $mediaId = (int) ($_POST['media_id'] ?? 0);
                if ($mediaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Media non valido'
                    ]);
                    return true;
                }

                $mediaModel = new ScreenMedia();
                $media = $mediaModel->trova($mediaId, 'screen');

                if (!$media) {
                    $this->json([
                        'success' => false,
                        'error' => 'Media non trovato'
                    ]);
                    return true;
                }

                $ok = $mediaModel->elimina($mediaId, 'screen');
                if ($ok) {
                    $file = BASE_PATH . '/public' . ($media['file_path'] ?? '');
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }

                $this->json([
                    'success' => $ok
                ]);
                return true;
        }

        return false;
    }

    private function handleAdminUploadAction(string $action): bool
    {
        switch ($action) {
            case 'crop-image-list':
                $this->json([
                    'success' => true,
                    'images' => $this->cropListImageFiles(),
                ]);
                return true;

            case 'crop-image-save':
                $sourcePathRaw = (string) ($_POST['source_path'] ?? '');
                $sourcePath = $this->cropNormalizeRelativePath($sourcePathRaw);
                $sourceAbs = $this->cropAbsolutePathFromRelative($sourcePath);
                if ($sourceAbs === null || !is_file($sourceAbs)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Sorgente immagine non valida'
                    ]);
                    return true;
                }

                $ext = $this->cropSupportedExtension($sourcePath);
                if ($ext === '') {
                    $this->json([
                        'success' => false,
                        'error' => 'Formato sorgente non supportato (usa jpg, jpeg, png, webp)'
                    ]);
                    return true;
                }

                if (!isset($_FILES['cropped_image']) || !is_array($_FILES['cropped_image'])) {
                    $this->json([
                        'success' => false,
                        'error' => 'File crop mancante'
                    ]);
                    return true;
                }

                $file = $_FILES['cropped_image'];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->json([
                        'success' => false,
                        'error' => 'Upload crop non valido'
                    ]);
                    return true;
                }

                $tmpName = (string) ($file['tmp_name'] ?? '');
                if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                    $this->json([
                        'success' => false,
                        'error' => 'File crop non valido'
                    ]);
                    return true;
                }

                $maxSizeBytes = 20 * 1024 * 1024;
                $size = (int) ($file['size'] ?? 0);
                if ($size <= 0 || $size > $maxSizeBytes) {
                    $this->json([
                        'success' => false,
                        'error' => 'Dimensione crop non valida (max 20MB)'
                    ]);
                    return true;
                }

                $detectedMime = (string) (@mime_content_type($tmpName) ?: '');
                $expectedMime = $this->cropMimeForExtension($ext);
                if ($expectedMime === '' || stripos($detectedMime, 'image/') !== 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Il file crop deve essere un\'immagine valida'
                    ]);
                    return true;
                }

                if ($detectedMime !== '' && stripos($detectedMime, $expectedMime) !== 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Formato crop non compatibile con il file sorgente'
                    ]);
                    return true;
                }

                $saveMode = trim((string) ($_POST['save_mode'] ?? 'overwrite'));
                if ($saveMode !== 'copy') {
                    $saveMode = 'overwrite';
                }

                $targetRelPath = $sourcePath;
                if ($saveMode === 'copy') {
                    $suffixRaw = trim((string) ($_POST['copy_suffix'] ?? '-169'));
                    $suffixSafe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $suffixRaw) ?: '-169';
                    $suffixSafe = trim((string) $suffixSafe, '-');
                    if ($suffixSafe === '') {
                        $suffixSafe = '169';
                    }
                    $suffixSafe = '-' . $suffixSafe;

                    $dirRel = str_replace('\\', '/', dirname($sourcePath));
                    if ($dirRel === '.' || $dirRel === DIRECTORY_SEPARATOR) {
                        $dirRel = '';
                    }
                    $baseName = pathinfo($sourcePath, PATHINFO_FILENAME);
                    $targetRelPath = ($dirRel !== '' ? $dirRel . '/' : '') . $baseName . $suffixSafe . '.' . $ext;
                    $targetRelPath = $this->cropNormalizeRelativePath($targetRelPath);
                }

                $targetAbs = $this->cropAbsolutePathFromRelative($targetRelPath);
                if ($targetAbs === null) {
                    $this->json([
                        'success' => false,
                        'error' => 'Percorso destinazione non valido'
                    ]);
                    return true;
                }

                $targetDir = dirname($targetAbs);
                if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Impossibile creare cartella destinazione'
                    ]);
                    return true;
                }

                if (!move_uploaded_file($tmpName, $targetAbs)) {
                    $raw = @file_get_contents($tmpName);
                    if (!is_string($raw) || $raw === '' || @file_put_contents($targetAbs, $raw) === false) {
                        $this->json([
                            'success' => false,
                            'error' => 'Errore salvataggio file crop'
                        ]);
                        return true;
                    }
                }

                $this->json([
                    'success' => true,
                    'source_path' => $sourcePath,
                    'output_path' => $targetRelPath,
                    'save_mode' => $saveMode,
                ]);
                return true;

            case 'media-upload':
                if (!isset($_FILES['immagine']) || !is_array($_FILES['immagine'])) {
                    $this->json([
                        'success' => false,
                        'error' => 'File media mancante'
                    ]);
                    return true;
                }

                $file = $_FILES['immagine'];

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->json([
                        'success' => false,
                        'error' => 'Upload non valido'
                    ]);
                    return true;
                }

                $maxSizeBytes = 12 * 1024 * 1024;
                $size = (int) ($file['size'] ?? 0);
                if ($size <= 0 || $size > $maxSizeBytes) {
                    $this->json([
                        'success' => false,
                        'error' => 'Dimensione file non valida (max 12MB)'
                    ]);
                    return true;
                }

                $tmpName = $file['tmp_name'] ?? '';
                $detectedMime = @mime_content_type($tmpName) ?: '';
                $isImage = strpos((string) $detectedMime, 'image/') === 0;
                $isAudio = strpos((string) $detectedMime, 'audio/') === 0;

                if (!$isImage && !$isAudio) {
                    $this->json([
                        'success' => false,
                        'error' => 'Formato non supportato: carica immagine o audio'
                    ]);
                    return true;
                }

                $originalName = (string) ($file['name'] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedAudioExt = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];
                $allowedExt = $isImage ? $allowedImageExt : $allowedAudioExt;
                if (!in_array($ext, $allowedExt, true)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Estensione non consentita'
                    ]);
                    return true;
                }

                $subDir = $isImage ? 'image' : 'audio';
                $uploadDir = BASE_PATH . '/public/upload/' . $subDir;
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Impossibile creare cartella upload'
                    ]);
                    return true;
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
                $safeBase = trim((string) $safeBase, '-');
                if ($safeBase === '') {
                    $safeBase = 'media';
                }

                $fileName = $safeBase . '-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($tmpName, $destPath)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Errore salvataggio file'
                    ]);
                    return true;
                }

                $titolo = trim((string) ($_POST['titolo'] ?? ''));
                if ($titolo === '') {
                    $titolo = pathinfo($originalName, PATHINFO_FILENAME) ?: ($isImage ? 'Immagine' : 'Audio');
                }

                $filePath = '/upload/' . $subDir . '/' . $fileName;
                $tipoFile = $isImage ? 'image' : 'audio';
                $id = (new ScreenMedia())->crea($titolo, $filePath, 'screen', $tipoFile);

                $this->json([
                    'success' => true,
                    'media_id' => $id,
                    'file_path' => $filePath,
                    'tipo_file' => $tipoFile
                ]);
                return true;

            case 'domanda-media-list':
                $mediaModel = new ScreenMedia();
                $listDomanda = $mediaModel->lista('domanda');

                $merged = [];
                $seen = [];

                foreach ($listDomanda as $row) {
                    $filePath = (string) ($row['file_path'] ?? '');
                    $tipoFile = (string) ($row['tipo_file'] ?? '');
                    $isDomandaAudio = $tipoFile === 'audio' && strpos($filePath, '/upload/domanda/audio/') === 0;
                    $isDomandaImage = $tipoFile === 'image' && strpos($filePath, '/upload/domanda/image/') === 0;
                    if (!$isDomandaAudio && !$isDomandaImage) {
                        continue;
                    }
                    $key = $tipoFile . '|' . $filePath;
                    if ($filePath === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $merged[] = $row;
                }

                $scanSources = [
                    ['dir' => BASE_PATH . '/public/upload/domanda/audio', 'urlPrefix' => '/upload/domanda/audio/', 'tipo' => 'audio'],
                    ['dir' => BASE_PATH . '/public/upload/domanda/image', 'urlPrefix' => '/upload/domanda/image/', 'tipo' => 'image'],
                ];

                foreach ($scanSources as $src) {
                    $dir = (string) ($src['dir'] ?? '');
                    $urlPrefix = (string) ($src['urlPrefix'] ?? '');
                    $tipo = (string) ($src['tipo'] ?? '');

                    if ($dir === '' || !is_dir($dir)) {
                        continue;
                    }

                    $files = @scandir($dir);
                    if (!is_array($files)) {
                        continue;
                    }

                    foreach ($files as $file) {
                        if ($file === '.' || $file === '..') {
                            continue;
                        }

                        $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
                        if (!is_file($fullPath)) {
                            continue;
                        }

                        $filePath = $urlPrefix . $file;
                        $key = $tipo . '|' . $filePath;
                        if (isset($seen[$key])) {
                            continue;
                        }

                        $seen[$key] = true;
                        $merged[] = [
                            'id' => 0,
                            'titolo' => pathinfo($file, PATHINFO_FILENAME),
                            'file_path' => $filePath,
                            'contesto' => 'domanda',
                            'tipo_file' => $tipo,
                            'attiva' => 0,
                            'creato_il' => null,
                        ];
                    }
                }

                $this->json([
                    'success' => true,
                    'media' => $merged
                ]);
                return true;

            case 'domanda-media-upload':
                if (!isset($_FILES['media_file']) || !is_array($_FILES['media_file'])) {
                    $this->json([
                        'success' => false,
                        'error' => 'File media mancante'
                    ]);
                    return true;
                }

                $file = $_FILES['media_file'];

                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $this->json([
                        'success' => false,
                        'error' => 'Upload non valido'
                    ]);
                    return true;
                }

                $maxSizeBytes = 16 * 1024 * 1024;
                $size = (int) ($file['size'] ?? 0);
                if ($size <= 0 || $size > $maxSizeBytes) {
                    $this->json([
                        'success' => false,
                        'error' => 'Dimensione file non valida (max 16MB)'
                    ]);
                    return true;
                }

                $tmpName = (string) ($file['tmp_name'] ?? '');
                $detectedMime = (string) (@mime_content_type($tmpName) ?: '');
                $isImage = strpos($detectedMime, 'image/') === 0;
                $isAudio = strpos($detectedMime, 'audio/') === 0;

                if (!$isImage && !$isAudio) {
                    $this->json([
                        'success' => false,
                        'error' => 'Formato non supportato: carica immagine o audio'
                    ]);
                    return true;
                }

                $originalName = (string) ($file['name'] ?? '');
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                $allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $allowedAudioExt = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'webm'];
                $allowedExt = $isImage ? $allowedImageExt : $allowedAudioExt;

                if (!in_array($ext, $allowedExt, true)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Estensione non consentita per questo tipo file'
                    ]);
                    return true;
                }

                $subDir = $isImage ? 'image' : 'audio';
                $uploadDir = BASE_PATH . '/public/upload/domanda/' . $subDir;
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Impossibile creare cartella upload'
                    ]);
                    return true;
                }

                $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
                $safeBase = trim((string) $safeBase, '-');
                if ($safeBase === '') {
                    $safeBase = 'media-domanda';
                }

                $fileName = $safeBase . '-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                $destPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($tmpName, $destPath)) {
                    $this->json([
                        'success' => false,
                        'error' => 'Errore salvataggio file'
                    ]);
                    return true;
                }

                $titolo = trim((string) ($_POST['titolo'] ?? ''));
                if ($titolo === '') {
                    $titolo = pathinfo($originalName, PATHINFO_FILENAME) ?: ($isImage ? 'Immagine domanda' : 'Audio domanda');
                }

                $filePath = '/upload/domanda/' . $subDir . '/' . $fileName;
                $tipoFile = $isImage ? 'image' : 'audio';
                $id = (new ScreenMedia())->crea($titolo, $filePath, 'domanda', $tipoFile);

                $this->json([
                    'success' => true,
                    'media_id' => $id,
                    'contesto' => 'domanda',
                    'tipo_file' => $tipoFile,
                    'file_path' => $filePath
                ]);
                return true;

            case 'domanda-media-elimina':
                $mediaId = (int) ($_POST['media_id'] ?? 0);
                if ($mediaId <= 0) {
                    $this->json([
                        'success' => false,
                        'error' => 'Media non valido'
                    ]);
                    return true;
                }

                $mediaModel = new ScreenMedia();
                $media = $mediaModel->trova($mediaId, 'domanda');

                if (!$media) {
                    $this->json([
                        'success' => false,
                        'error' => 'Media non trovato'
                    ]);
                    return true;
                }

                $ok = $mediaModel->elimina($mediaId, 'domanda');
                if ($ok) {
                    $file = BASE_PATH . '/public' . ($media['file_path'] ?? '');
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }

                $this->json([
                    'success' => $ok
                ]);
                return true;
        }

        return false;
    }
}
