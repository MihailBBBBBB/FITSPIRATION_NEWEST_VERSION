<?php
session_start();
require_once '../includes/dbh.inc.php';
require_once '../includes/outfits_schema.inc.php';
include_once '../JS/headerFooter.php';
$isGuest = !isset($_SESSION['user_id']);

$introChallenge = [
    'theme' => 'Weekly Outfit Challenge',
    'description' => 'Join this week\'s challenge and share your strongest look with the community.',
    'week_key' => '',
];

try {
    $activeIntroChallenge = ensureCurrentWeeklyChallenge($pdo);
    if (is_array($activeIntroChallenge)) {
        $introChallenge['theme'] = (string) ($activeIntroChallenge['theme'] ?? $introChallenge['theme']);
        $introChallenge['description'] = (string) ($activeIntroChallenge['description'] ?? $introChallenge['description']);
        $introChallenge['week_key'] = (string) ($activeIntroChallenge['week_key'] ?? '');
    }
} catch (Throwable $challengeError) {
    error_log('Main intro challenge load failed: ' . $challengeError->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Fitspiration | Join the Community</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
        <link rel="stylesheet" href="../CSS/Main.css?v=16"/>
        <script src="../JS/translator.js"></script>
    </head>
    <body>
        
        <special-header></special-header>
        
        <div class="layout">
            <?php if (!$isGuest): ?>
                <special-aside></special-aside>
            <?php endif; ?>

            <main class="main-content intro-main<?php echo $isGuest ? ' no-sidebar' : ''; ?>">
                <section class="intro-hero">
                    <div class="intro-hero-copy">
                        <p class="intro-eyebrow">FASHION COMMUNITY PLATFORM</p>
                        <h2>Build outfits, discover style, and get inspired by real people.</h2>
                        <p class="intro-sub">
                            Fitspiration is where creators and fashion lovers share outfit ideas, remix looks,
                            and build personal style together.
                        </p>

                        <div class="intro-cta-row">
                            <?php if ($isGuest): ?>
                                <a class="intro-btn primary" href="Registration.php">Join the community</a>
                                <a class="intro-btn ghost" href="Login.php">I already have an account</a>
                            <?php else: ?>
                                <a class="intro-btn primary" href="Home.php">Go to community feed</a>
                                <a class="intro-btn ghost" href="OutfitBuilder.php">Open outfit builder</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="intro-hero-grid" aria-hidden="true">
                        <article class="intro-shot tall">
                            <img src="../images/Streetwear.jpg" alt="Streetwear inspiration">
                        </article>
                        <article class="intro-shot">
                            <img src="../images/Old_money.jpg" alt="Old money style">
                        </article>
                        <article class="intro-shot wide">
                            <img src="../images/Accessories.jpg" alt="Accessories and details">
                        </article>
                    </div>
                </section>

                <section class="intro-features">
                    <h3>Why people join Fitspiration</h3>
                    <div class="intro-feature-grid">
                        <article class="intro-feature-card">
                            <i class="fa-solid fa-shirt"></i>
                            <h4>Outfit Builder</h4>
                            <p>Combine pieces, auto-match outfits, and preview full looks before posting.</p>
                        </article>
                        <article class="intro-feature-card">
                            <i class="fa-solid fa-compass"></i>
                            <h4>Discover Feed</h4>
                            <p>Find pins, outfits, and creators by style, season, color, and visual similarity.</p>
                        </article>
                        <article class="intro-feature-card">
                            <i class="fa-solid fa-people-group"></i>
                            <h4>Community</h4>
                            <p>Follow creators, save ideas, remix outfit posts, and grow your fashion network.</p>
                        </article>
                    </div>
                </section>

                <section class="intro-steps">
                    <h3>How it works</h3>
                    <div class="intro-step-list">
                        <article class="intro-step">
                            <span>1</span>
                            <p>Create your profile and share your style preferences.</p>
                        </article>
                        <article class="intro-step">
                            <span>2</span>
                            <p>Explore the feed, collect ideas, and follow people who match your vibe.</p>
                        </article>
                        <article class="intro-step">
                            <span>3</span>
                            <p>Build and publish outfits, then get feedback from the community.</p>
                        </article>
                    </div>
                </section>

                <section class="intro-challenge">
                    <div class="intro-challenge-copy">
                        <p class="intro-challenge-eyebrow">WEEKLY EVENT</p>
                        <h3>Outfit Challenge</h3>
                        <p>
                            Every week we drop a new theme where members build and post their best look.
                            It is a fun way to try new aesthetics, get feedback, and discover fresh creators.
                        </p>
                        <ul>
                            <li>New theme every week</li>
                            <li>Community voting and engagement</li>
                            <li>Featured looks in the challenge feed</li>
                        </ul>
                        <a class="intro-btn ghost" href="OutfitChallenge.php">Explore Outfit Challenge</a>
                    </div>
                    <article class="intro-challenge-card" aria-label="Outfit challenge highlight">
                        <span>Current Theme</span>
                        <h4><?php echo htmlspecialchars($introChallenge['theme']); ?></h4>
                        <p><?php echo htmlspecialchars($introChallenge['description']); ?></p>
                        <?php if ($introChallenge['week_key'] !== ''): ?>
                            <p class="intro-challenge-week"><?php echo htmlspecialchars($introChallenge['week_key']); ?></p>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="intro-faq">
                    <h3>Quick FAQ</h3>
                    <div class="intro-faq-list">
                        <details class="intro-faq-item" open>
                            <summary>Do I need to pay to join?</summary>
                            <p>No. Creating an account and exploring the community is free.</p>
                        </details>
                        <details class="intro-faq-item">
                            <summary>Can I keep my collections private?</summary>
                            <p>Yes. You can control privacy on your collections and choose what to share publicly.</p>
                        </details>
                        <details class="intro-faq-item">
                            <summary>What can I do after signing up?</summary>
                            <p>You can save pins, follow creators, build outfits, and publish your own looks.</p>
                        </details>
                    </div>
                </section>

                <section class="intro-final-cta">
                    <h3>Ready to find your next look?</h3>
                    <p>Join Fitspiration and turn inspiration into outfits you can actually wear.</p>
                    <?php if ($isGuest): ?>
                        <a class="intro-btn primary" href="Registration.php">Create your account</a>
                    <?php else: ?>
                        <a class="intro-btn primary" href="Home.php">Open your feed</a>
                    <?php endif; ?>
                </section>
            </main>
        </div>
        
        <special-footer></special-footer>
        
    </body>
    </html>

