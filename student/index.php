<?php
// SPARK Platform - Student Home Page
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

$page_title = 'Home';
$page_subtitle = 'Welcome to SPARK - Where Innovation Meets Excellence';
$meta_description = 'SPARK - Sanjivani Platform for AI, Research & Knowledge. Join us to explore cutting-edge technology, research, and innovation.';
$meta_keywords = 'AI, Research, Knowledge, Sanjivani University, Innovation, Technology';

include __DIR__ . '/../templates/header.php';
?>

<!-- Hero Section with 3D Animations -->
<section class="hero-section">
    <div class="hero-content">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6">
                    <div class="hero-text" data-aos="fade-right">
                        <h1 class="hero-title">SPARK</h1>
                        <p class="hero-subtitle">Sanjivani Platform for AI, Research & Knowledge</p>
                        <p class="hero-description">
                            Igniting innovation and fostering excellence in artificial intelligence,
                            research, and technology. Join our vibrant community of learners,
                            innovators, and leaders.
                        </p>
                        <div class="hero-buttons" data-aos="fade-up" data-aos-delay="200">
                            <?php if (isLoggedIn()): ?>
                                <a href="<?php echo SITE_URL; ?>/student/dashboard.php" class="cta-button me-3">
                                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                </a>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/student/register.php" class="cta-button me-3">
                                    <i class="fas fa-rocket me-2"></i> Join SPARK
                                </a>
                                <a href="<?php echo SITE_URL; ?>/student/login.php" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i> Login
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-graphics" data-aos="fade-left">
                        <div class="floating-elements">
                            <div class="floating-element float-delay-1">
                                <i class="fas fa-brain"></i>
                            </div>
                            <div class="floating-element float-delay-2">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div class="floating-element float-delay-3">
                                <i class="fas fa-code"></i>
                            </div>
                            <div class="floating-element float-delay-4">
                                <i class="fas fa-microscope"></i>
                            </div>
                            <div class="floating-element float-delay-5">
                                <i class="fas fa-network-wired"></i>
                            </div>
                        </div>
                        <div class="central-sphere">
                            <div class="sphere">
                                <i class="fas fa-bolt"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title" data-aos="fade-up">What We Offer</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Explore opportunities in AI, research, and technology
                </p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Vision Card -->
            <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="feature-title">Our Vision</h3>
                    <p class="feature-description">
                        To create a dynamic ecosystem where students explore, innovate, and excel
                        in artificial intelligence, research, and cutting-edge technologies.
                    </p>
                </div>
            </div>

            <!-- Mission Card -->
            <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <h3 class="feature-title">Our Mission</h3>
                    <p class="feature-description">
                        Empower students with hands-on experience, collaborative research, and
                        industry exposure to become tomorrow's tech leaders.
                    </p>
                </div>
            </div>

            <!-- Technologies Card -->
            <div class="col-lg-4 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <h3 class="feature-title">Technologies</h3>
                    <p class="feature-description">
                        Machine Learning, Deep Learning, Computer Vision, Natural Language Processing,
                        IoT, Blockchain, Quantum Computing, and more.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section -->
<section class="stats-section py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card text-center">
                    <div class="stat-number" data-target="500">0</div>
                    <div class="stat-label">Active Members</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card text-center">
                    <div class="stat-number" data-target="50">0</div>
                    <div class="stat-label">Events Hosted</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card text-center">
                    <div class="stat-number" data-target="25">0</div>
                    <div class="stat-label">Research Projects</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-card text-center">
                    <div class="stat-number" data-target="100">0</div>
                    <div class="stat-label">Certificates Issued</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Upcoming Events Preview -->
