<?php

function ensureVisualSimilarityTable(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS pin_visual_signatures (
            pin_id INT NOT NULL PRIMARY KEY,
            image_hash VARCHAR(64) NOT NULL,
            signature_json MEDIUMTEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_visual_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

function visualSimilarityClamp(float $value): float {
    if ($value < 0.0) {
        return 0.0;
    }
    if ($value > 1.0) {
        return 1.0;
    }
    return $value;
}

function visualSimilarityRgbToHsv(int $r, int $g, int $b): array {
    $rf = $r / 255;
    $gf = $g / 255;
    $bf = $b / 255;

    $max = max($rf, $gf, $bf);
    $min = min($rf, $gf, $bf);
    $delta = $max - $min;

    $h = 0.0;
    if ($delta > 0) {
        if ($max === $rf) {
            $h = 60.0 * fmod((($gf - $bf) / $delta), 6.0);
        } elseif ($max === $gf) {
            $h = 60.0 * ((($bf - $rf) / $delta) + 2.0);
        } else {
            $h = 60.0 * ((($rf - $gf) / $delta) + 4.0);
        }
    }

    if ($h < 0) {
        $h += 360.0;
    }

    $s = $max <= 0 ? 0.0 : ($delta / $max);
    $v = $max;

    return [$h, $s, $v];
}

function histogramIoU12(array $left, array $right): float {
    if (count($left) !== 12 || count($right) !== 12) {
        return 0.0;
    }

    $intersection = 0.0;
    $union = 0.0;
    for ($i = 0; $i < 12; $i++) {
        $a = (float) ($left[$i] ?? 0.0);
        $b = (float) ($right[$i] ?? 0.0);
        $intersection += min($a, $b);
        $union += max($a, $b);
    }

    return $union > 0 ? ($intersection / $union) : 0.0;
}

function normalizeHistogram12(array $hist): array {
    $sum = array_sum($hist);
    if ($sum <= 0) {
        return array_fill(0, 12, 0.0);
    }

    $out = [];
    for ($i = 0; $i < 12; $i++) {
        $out[$i] = ((float) ($hist[$i] ?? 0.0)) / $sum;
    }
    return $out;
}

function buildImageVisualSignature(string $absolutePath): ?array {
    if (!is_file($absolutePath) || !is_readable($absolutePath)) {
        return null;
    }

    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $raw = @file_get_contents($absolutePath);
    if ($raw === false) {
        return null;
    }

    $img = @imagecreatefromstring($raw);
    if (!$img) {
        return null;
    }

    $width = imagesx($img);
    $height = imagesy($img);
    if ($width < 2 || $height < 2) {
        imagedestroy($img);
        return null;
    }

    $gridX = 16;
    $gridY = 16;
    $samples = 0;

    $hueBins = array_fill(0, 12, 0.0);
    $centerHueBins = array_fill(0, 12, 0.0);
    $lowerHueBins = array_fill(0, 12, 0.0);
    $accentHueBins = array_fill(0, 12, 0.0);
    $accentWeightTotal = 0.0;
    $sumR = 0.0;
    $sumG = 0.0;
    $sumB = 0.0;
    $sumS = 0.0;
    $sumV = 0.0;

    $lumGrid = [];

    for ($gy = 0; $gy < $gridY; $gy++) {
        $lumGrid[$gy] = [];
        $y = (int) round(($gy + 0.5) * ($height / $gridY));
        $y = max(0, min($height - 1, $y));

        for ($gx = 0; $gx < $gridX; $gx++) {
            $x = (int) round(($gx + 0.5) * ($width / $gridX));
            $x = max(0, min($width - 1, $x));

            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            [$h, $s, $v] = visualSimilarityRgbToHsv($r, $g, $b);
            $hueBin = (int) floor($h / 30.0) % 12;
            $hueWeight = (0.3 + 0.7 * $s) * (0.35 + 0.65 * $v);
            $hueBins[$hueBin] += $hueWeight;

            $nx = ($gx + 0.5) / $gridX;
            $ny = ($gy + 0.5) / $gridY;

            if ($nx >= 0.3 && $nx <= 0.7 && $ny >= 0.28 && $ny <= 0.92) {
                $centerHueBins[$hueBin] += $hueWeight;
            }
            if ($ny >= 0.46) {
                $lowerHueBins[$hueBin] += $hueWeight;
            }

            // Accent colors isolate likely clothing regions from mannequin/background.
            if ($s >= 0.18 && $v <= 0.92) {
                $accentWeight = (0.4 + 0.6 * $s) * (0.35 + 0.65 * (1.0 - abs($v - 0.5)));
                $accentHueBins[$hueBin] += $accentWeight;
                $accentWeightTotal += $accentWeight;
            }

            $sumR += $r / 255;
            $sumG += $g / 255;
            $sumB += $b / 255;
            $sumS += $s;
            $sumV += $v;

            $lumGrid[$gy][$gx] = (0.2126 * $r + 0.7152 * $g + 0.0722 * $b) / 255;
            $samples++;
        }
    }

    imagedestroy($img);

    if ($samples <= 0) {
        return null;
    }

    $hueBins = normalizeHistogram12($hueBins);
    $centerHueBins = normalizeHistogram12($centerHueBins);
    $lowerHueBins = normalizeHistogram12($lowerHueBins);

    $accentHueBins = normalizeHistogram12($accentHueBins);

    $edgeCount = 0;
    $edgeTotal = 0;
    for ($gy = 0; $gy < $gridY; $gy++) {
        for ($gx = 0; $gx < $gridX; $gx++) {
            $current = $lumGrid[$gy][$gx];
            if ($gx > 0) {
                $edgeTotal++;
                if (abs($current - $lumGrid[$gy][$gx - 1]) > 0.14) {
                    $edgeCount++;
                }
            }
            if ($gy > 0) {
                $edgeTotal++;
                if (abs($current - $lumGrid[$gy - 1][$gx]) > 0.14) {
                    $edgeCount++;
                }
            }
        }
    }

    $edgeDensity = $edgeTotal > 0 ? ($edgeCount / $edgeTotal) : 0.0;

    return [
        'v' => 3,
        'h' => array_map(static function($n) { return round((float) $n, 6); }, $hueBins),
        'hc' => array_map(static function($n) { return round((float) $n, 6); }, $centerHueBins),
        'hl' => array_map(static function($n) { return round((float) $n, 6); }, $lowerHueBins),
        'ha' => array_map(static function($n) { return round((float) $n, 6); }, $accentHueBins),
        'ar' => round($accentWeightTotal / max(1, $samples), 6),
        'rgb' => [
            round($sumR / $samples, 6),
            round($sumG / $samples, 6),
            round($sumB / $samples, 6),
        ],
        'sv' => [
            round($sumS / $samples, 6),
            round($sumV / $samples, 6),
        ],
        'edge' => round($edgeDensity, 6),
        'ratio' => round($width / max(1, $height), 6),
    ];
}

function fetchPinImageFilename(PDO $pdo, int $pinId): ?string {
    $stmt = $pdo->prepare('SELECT img FROM pins WHERE id = ? LIMIT 1');
    $stmt->execute([$pinId]);
    $img = $stmt->fetchColumn();
    if (!$img) {
        return null;
    }
    return trim((string) $img);
}

function normalizePinImageFilename(?string $imgFile): string {
    $value = trim((string) $imgFile);
    if ($value === '') {
        return 'no_image.jpg';
    }

    $value = str_replace('\\', '/', $value);

    $prefixes = [
        '../images/',
        './images/',
        '/images/',
        'images/',
        '/FITSPIRATION/images/',
    ];

    foreach ($prefixes as $prefix) {
        if (stripos($value, $prefix) === 0) {
            $value = substr($value, strlen($prefix));
            break;
        }
    }

    $value = ltrim($value, '/');
    if ($value === '') {
        return 'no_image.jpg';
    }

    return $value;
}

function resolvePinImageAbsolutePath(string $normalizedFile): string {
    $imagesRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR;
    $candidate = $imagesRoot . str_replace('/', DIRECTORY_SEPARATOR, $normalizedFile);
    if (is_file($candidate)) {
        return $candidate;
    }

    return $imagesRoot . 'no_image.jpg';
}

function isPlaceholderImageFilename(string $normalizedFile): bool {
    $base = strtolower(basename(str_replace('\\', '/', $normalizedFile)));
    return in_array($base, ['no_image.jpg', 'no_image.jpeg', 'no_image.png'], true);
}

function tokenizeSimilarityText(string $text): array {
    $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopWords = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'look', 'style', 'fashion',
        'outfit', 'pin', 'new', 'remix', 'fit', 'admin'
    ];

    $result = [];
    foreach ($tokens as $token) {
        if (strlen($token) < 3 || in_array($token, $stopWords, true)) {
            continue;
        }
        $result[$token] = true;
        if (count($result) >= 10) {
            break;
        }
    }

    return array_keys($result);
}

function computeTitleTokenSimilarity(string $a, string $b): float {
    $tokensA = tokenizeSimilarityText($a);
    $tokensB = tokenizeSimilarityText($b);
    if (empty($tokensA) || empty($tokensB)) {
        return 0.0;
    }

    $setA = array_fill_keys($tokensA, true);
    $setB = array_fill_keys($tokensB, true);
    $intersection = 0;

    foreach ($setA as $token => $_) {
        if (isset($setB[$token])) {
            $intersection++;
        }
    }

    $union = count($setA) + count($setB) - $intersection;
    if ($union <= 0) {
        return 0.0;
    }

    return visualSimilarityClamp($intersection / $union);
}

function getPinVisualSignature(PDO $pdo, int $pinId): ?array {
    if ($pinId <= 0) {
        return null;
    }

    ensureVisualSimilarityTable($pdo);

    $imgFile = normalizePinImageFilename(fetchPinImageFilename($pdo, $pinId));
    $isPlaceholder = isPlaceholderImageFilename($imgFile);

    $imageHash = sha1('sig_v3|' . $imgFile);

    $cachedStmt = $pdo->prepare('SELECT signature_json, image_hash FROM pin_visual_signatures WHERE pin_id = ? LIMIT 1');
    $cachedStmt->execute([$pinId]);
    $cached = $cachedStmt->fetch(PDO::FETCH_ASSOC);
    if ($cached && (string) ($cached['image_hash'] ?? '') === $imageHash) {
        $decoded = json_decode((string) ($cached['signature_json'] ?? ''), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $imagePath = resolvePinImageAbsolutePath($imgFile);
    $signature = buildImageVisualSignature($imagePath);
    if (!$signature) {
        return null;
    }

    $signature['is_placeholder'] = $isPlaceholder;

    $signatureJson = json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($signatureJson !== false) {
        $upsert = $pdo->prepare(
            'INSERT INTO pin_visual_signatures (pin_id, image_hash, signature_json) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE image_hash = VALUES(image_hash), signature_json = VALUES(signature_json)'
        );
        $upsert->execute([$pinId, $imageHash, $signatureJson]);
    }

    return $signature;
}

function computeVisualSignatureSimilarity(array $a, array $b): float {
    $h1 = is_array($a['h'] ?? null) ? $a['h'] : [];
    $h2 = is_array($b['h'] ?? null) ? $b['h'] : [];
    if (count($h1) !== 12 || count($h2) !== 12) {
        return 0.0;
    }

    $histIntersection = 0.0;
    $histUnion = 0.0;
    for ($i = 0; $i < 12; $i++) {
        $v1 = (float) $h1[$i];
        $v2 = (float) $h2[$i];
        $histIntersection += min($v1, $v2);
        $histUnion += max($v1, $v2);
    }
    $histScore = $histUnion > 0 ? ($histIntersection / $histUnion) : 0.0;

    $hcA = is_array($a['hc'] ?? null) ? $a['hc'] : [];
    $hlA = is_array($a['hl'] ?? null) ? $a['hl'] : [];
    $hcB = is_array($b['hc'] ?? null) ? $b['hc'] : [];
    $hlB = is_array($b['hl'] ?? null) ? $b['hl'] : [];

    $regionDirectCenter = histogramIoU12($hcA, $hcB);
    $regionDirectLower = histogramIoU12($hlA, $hlB);

    // Cross-region helps item-vs-outfit comparisons (single jeans piece vs full-body outfit).
    $regionCrossA = max(histogramIoU12($h1, $hcB), histogramIoU12($h1, $hlB));
    $regionCrossB = max(histogramIoU12($h2, $hcA), histogramIoU12($h2, $hlA));
    $regionScore = max($regionDirectCenter, $regionDirectLower, $regionCrossA, $regionCrossB);

    $ha1 = is_array($a['ha'] ?? null) ? $a['ha'] : [];
    $ha2 = is_array($b['ha'] ?? null) ? $b['ha'] : [];
    $accentScore = 0.0;
    if (count($ha1) === 12 && count($ha2) === 12) {
        $accentIntersection = 0.0;
        $accentUnion = 0.0;
        for ($i = 0; $i < 12; $i++) {
            $av1 = (float) $ha1[$i];
            $av2 = (float) $ha2[$i];
            $accentIntersection += min($av1, $av2);
            $accentUnion += max($av1, $av2);
        }
        $accentScore = $accentUnion > 0 ? ($accentIntersection / $accentUnion) : 0.0;

        $accentRatioA = (float) ($a['ar'] ?? 0.0);
        $accentRatioB = (float) ($b['ar'] ?? 0.0);
        $accentCoverage = min(1.0, ($accentRatioA + $accentRatioB) * 3.0);
        $accentScore *= $accentCoverage;
    }

    $rgbA = is_array($a['rgb'] ?? null) ? $a['rgb'] : [0, 0, 0];
    $rgbB = is_array($b['rgb'] ?? null) ? $b['rgb'] : [0, 0, 0];
    $dr = ((float) ($rgbA[0] ?? 0)) - ((float) ($rgbB[0] ?? 0));
    $dg = ((float) ($rgbA[1] ?? 0)) - ((float) ($rgbB[1] ?? 0));
    $db = ((float) ($rgbA[2] ?? 0)) - ((float) ($rgbB[2] ?? 0));
    $rgbDistance = sqrt($dr * $dr + $dg * $dg + $db * $db);
    $rgbScore = visualSimilarityClamp(1.0 - ($rgbDistance / 1.7320508));

    $svA = is_array($a['sv'] ?? null) ? $a['sv'] : [0, 0];
    $svB = is_array($b['sv'] ?? null) ? $b['sv'] : [0, 0];
    $ds = abs(((float) ($svA[0] ?? 0)) - ((float) ($svB[0] ?? 0)));
    $dv = abs(((float) ($svA[1] ?? 0)) - ((float) ($svB[1] ?? 0)));
    $svScore = visualSimilarityClamp(1.0 - (($ds + $dv) / 2.0));

    $edgeA = (float) ($a['edge'] ?? 0.0);
    $edgeB = (float) ($b['edge'] ?? 0.0);
    $edgeScore = visualSimilarityClamp(1.0 - abs($edgeA - $edgeB));

    $ratioA = (float) ($a['ratio'] ?? 1.0);
    $ratioB = (float) ($b['ratio'] ?? 1.0);
    $ratioScore = visualSimilarityClamp(1.0 - min(1.0, abs($ratioA - $ratioB) / 1.2));

    $score =
        0.34 * $histScore +
        0.28 * $regionScore +
        0.24 * $accentScore +
        0.14 * $rgbScore +
        0.08 * $svScore +
        0.05 * $edgeScore +
        0.03 * $ratioScore;

    return visualSimilarityClamp($score);
}

function rankPinsByVisualSimilarity(PDO $pdo, int $sourcePinId, array $candidateRows): array {
    $sourceSignature = getPinVisualSignature($pdo, $sourcePinId);

    $sourceMetaStmt = $pdo->prepare(
        'SELECT p.title,
            COALESCE(dominant_color, "") AS dominant_color,
                COALESCE(style_tag, "") AS style_tag,
                COALESCE(season, "") AS season,
                COALESCE(category, "") AS category
         FROM pins p
         LEFT JOIN pin_discovery_meta pdm ON pdm.pin_id = p.id
         WHERE p.id = ?
         LIMIT 1'
    );
    $sourceMetaStmt->execute([$sourcePinId]);
    $sourceMeta = $sourceMetaStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'title' => '',
        'dominant_color' => '',
        'style_tag' => '',
        'season' => '',
        'category' => '',
    ];

    $ranked = [];
    foreach ($candidateRows as $row) {
        $candidateId = (int) ($row['id'] ?? 0);
        if ($candidateId <= 0 || $candidateId === $sourcePinId) {
            continue;
        }

        $candidateSignature = getPinVisualSignature($pdo, $candidateId);
        $visualScore = 0.0;
        if ($sourceSignature && $candidateSignature) {
            $visualScore = computeVisualSignatureSimilarity($sourceSignature, $candidateSignature);

            $sourcePlaceholder = !empty($sourceSignature['is_placeholder']);
            $candidatePlaceholder = !empty($candidateSignature['is_placeholder']);
            if ($sourcePlaceholder || $candidatePlaceholder) {
                // Placeholder images should not dominate similarity ranking.
                $visualScore = 0.0;
            }
        }

        $titleScore = computeTitleTokenSimilarity(
            (string) ($sourceMeta['title'] ?? ''),
            (string) ($row['title'] ?? '')
        );

        $metaScore = 0.0;
        if (($row['dominant_color'] ?? '') !== '' && (string) $row['dominant_color'] === (string) ($sourceMeta['dominant_color'] ?? '')) {
            $metaScore += 0.42;
        }
        if (($row['style_tag'] ?? '') !== '' && (string) $row['style_tag'] === (string) ($sourceMeta['style_tag'] ?? '')) {
            $metaScore += 0.3;
        }
        if (($row['season'] ?? '') !== '' && (string) $row['season'] === (string) ($sourceMeta['season'] ?? '')) {
            $metaScore += 0.18;
        }
        if (($row['category'] ?? '') !== '' && (string) $row['category'] === (string) ($sourceMeta['category'] ?? '')) {
            $metaScore += 0.1;
        }
        $metaScore = visualSimilarityClamp($metaScore);

        $likeCount = (int) ($row['like_count'] ?? 0);
        $popularityScore = visualSimilarityClamp(min(1.0, $likeCount / 120.0));

        $totalScore =
            0.78 * $visualScore +
            0.13 * $metaScore +
            0.07 * $titleScore +
            0.02 * $popularityScore;

        if (!$sourceSignature || !$candidateSignature) {
            $totalScore =
                0.62 * $metaScore +
                0.36 * $titleScore +
                0.02 * $popularityScore;
        }

        $hasStrongSignal = ($visualScore >= 0.19) || ($metaScore >= 0.3) || ($titleScore >= 0.34);
        if (!$hasStrongSignal) {
            continue;
        }

        $row['visual_similarity_score'] = round($visualScore, 6);
        $row['title_similarity_score'] = round($titleScore, 6);
        $row['total_similarity_score'] = round($totalScore, 6);
        $row['score'] = round($totalScore * 1000, 3);
        $ranked[] = $row;
    }

    usort($ranked, static function(array $a, array $b): int {
        $left = (float) ($a['total_similarity_score'] ?? 0);
        $right = (float) ($b['total_similarity_score'] ?? 0);
        if ($left === $right) {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        }
        return ($right <=> $left);
    });

    return $ranked;
}
