<?php

namespace App\Controllers\Api\Traits;

trait MediaUploadTrait
{
    /**
     * Ritorna l'array $_FILES[$field] se valido.
     * Se non valido, risponde in JSON e ritorna null.
     */
    private function requireUploadedImage(string $field): ?array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            $this->json([
                'success' => false,
                'error' => 'File immagine mancante'
            ]);
            return null;
        }

        $file = $_FILES[$field];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json([
                'success' => false,
                'error' => 'Upload non valido'
            ]);
            return null;
        }

        $maxSizeBytes = 8 * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSizeBytes) {
            $this->json([
                'success' => false,
                'error' => 'Dimensione file non valida (max 8MB)'
            ]);
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $detectedMime = @mime_content_type($tmpName) ?: '';
        if (strpos($detectedMime, 'image/') !== 0) {
            $this->json([
                'success' => false,
                'error' => "Formato non supportato: carica un'immagine"
            ]);
            return null;
        }

        $originalName = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExt, true)) {
            $this->json([
                'success' => false,
                'error' => 'Estensione non consentita'
            ]);
            return null;
        }

        return $file;
    }
}