<section class="events-preview py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title" data-aos="fade-up">Upcoming Events</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Don't miss out on exciting events and workshops
                </p>
            </div>
        </div>

        <div class="row g-4">
            <?php
            // Fetch upcoming events (placeholder data for now)
            $upcomingEvents = [
                [
                    'title' => 'AI Workshop: Introduction to Neural Networks',
                    'date' => '2024-02-15',
                    'description' => 'Learn the fundamentals of neural networks and deep learning.'
                ],
                [
                    'title' => 'Hackathon 2024: Code for Impact',
                    'date' => '2024-02-20',
                    'description' => '24-hour hackathon to solve real-world problems with code.'
                ],
                [
                    'title' => 'Research Symposium: Latest in AI',
                    'date' => '2024-02-25',
                    'description' => 'Present your research and learn about cutting-edge AI developments.'
                ]
            ];

            foreach ($upcomingEvents as $index => $event):
            ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo ($index + 1) * 100; ?>">
                <div class="card event-card h-100">
                    <div class="card-body">
                        <div class="event-date">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <?php echo formatDate($event['date']); ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($event['description']); ?></p>
                        <?php if (isLoggedIn()): ?>
                        <a href="<?php echo SITE_URL; ?>/student/events.php" class="btn btn-primary">
                            <i class="fas fa-ticket-alt me-2"></i> Register Now
                        </a>
                        <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/student/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i> Login to Register
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5">
            <a href="<?php echo SITE_URL; ?>/student/events.php" class="btn btn-outline-primary btn-lg" data-aos="fade-up">
                <i class="fas fa-calendar-alt me-2"></i> View All Events
            </a>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">
                <div class="cta-content" data-aos="zoom-in">
                    <h2 class="cta-title">Ready to Spark Your Future?</h2>
                    <p class="cta-subtitle">
                        Join our community of innovators, researchers, and tech enthusiasts
                    </p>
                    <?php if (!isLoggedIn()): ?>
                    <div class="cta-buttons mt-4">
                        <a href="<?php echo SITE_URL; ?>/student/register.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-user-plus me-2"></i> Register Now
                        </a>
                        <a href="<?php echo SITE_URL; ?>/student/team.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-users me-2"></i> Meet Our Team
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="cta-buttons mt-4">
                        <a href="<?php echo SITE_URL; ?>/student/dashboard.php" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-tachometer-alt me-2"></i> Go to Dashboard
                        </a>
                        <a href="<?php echo SITE_URL; ?>/student/research.php" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-flask me-2"></i> Explore Research
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Technologies Section -->
<section class="technologies-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h2 class="section-title" data-aos="fade-up">Technologies We Explore</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Cutting-edge technologies shaping the future
                </p>
            </div>
        </div>

        <div class="technologies-grid">
            <div class="tech-item" data-aos="flip-left" data-aos-delay="100">
                <div class="tech-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="tech-name">Machine Learning</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="200">
                <div class="tech-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="tech-name">Computer Vision</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="300">
                <div class="tech-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="tech-name">NLP</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="400">
                <div class="tech-icon">
                    <i class="fas fa-network-wired"></i>
                </div>
                <div class="tech-name">Neural Networks</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="500">
                <div class="tech-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="tech-name">Robotics</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="600">
                <div class="tech-icon">
                    <i class="fas fa-atom"></i>
                </div>
                <div class="tech-name">Quantum Computing</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="700">
                <div class="tech-icon">
                    <i class="fas fa-link"></i>
                </div>
                <div class="tech-name">Blockchain</div>
            </div>

            <div class="tech-item" data-aos="flip-left" data-aos-delay="800">
                <div class="tech-icon">
                    <i class="fas fa-cloud"></i>
                </div>
                <div class="tech-name">Cloud Computing</div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Additional styles for home page */
.hero-section {
    position: relative;
    background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
    overflow: hidden;
}

.hero-text {
    color: #ffffff;
}

.hero-description {
    font-size: 1.2rem;
    color: #b0b0b0;
    margin-bottom: 2rem;
}

