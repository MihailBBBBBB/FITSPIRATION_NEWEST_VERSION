<?php

function getFitspirationImagesDirectory(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
}

function describeFitspirationPathState(string $path): string {
    clearstatcache(true, $path);

    $exists = is_dir($path) ? 'yes' : 'no';
    $writable = is_writable($path) ? 'yes' : 'no';
    $permissions = is_dir($path) ? substr(sprintf('%o', fileperms($path)), -4) : 'missing';

    return sprintf('path=%s exists=%s writable=%s perms=%s', $path, $exists, $writable, $permissions);
}

function ensureFitspirationImagesDirectory(): array {
    $directory = getFitspirationImagesDirectory();

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        error_log('Image storage init failed: ' . describeFitspirationPathState($directory));
        return [
            'success' => false,
            'path' => $directory,
            'error' => 'Upload directory could not be created. ' . describeFitspirationPathState($directory),
        ];
    }

    if (!is_writable($directory)) {
        @chmod($directory, 0777);
        clearstatcache(true, $directory);
    }

    if (!is_writable($directory)) {
        error_log('Image storage not writable: ' . describeFitspirationPathState($directory));
        return [
            'success' => false,
            'path' => $directory,
            'error' => 'Upload directory is not writable. ' . describeFitspirationPathState($directory),
        ];
    }

    $probePath = $directory . '.write-test-' . uniqid('', true);
    $probeWriteResult = @file_put_contents($probePath, 'ok');
    if ($probeWriteResult === false) {
        error_log('Image storage write probe failed: ' . describeFitspirationPathState($directory));
        return [
            'success' => false,
            'path' => $directory,
            'error' => 'Upload directory failed write test. ' . describeFitspirationPathState($directory),
        ];
    }

    @unlink($probePath);

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