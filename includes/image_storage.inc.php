<?php

function getFitspirationImagesDirectory(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
}

function ensureFitspirationImagesDirectory(): array {
    $directory = getFitspirationImagesDirectory();

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        return [
            'success' => false,
            'path' => $directory,
            'error' => 'Upload directory could not be created.',
        ];
    }

    if (!is_writable($directory)) {
        return [
            'success' => false,
            'path' => $directory,
            'error' => 'Upload directory is not writable.',
        ];
    }

    return [
        'success' => true,
        'path' => $directory,
    ];
}

function saveFitspirationUploadedImage(array $file, string $prefix, int $maxSizeBytes = 20971520): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . (int) ($file['error'] ?? -1)];
    }

    $imageInfo = getimagesize($file['tmp_name']);
    $allowedTypes = ['image/jpeg', 'image/png'];
    if ($imageInfo === false || !in_array($imageInfo['mime'], $allowedTypes, true)) {
        return ['success' => false, 'error' => 'Invalid image. Must be a .jpg or .png file.'];
    }

    if (($file['size'] ?? 0) > $maxSizeBytes) {
        return ['success' => false, 'error' => 'Image must be under 20MB.'];
    }

    $directoryState = ensureFitspirationImagesDirectory();
    if (!$directoryState['success']) {
        return ['success' => false, 'error' => (string) $directoryState['error']];
    }

    $extension = $imageInfo['mime'] === 'image/png' ? 'png' : 'jpg';
    $fileName = uniqid($prefix, true) . '.' . $extension;
    $filePath = $directoryState['path'] . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'error' => 'Failed to save image.'];
    }

    return ['success' => true, 'path' => $fileName];
}