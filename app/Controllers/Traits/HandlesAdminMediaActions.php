<?php

namespace App\Controllers\Traits;

use App\Models\AppSettings;
use App\Models\ScreenMedia;

trait HandlesAdminMediaActions
{
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