.hero-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.hero-graphics {
    position: relative;
    height: 600px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.floating-elements {
    position: absolute;
    width: 100%;
    height: 100%;
}

.floating-element {
    position: absolute;
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, #00ff88, #00aaff);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #000000;
    font-size: 1.5rem;
    animation: float 4s ease-in-out infinite;
    box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
}

.float-delay-1 {
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}

.float-delay-2 {
    top: 20%;
    right: 15%;
    animation-delay: 0.5s;
}

.float-delay-3 {
    top: 60%;
    left: 5%;
    animation-delay: 1s;
}

.float-delay-4 {
    bottom: 20%;
    right: 10%;
    animation-delay: 1.5s;
}

.float-delay-5 {
    top: 40%;
    right: 25%;
    animation-delay: 2s;
}

.central-sphere {
    position: relative;
    width: 200px;
    height: 200px;
}

.sphere {
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, #00ff88, #00aaff, #ff00aa);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #ffffff;
    animation: rotate 10s linear infinite, pulse 2s ease-in-out infinite;
    box-shadow: 0 0 50px rgba(0, 255, 136, 0.5);
}

@keyframes rotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.section-title {
    font-size: 2.5rem;
    font-weight: bold;
    background: linear-gradient(45deg, #00ff88, #00aaff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 1rem;
}

.section-subtitle {
    font-size: 1.2rem;
    color: #b0b0b0;
}

.stats-section {
    background: var(--secondary-color);
}

.stat-card {
    padding: 2rem;
    background: var(--card-bg);
    border-radius: 15px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent-color);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.2);
}

.stat-number {
    font-size: 3rem;
    font-weight: bold;
    color: var(--accent-color);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1.1rem;
    color: var(--text-primary);
}

.event-card {
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent-color);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.2);
}

.event-date {
    color: var(--accent-color);
    font-weight: 500;
    margin-bottom: 1rem;
}

.cta-section {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    position: relative;
    overflow: hidden;
}

.cta-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(0,255,136,0.2)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
}

.cta-content {
    position: relative;
    z-index: 1;
}

.cta-title {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--text-primary);
    margin-bottom: 1rem;
}

.cta-subtitle {
    font-size: 1.3rem;
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.technologies-section {
    background: var(--secondary-color);
}

.technologies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-top: 3rem;
}

.tech-item {
    text-align: center;
    padding: 2rem;
    background: var(--card-bg);
    border-radius: 15px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.tech-item:hover {
    transform: translateY(-10px) scale(1.05);
    border-color: var(--accent-color);
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.3);
}

.tech-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1rem;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--primary-color);
    transition: all 0.3s ease;
}

.tech-item:hover .tech-icon {
    transform: scale(1.1);
    box-shadow: 0 0 30px rgba(0, 255, 136, 0.5);
}

.tech-name {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .hero-title {
        font-size: 3rem;
    }

    .section-title,
    .cta-title {
        font-size: 2rem;
    }

    .technologies-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .hero-graphics {
        height: 400px;
    }

    .central-sphere {
        width: 150px;
        height: 150px;
    }

    .sphere {
        font-size: 2rem;
    }

    .floating-element {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
}
</style>

<script>
// Statistics counter animation
function animateCounter() {
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200;

    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const increment = target / speed;

        const updateCount = () => {
            const count = +counter.innerText;

            if (count < target) {
                counter.innerText = Math.ceil(count + increment);
                setTimeout(updateCount, 10);
            } else {
                counter.innerText = target + '+';
            }
        };

        updateCount();
    });
}

// Intersection Observer for counter animation
const observerOptions = {
    threshold: 0.5,
    rootMargin: '0px'
};

const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter();
            counterObserver.unobserve(entry.target);
        }
    });
}, observerOptions);

const statsSection = document.querySelector('.stats-section');
if (statsSection) {
    counterObserver.observe(statsSection);
}

// Enhanced particle interaction for hero section
document.addEventListener('mousemove', (e) => {
    const heroSection = document.querySelector('.hero-section');
    if (heroSection) {
        const rect = heroSection.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width - 0.5) * 20;
        const y = ((e.clientY - rect.top) / rect.height - 0.5) * 20;

        const centralSphere = document.querySelector('.central-sphere');
        if (centralSphere) {
            centralSphere.style.transform = `translate(${x}px, ${y}px)`;
        }
    }
});
</script>