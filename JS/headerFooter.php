<?php
$headerHTML = '';
require_once '../includes/dbh.inc.php';
require_once '../includes/notifications.inc.php';
require_once '../includes/messages_repository.inc.php';

$user_id = $_SESSION['user_id'] ?? null;
$result = null;
$unread_notifications = 0;
$unread_messages = 0;
$searchInputValue = isset($_GET['search']) ? (string) $_GET['search'] : '';
$searchScopeValue = isset($_GET['search_scope']) ? (string) $_GET['search_scope'] : 'all';
$allowedSearchScopes = ['all', 'pins', 'outfits', 'people', 'boards'];
if (!in_array($searchScopeValue, $allowedSearchScopes, true)) {
    $searchScopeValue = 'all';
}

if ($user_id) {
    $query = "SELECT is_admin FROM registration WHERE id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_notifications = getUnreadNotificationsCount($pdo, $user_id);
    $unread_messages = getUnreadMessagesCount($pdo, (int) $user_id);
}


if ($result && $result['is_admin'] == 1) {
    $headerHTML = '
        <header class="header">
            <h1 class="no-translate" onclick="window.location.href=\'Main.php\'">FITS<span>PIRATION</span></h1>
            <div class="search-container">
                <form action="Home.php" method="GET" id="searchForm">
                    <input class="search-bar" type="text" id="searchInput" name="search" placeholder="Search for styles, trends..." value="' . htmlspecialchars($searchInputValue, ENT_QUOTES) . '">
                    <input type="hidden" id="headerSearchScope" name="search_scope" value="' . htmlspecialchars($searchScopeValue, ENT_QUOTES) . '">
                    <div class="header-search-suggestions" id="headerSearchSuggestions"></div>
                </form>
            </div>
            <div class="buttons">
                <button class="login-btn header-action admin-btn" onclick="window.location.href=\'AdminPanel.php\'"><i class="fa-solid fa-shield-halved"></i><span>Admin</span></button>
                <button class="login-btn header-action messages-btn" onclick="window.location.href=\'Messages.php\'"><i class="fa-solid fa-envelope"></i><span>Messages</span></button>
                <button class="profile-pic" onclick="window.location.href=\'Profile.php\'"><i class="fa-solid fa-circle-user"></i></button>
                <button class="login-btn header-action logout-btn" onclick="window.location.href=\'/includes/LogOut.inc.php\'"><i class="fa-solid fa-right-from-bracket"></i><span>Log Out</span></button>
                <button class="translate-btn" id="translate-btn" onclick="window.translator?.toggleTranslation()">LV</button>
            </div>
        </header>
    ';
} else if (!isset($_SESSION['user_id'])) {
    $headerHTML = '
        <header class="header">
            <h1 class="no-translate" onclick="window.location.href=\'Main.php\'">FITS<span>PIRATION</span></h1>
            <div class="buttons">
                <button class="login-btn header-action login-action-btn" onclick="window.location.href=\'Login.php\'"><i class="fa-solid fa-right-to-bracket"></i><span>Log In</span></button>
                <button class="signup-btn header-action signup-action-btn" onclick="window.location.href=\'Registration.php\'"><i class="fa-solid fa-user-plus"></i><span>Sign Up</span></button>
                <button class="translate-btn" id="translate-btn" onclick="window.translator?.toggleTranslation()">LV</button>
            </div>
        </header>
    ';
} else {
    $headerHTML = '
        <header class="header">
            <h1 class="no-translate" onclick="window.location.href=\'Main.php\'">FITS<span>PIRATION</span></h1>
            <div class="search-container">
                <form action="Home.php" method="GET" id="searchForm">
                    <input class="search-bar" type="text" id="searchInput" name="search" placeholder="Search for styles, trends..." value="' . htmlspecialchars($searchInputValue, ENT_QUOTES) . '">
                    <input type="hidden" id="headerSearchScope" name="search_scope" value="' . htmlspecialchars($searchScopeValue, ENT_QUOTES) . '">
                    <div class="header-search-suggestions" id="headerSearchSuggestions"></div>
                </form>
            </div>
            <div class="buttons">
                <button class="login-btn header-action messages-btn" onclick="window.location.href=\'Messages.php\'"><i class="fa-solid fa-envelope"></i><span>Messages</span></button>
                <button class="profile-pic" onclick="window.location.href=\'Profile.php\'"><i class="fa-solid fa-circle-user"></i></button>
                <button class="login-btn header-action logout-btn" onclick="window.location.href=\'/includes/LogOut.inc.php\'"><i class="fa-solid fa-right-from-bracket"></i><span>Log Out</span></button>
                <button class="translate-btn" id="translate-btn" onclick="window.translator?.toggleTranslation()">LV</button>
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
                <li><a href='OutfitBuilder.php'><i class="fas fa-shirt"></i> Outfit Builder</a></li>
                <li><a href='OutfitChallenge.php'><i class="fas fa-trophy"></i> Outfit Challenge</a></li>
                <li>
                    <a href='Messages.php' class='messages-link'>
                        <i class="fas fa-envelope"></i> Messages
                        <span class="notification-badge" id="sidebarMessagesBadge" <?php echo $unread_messages > 0 ? '' : 'style="display:none;"'; ?>><?php echo (int)$unread_messages; ?></span>
                    </a>
                </li>
                <li>
                    <a href='Notifications.php' class='notifications-link'>
                        <i class="fas fa-bell"></i> Notifications
                        <span class="notification-badge" id="sidebarNotificationsBadge" <?php echo $unread_notifications > 0 ? '' : 'style="display:none;"'; ?>><?php echo (int)$unread_notifications; ?></span>
                    </a>
                </li>
            </ul>
        </aside>
        `
    }
}

    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNotificationsBadge = document.getElementById('sidebarNotificationsBadge');
        const sidebarMessagesBadge = document.getElementById('sidebarMessagesBadge');

        const setBadgeCount = (badgeElement, count) => {
            if (!badgeElement) {
                return;
            }

            const normalizedCount = Math.max(0, Number(count) || 0);
            if (normalizedCount > 0) {
                badgeElement.textContent = String(normalizedCount);
                badgeElement.style.display = '';
            } else {
                badgeElement.textContent = '';
                badgeElement.style.display = 'none';
            }
        };

        const refreshSidebarBadges = () => {
            fetch('../includes/live_badges.inc.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (!data || data.success !== true) {
                        return;
                    }

                    setBadgeCount(sidebarNotificationsBadge, data.unread_notifications);
                    setBadgeCount(sidebarMessagesBadge, data.unread_messages);
                })
                .catch(() => {});
        };

        refreshSidebarBadges();
        setInterval(refreshSidebarBadges, 2000);

        function updateMobileLayoutClass() {
            if (window.matchMedia('(max-width: 980px)').matches) {
                document.body.classList.add('mobile-layout');
            } else {
                document.body.classList.remove('mobile-layout');
            }
        }

        updateMobileLayoutClass();
        window.addEventListener('resize', updateMobileLayoutClass);

        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const suggestionsBox = document.getElementById('headerSearchSuggestions');
        const scopeInput = document.getElementById('headerSearchScope');
        const allowedScopes = ['all', 'pins', 'outfits', 'people', 'boards'];
        let activeScope = allowedScopes.includes(scopeInput?.value || '') ? scopeInput.value : 'all';

        if (!searchForm || !searchInput) {
            return;
        }

        if (scopeInput) {
            scopeInput.value = activeScope;
        }

        const recentKey = 'fitspiration_home_recent_searches';
        let activeController = null;

        const getRecentSearches = () => {
            try {
                const parsed = JSON.parse(localStorage.getItem(recentKey) || '[]');
                return Array.isArray(parsed) ? parsed.filter(Boolean).slice(0, 6) : [];
            } catch (error) {
                return [];
            }
        };

        const saveRecentSearch = (term) => {
            const clean = String(term || '').trim();
            if (!clean) {
                return;
            }
            const existing = getRecentSearches().filter(item => item.toLowerCase() !== clean.toLowerCase());
            existing.unshift(clean);
            localStorage.setItem(recentKey, JSON.stringify(existing.slice(0, 6)));
        };

        const closeSuggestions = () => {
            if (!suggestionsBox) {
                return;
            }
            suggestionsBox.innerHTML = '';
            suggestionsBox.classList.remove('visible');
        };

        const openSuggestions = () => {
            if (suggestionsBox) {
                suggestionsBox.classList.add('visible');
            }
        };

        const buildSearchUrl = (searchValue) => {
            const isOnHome = window.location.pathname.toLowerCase().includes('home.php');
            const url = new URL('Home.php', window.location.href);

            if (isOnHome) {
                const paramsToCarry = ['feed', 'content', 'sort', 'search_scope', 'color', 'style', 'season', 'category', 'smart_feed_id'];
                const currentParams = new URLSearchParams(window.location.search);
                paramsToCarry.forEach((param) => {
                    const value = currentParams.get(param);
                    if (value !== null && value !== '') {
                        url.searchParams.set(param, value);
                    }
                });
            }

            if (searchValue) {
                url.searchParams.set('search', searchValue);
            }
            url.searchParams.set('search_scope', activeScope);

            return url;
        };

        const renderScopeChips = () => {
            return `
                <div class="header-scope-chips" role="tablist" aria-label="Search scope">
                    ${allowedScopes.map(scope => `
                        <button type="button" class="header-scope-chip ${activeScope === scope ? 'active' : ''}" data-action="scope" data-scope="${scope}">${scope.charAt(0).toUpperCase() + scope.slice(1)}</button>
                    `).join('')}
                </div>
            `;
        };

        const renderRecent = () => {
            if (!suggestionsBox) {
                return;
            }
            const recent = getRecentSearches();
            if (recent.length === 0) {
                closeSuggestions();
                return;
            }

            suggestionsBox.innerHTML = `
                ${renderScopeChips()}
                <div class="header-suggestion-group">
                    <p class="header-suggestion-label">Recent searches</p>
                    <div class="header-suggestion-items">
                        ${recent.map(item => `<button type="button" class="header-suggestion-item" data-action="recent" data-value="${String(item).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;')}">${String(item).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</button>`).join('')}
                    </div>
                </div>
            `;
            openSuggestions();
        };

        const fetchSuggestions = (term) => {
            if (!suggestionsBox) {
                return;
            }
            if (activeController) {
                activeController.abort();
            }
            activeController = new AbortController();

            const url = new URL('../includes/unified_search.inc.php', window.location.href);
            url.searchParams.set('action', 'typeahead');
            url.searchParams.set('q', term);
            url.searchParams.set('search_scope', activeScope);

            fetch(url.toString(), {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: activeController.signal
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch suggestions');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data || !data.success) {
                        throw new Error('Invalid suggestions response');
                    }

                    const users = Array.isArray(data.suggestions?.users) ? data.suggestions.users : [];
                    const pins = Array.isArray(data.suggestions?.pins) ? data.suggestions.pins : [];
                    const outfits = Array.isArray(data.suggestions?.outfits) ? data.suggestions.outfits : [];
                    const boards = Array.isArray(data.suggestions?.collections) ? data.suggestions.collections : [];
                    const tags = Array.isArray(data.suggestions?.tags) ? data.suggestions.tags : [];
                    const items = [];

                    const safe = (value) => String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/\"/g, '&quot;');

                    const addLabeledItems = (label, dataItems, action, maxItems = 3) => {
                        const chunk = dataItems.slice(0, maxItems);
                        if (!chunk.length) {
                            return;
                        }
                        items.push(`<div class="header-suggestion-section-title">${safe(label)}</div>`);
                        chunk.forEach(entry => {
                            if (action === 'user') {
                                items.push(`<button type="button" class="header-suggestion-item" data-action="user" data-id="${Number(entry.id || 0)}">${safe(entry.username || 'User')}</button>`);
                                return;
                            }
                            const title = entry.title || entry.value || '';
                            items.push(`<button type="button" class="header-suggestion-item" data-action="${action}" data-value="${safe(title)}">${safe(title || label)}</button>`);
                        });
                    };

                    if (activeScope === 'all' || activeScope === 'people') {
                        addLabeledItems('People', users, 'user');
                    }
                    if (activeScope === 'all' || activeScope === 'pins') {
                        addLabeledItems('Pins', pins, 'search');
                    }
                    if (activeScope === 'all' || activeScope === 'outfits') {
                        addLabeledItems('Outfits', outfits, 'search');
                    }
                    if (activeScope === 'all' || activeScope === 'boards') {
                        addLabeledItems('Boards', boards, 'search');
                    }
                    if (activeScope === 'all' || activeScope === 'pins' || activeScope === 'outfits') {
                        addLabeledItems('Tags', tags, 'tag', 2);
                    }

                    if (items.length === 0) {
                        items.push(`<button type="button" class="header-suggestion-item" data-action="search" data-value="${safe(term)}">Search for "${safe(term)}"</button>`);
                    }

                    suggestionsBox.innerHTML = `
                        ${renderScopeChips()}
                        <div class="header-suggestion-group">
                            <p class="header-suggestion-label">Suggestions</p>
                            <div class="header-suggestion-items">${items.join('')}</div>
                        </div>
                    `;
                    openSuggestions();
                })
                .catch(error => {
                    if (error.name !== 'AbortError') {
                        console.error(error);
                    }
                });
        };

        searchForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const searchTerm = searchInput.value.trim();
            if (searchTerm) {
                saveRecentSearch(searchTerm);
            }
            closeSuggestions();
            window.location.href = buildSearchUrl(searchTerm).toString();
        });

        if (suggestionsBox) {
            let debounceTimer = null;

            searchInput.addEventListener('focus', () => {
                if (!searchInput.value.trim()) {
                    renderRecent();
                }
            });

            searchInput.addEventListener('input', () => {
                const value = searchInput.value.trim();
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (!value) {
                        renderRecent();
                        return;
                    }
                    if (value.length < 2) {
                        closeSuggestions();
                        return;
                    }
                    fetchSuggestions(value);
                }, 220);
            });

            suggestionsBox.addEventListener('click', (event) => {
                const scopeButton = event.target.closest('.header-scope-chip');
                if (scopeButton) {
                    const selectedScope = scopeButton.dataset.scope || 'all';
                    if (!allowedScopes.includes(selectedScope)) {
                        return;
                    }
                    activeScope = selectedScope;
                    if (scopeInput) {
                        scopeInput.value = activeScope;
                    }

                    const currentValue = searchInput.value.trim();
                    if (!currentValue) {
                        renderRecent();
                        return;
                    }
                    if (currentValue.length < 2) {
                        closeSuggestions();
                        return;
                    }
                    fetchSuggestions(currentValue);
                    return;
                }

                const button = event.target.closest('.header-suggestion-item');
                if (!button) {
                    return;
                }

                const action = button.dataset.action || 'search';

                if (action === 'user' && button.dataset.id) {
                    window.location.href = 'Profile.php?user_id=' + encodeURIComponent(button.dataset.id);
                    return;
                }

                const suggestionValue = (button.dataset.value || button.textContent || '').trim();
                if (!suggestionValue) {
                    return;
                }

                searchInput.value = suggestionValue;
                saveRecentSearch(suggestionValue);

                const url = buildSearchUrl(suggestionValue);
                if (action === 'tag' && button.dataset.kind && button.dataset.value && window.location.pathname.toLowerCase().includes('home.php')) {
                    url.searchParams.set(button.dataset.kind, button.dataset.value);
                }
                window.location.href = url.toString();
            });

            document.addEventListener('click', (event) => {
                if (!searchForm.contains(event.target)) {
                    closeSuggestions();
                }
            });
        }
    });




customElements.define('special-footer', SpecialFooter);
customElements.define('special-aside', SpecialAside);
</script>
