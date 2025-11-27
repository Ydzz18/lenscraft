<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LensCraft - Photography Community</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/img/Logo.png">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">Lens<span>Craft</span></a>
                <div class="nav-links">
                    <a href="#gallery">Gallery</a>
                    <a href="#about">About</a>
                    <a href="#contact">Contact</a>
                </div>
                <div class="auth-buttons">
                    <a href="auth/login.php" class="btn btn-secondary">Login</a>
                    <a href="auth/register.php" class="btn btn-primary">Sign Up</a>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Capture. Share. Inspire.</h1>
            <p>Join the world's most passionate photography community. Upload your photos, connect with fellow photographers, and showcase your talent to the world.</p>
            <a href="auth/register.php" class="btn btn-primary">Start Sharing</a>
        </div>
    </section>

    <!-- Gallery Section -->
    <section id="gallery" class="gallery-section">
        <div class="container">
            <h2 class="section-title">Featured Gallery</h2>
            <div class="gallery">
                <!-- Photo Card 1 -->
                <div class="photo-card">
                    <img src="https://placehold.co/400x300/667eea/ffffff?text=Mountain+Landscape" alt="Mountain Landscape" class="photo-image">
                    <div class="photo-info">
                        <h3 class="photo-title">Mountain Majesty</h3>
                        <div class="photo-meta">
                            <span>By John Doe</span>
                            <span>2 hours ago</span>
                        </div>
                        <p>A breathtaking view of the Alps at sunrise.</p>
                        <div class="photo-actions">
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-heart"></i>
                                <span>24</span>
                            </a>
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-comment"></i>
                                <span>8</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Photo Card 2 -->
                <div class="photo-card">
                    <img src="https://placehold.co/400x300/764ba2/ffffff?text=Urban+Street" alt="Urban Street" class="photo-image">
                    <div class="photo-info">
                        <h3 class="photo-title">Urban Pulse</h3>
                        <div class="photo-meta">
                            <span>By Jane Smith</span>
                            <span>5 hours ago</span>
                        </div>
                        <p>Street photography capturing city life in motion.</p>
                        <div class="photo-actions">
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-heart"></i>
                                <span>18</span>
                            </a>
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-comment"></i>
                                <span>12</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Photo Card 3 -->
                <div class="photo-card">
                    <img src="https://placehold.co/400x300/4facfe/ffffff?text=Wildlife" alt="Wildlife" class="photo-image">
                    <div class="photo-info">
                        <h3 class="photo-title">Wild Encounter</h3>
                        <div class="photo-meta">
                            <span>By Mike Johnson</span>
                            <span>1 day ago</span>
                        </div>
                        <p>Close encounter with wildlife in the Serengeti.</p>
                        <div class="photo-actions">
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-heart"></i>
                                <span>32</span>
                            </a>
                            <a href="auth/login.php" class="action-btn">
                                <i class="fas fa-comment"></i>
                                <span>15</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <h2 class="section-title">About LensCraft</h2>
            <div class="about-content">
                <p class="about-intro">LensCraft is a community-driven platform for photographers of all levels. Our mission is to connect creatives, help members grow their skills, and showcase inspiring visual stories from around the world.</p>
                <div class="about-grid">
                    <div class="about-tile">
                        <h3>Discover</h3>
                        <p>Explore curated galleries and find inspiration across genres and styles.</p>
                    </div>
                    <div class="about-tile">
                        <h3>Connect</h3>
                        <p>Engage with fellow photographers, share feedback, and grow your network.</p>
                    </div>
                    <div class="about-tile">
                        <h3>Learn</h3>
                        <p>Participate in challenges, tutorials, and community-led workshops.</p>
                    </div>
                </div>
                <p class="about-desc">Whether you're just starting out or you're a seasoned professional, LensCraft is built to help you share your vision and connect with people who care about photography as much as you do.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <h3 class="logo" style="color: white; margin-bottom: 20px;">LensCraft</h3>
                <p>Connecting photographers worldwide through the art of visual storytelling.</p>
                <div class="social-links">
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 LensCraft Photography Community. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>