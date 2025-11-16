        </div>
    </main>

    <!-- Footer -->
    <footer class="footer bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-bolt me-2"></i>SPARK Platform</h5>
                    <p class="footer-text">
                        Sanjivani Platform for AI, Research & Knowledge.<br>
                        Empowering students with technology and innovation.
                    </p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/sanjivani" class="social-link" target="_blank" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.twitter.com/sanjivani" class="social-link" target="_blank" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/school/sanjivani" class="social-link" target="_blank" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="https://www.instagram.com/sanjivani" class="social-link" target="_blank" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>

                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-link me-2"></i>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>/student/">Home</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/student/events.php">Events</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/student/calendar.php">Calendar</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/student/research.php">Research</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/student/opportunities.php">Opportunities</a></li>
                    </ul>
                </div>

                <div class="col-md-4 mb-4">
                    <h5><i class="fas fa-info-circle me-2"></i>Contact Info</h5>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope me-2"></i>spark@sanjivani.edu</p>
                        <p><i class="fas fa-phone me-2"></i>+91 XXXX XXX XXX</p>
                        <p><i class="fas fa-map-marker-alt me-2"></i>Sanjivani College of Engineering<br>Kopargaon, Maharashtra</p>
                    </div>
                </div>
            </div>

            <div class="footer-bottom text-center mt-4 pt-4 border-top border-secondary">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> SPARK - Sanjivani Platform for AI, Research & Knowledge. All rights reserved.</p>
                <p class="mb-0">Designed with <i class="fas fa-heart text-danger"></i> by Sanjivani Team</p>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollToTop" class="btn btn-scroll-top" onclick="scrollToTop()">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Include calendar script if needed -->
    <?php if (isset($include_calendar) && $include_calendar): ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js"></script>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

    <?php if (isset($include_custom_script) && $include_custom_script): ?>
        <script src="<?php echo SITE_URL; ?>/assets/js/<?php echo $include_custom_script; ?>"></script>
    <?php endif; ?>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Scroll to top functionality
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Show/hide scroll to top button
        window.addEventListener('scroll', function() {
            const scrollBtn = document.getElementById('scrollToTop');
            if (scrollBtn) {
                if (window.pageYOffset > 300) {
                    scrollBtn.style.display = 'block';
                } else {
                    scrollBtn.style.display = 'none';
                }
            }
        });

        // Page loading animations
        document.addEventListener('DOMContentLoaded', function() {
            // Hide loading overlay
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                setTimeout(function() {
                    loadingOverlay.style.opacity = '0';
                    setTimeout(function() {
                        loadingOverlay.style.display = 'none';
                    }, 500);
                }, 1000);
            }
        });

        // Mobile menu handling
        document.addEventListener('click', function(e) {
            const navbarNav = document.getElementById('navbarNav');
            const navbarToggler = document.querySelector('.navbar-toggler');

            if (navbarNav && navbarToggler && !navbarNav.contains(e.target) && !navbarToggler.contains(e.target)) {
                const bsCollapse = new bootstrap.Collapse(navbarNav);
                bsCollapse.hide();
            }
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
?>