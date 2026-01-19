<?php
$headerHTML = '';
require_once '../includes/dbh.inc.php';

$user_id = $_SESSION['user_id'];
$query = "SELECT is_admin FROM registration WHERE id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);


if ($result && $result['is_admin'] == 1) {
    $headerHTML = '
        <header class="header">
            <h1 onclick="window.location.href=\'Main.php\'">Fitspiration</h1>
            <div class="search-container">
                <form action="Home.php" method="GET" id="searchForm">
                    <input class="search-bar" type="text" id="searchInput" placeholder="Search for styles, trends..." required>
                </form>
            </div>
            <div class="buttons">
                <button class="login-btn" onclick="window.location.href=\'AdminPanel.php\'">Admin</button>
                <button class="profile-pic" onclick="window.location.href=\'Profile.php\'"><i class="fa-solid fa-circle-user"></i></button>
                <button class="login-btn" onclick="window.location.href=\'../includes/LogOut.inc.php\'">Log Out</button>
            </div>
        </header>
    ';
} else if (!isset($_SESSION['user_id'])) {
    $headerHTML = '
        <header class="header">
            <h1 onclick="window.location.href=\'Main.php\'">Fitspiration</h1>
            <div class="search-container">
                <form action="Home.php" method="GET" id="searchForm">
                    <input class="search-bar" id="searchInput" type="text" placeholder="Search for styles, trends..." required>
                </form>
            </div>
            <div class="buttons">
                <button class="login-btn" onclick="window.location.href=\'LogIn.php\'">Log In</button>
                <button class="signup-btn" onclick="window.location.href=\'Registration.php\'">Sign Up</button>
            </div>
        </header>
    ';
} else {
    $headerHTML = '
        <header class="header">
            <h1 onclick="window.location.href=\'Main.php\'">Fitspiration</h1>
            <div class="search-container">
                <form action="Home.php" method="GET" id="searchForm">
                    <input class="search-bar" type="text" id="searchInput" placeholder="Search for styles, trends..." required>
                </form>
            </div>
            <div class="buttons">
                <button class="profile-pic" onclick="window.location.href=\'Profile.php\'"><i class="fa-solid fa-circle-user"></i></button>
                <button class="login-btn" onclick="window.location.href=\'../includes/LogOut.inc.php\'">Log Out</button>
            </div>
        </header>
    ';
}
?>

<script>

class SpecialHeader extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `<?php echo $headerHTML; ?>`;
    }
}
customElements.define('special-header', SpecialHeader);


class SpecialFooter extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Contact Us</h4>
                    <p>Email: info@fitspiration.com</p>
                    <p>Phone: +371 21235324</p>
                    <p>Address: Bultu iela 7, 5</p>
                </div>
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <a href="https://facebook.com" target="_blank">Facebook</a>
                    <a href="https://instagram.com" target="_blank">Instagram</a>
                    <a href="https://twitter.com" target="_blank">Twitter</a>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Fitspiration. All rights reserved.</p>
            </div>
        </footer>
        `
    }
}

class SpecialAside extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
        <aside class="sidebar">
            <ul>
                <li><a href='Home.php'><i class="fas fa-house"></i> Home</a></li>
                <li><a href='Main.php'><i class="fas fa-th-large"></i> Categories</a></li>
                <li><a href='#'><i class="fas fa-bell"></i> Notifications</a></li>
            </ul>
        </aside>
        `
    }
}

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        
        // Обработка нажатия Enter в поле поиска
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                const searchTerm = searchInput.value.trim();
                if (searchTerm) {
                    console.log('Search term entered:', searchTerm);
                    window.location.href = 'Home.php?search=' + encodeURIComponent(searchTerm);
                } else {
                    console.log('Search term is empty, redirecting to Home.php');
                    window.location.href = 'Home.php';
                }
            }
        });
    });




customElements.define('special-footer', SpecialFooter);
customElements.define('special-aside', SpecialAside);
</script>
