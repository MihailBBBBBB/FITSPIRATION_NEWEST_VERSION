<?php

function getFitspirationSharedDefaultAvatarFileName(): string {
    return 'default_avatar.svg';
}

function isFitspirationSharedDefaultAvatar(?string $image): bool {
    return trim((string) $image) === getFitspirationSharedDefaultAvatarFileName();
}

function getFitspirationImagesDirectory(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
}

function getFitspirationImagePublicPath(string $image): ?string {
    $normalized = str_replace('\\', '/', trim($image));
    if ($normalized === '') {
        return null;
    }

    if (preg_match('#^(https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:')) {
        return $normalized;
    }

    if (str_starts_with($normalized, '../images/')) {
        return '/images/' . ltrim(substr($normalized, strlen('../images/')), '/');
    }

    if (str_starts_with($normalized, './images/')) {
        return '/images/' . ltrim(substr($normalized, strlen('./images/')), '/');
    }

    if (str_starts_with($normalized, '/images/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, 'images/')) {
        return '/' . $normalized;
    }

    return '/images/' . ltrim($normalized, '/');
}

function buildFitspirationDefaultAvatarDataUrl(string $seed = ''): string {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-label="Default profile avatar">'
        . '<rect width="160" height="160" rx="80" fill="#e5e7eb"/>'
        . '<circle cx="80" cy="58" r="24" fill="#9ca3af"/>'
        . '<path d="M34 136c8-24 28-38 46-38s38 14 46 38" fill="#9ca3af"/>'
        . '</svg>';

    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function buildFitspirationAvatarUrl(?string $image, string $seed = '', string $relativePrefix = '../images/'): string {
    $resolvedImage = buildFitspirationImageUrl($image, $relativePrefix, '');
    if ($resolvedImage !== '') {
        return $resolvedImage;
    }

    return buildFitspirationDefaultAvatarDataUrl($seed);
}

function buildFitspirationImageUrl(?string $image, string $relativePrefix = '../images/', string $default = '../images/no_image.jpg'): string {
    $image = trim((string) $image);
    if ($image === '') {
        return $default;
    }

    $normalized = str_replace('\\', '/', $image);

    if (preg_match('#^(https?:)?//#i', $normalized) || str_starts_with($normalized, 'data:')) {
        return $normalized;
    }

    $publicPath = getFitspirationImagePublicPath($normalized);
    if ($publicPath === null) {
        return $default;
    }

    if (preg_match('#^(https?:)?//#i', $publicPath) || str_starts_with($publicPath, 'data:')) {
        return $publicPath;
    }

    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
    if (!is_file($absolutePath)) {
        return $default;
    }

    if (str_starts_with($publicPath, '/images/')) {
        return $relativePrefix . ltrim(substr($publicPath, strlen('/images/')), '/');
    }

    return $default;
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