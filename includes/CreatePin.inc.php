<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "csrf.inc.php";
require_once "image_storage.inc.php";
require_once "collection_collaboration.inc.php";
require_once "discovery_filters.inc.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../HTML/Registration.php?error=notloggedin");
    exit();
}

// Fetch user's collections for the dropdown
$user_id = $_SESSION['user_id'];
require_once "dbh.inc.php";
$collection_options = getUserCreatableCollections($pdo, (int) $user_id);
ensureDiscoveryFilterTables($pdo);
$discoveryOptionSets = getDiscoveryFilterOptionSets();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    requireValidCsrfToken();
    $title = trim((string)($_POST["title"] ?? ''));
    $description = trim((string)($_POST["description"] ?? ''));
    $link = trim((string)($_POST["link"] ?? ''));
    $collection_id = isset($_POST["collection_id"]) ? (int) $_POST["collection_id"] : 0;
    $discoveryFilters = [
        'dominant_color' => (string) ($_POST['dominant_color'] ?? ''),
        'style_tag' => (string) ($_POST['style_tag'] ?? ''),
        'season' => (string) ($_POST['season'] ?? ''),
        'category' => (string) ($_POST['category'] ?? ''),
    ];
    $user_id = $_SESSION['user_id'];

    if ($title === '' || $collection_id <= 0) {
        $_SESSION['pin_error'] = "Missing required pin data.";
        header("Location: ../HTML/CreatePin.php?error=invalidinput");
        exit();
    }

    if ($link !== '' && filter_var($link, FILTER_VALIDATE_URL) === false) {
        $_SESSION['pin_error'] = "Invalid link provided.";
        header("Location: ../HTML/CreatePin.php?error=invalidlink");
        exit();
    }

    // Handle image upload
    $pin_image = null;
    if (isset($_FILES['pin_image']) && $_FILES['pin_image']['error'] == 0) {
        $result = saveFitspirationUploadedImage($_FILES['pin_image'], 'pin_');
        if (!$result['success']) {
            $_SESSION['pin_error'] = $result['error'];
            header("Location: ../HTML/CreatePin.php?error=invalidimage");
            exit();
        }
        $pin_image = $result['path'];
    }

    try {
        $accessQuery = $pdo->prepare(
            "SELECT c.user_id AS owner_user_id,
                    cc.role AS collaborator_role
             FROM collections c
             LEFT JOIN collection_collaborators cc
               ON cc.collection_id = c.collection_id AND cc.user_id = ?
             WHERE c.collection_id = ?
             LIMIT 1"
        );
        $accessQuery->execute([$user_id, $collection_id]);
        $accessRow = $accessQuery->fetch(PDO::FETCH_ASSOC);

        $hasEditAccess = false;
        if ($accessRow) {
            $ownerUserId = (int) ($accessRow['owner_user_id'] ?? 0);
            $role = ((int) $ownerUserId === (int) $user_id) ? 'owner' : normalizeCollectionRole((string) ($accessRow['collaborator_role'] ?? ''));
            $hasEditAccess = canUserEditCollectionWithRole($role);
        }

        if (!$hasEditAccess) {
            $_SESSION['pin_error'] = "You are not allowed to add pins to that collection.";
            header("Location: ../HTML/CreatePin.php?error=unauthorizedcollection");
            exit();
        }

        // Insert the new pin
        $query = "INSERT INTO pins (img, title, description, link, collection_id, user_id) VALUES (?, ?, ?, ?, ?, ?);";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$pin_image, $title, $description, $link, $collection_id, $user_id]);

        $pinId = (int) $pdo->lastInsertId();
        if ($pinId > 0) {
            savePinDiscoveryMeta($pdo, $pinId, $discoveryFilters);
        }

        header("Location: ../HTML/Profile.php?pin=created");
        exit();
    } catch (PDOException $e) {
        $_SESSION['pin_error'] = "Database error. Please try again later.";
        header("Location: ../HTML/CreatePin.php?error=dberror");
        exit();
    }
}
?>