        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-top">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="footer-widget">
                            <div class="footer-logo">
                                <div class="spark-logo">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <span class="ms-2">SPARK</span>
                            </div>
                            <p class="footer-description">
                                Sanjivani Platform for AI, Research & Knowledge - Empowering students through technology, innovation, and collaboration.
                            </p>
                            <div class="social-links">
                                <a href="#" class="social-link" data-aos="zoom-in" data-aos-delay="100">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="#" class="social-link" data-aos="zoom-in" data-aos-delay="200">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="#" class="social-link" data-aos="zoom-in" data-aos-delay="300">
                                    <i class="fab fa-instagram"></i>
                                </a>
                                <a href="#" class="social-link" data-aos="zoom-in" data-aos-delay="400">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="social-link" data-aos="zoom-in" data-aos-delay="500">
                                    <i class="fab fa-github"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <div class="footer-widget">
                            <h4 class="widget-title">Quick Links</h4>
                            <ul class="footer-links">
                                <li><a href="<?php echo SITE_URL; ?>/student/events.php">Events</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/team.php">Team</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/research.php">Research</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/opportunities.php">Opportunities</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/gallery.php">Gallery</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="footer-widget">
                            <h4 class="widget-title">Student Resources</h4>
                            <ul class="footer-links">
                                <li><a href="<?php echo SITE_URL; ?>/student/dashboard.php">Dashboard</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/certificates.php">Certificates</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/attendance.php">Attendance</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/calendar.php">Event Calendar</a></li>
                                <li><a href="<?php echo SITE_URL; ?>/student/contact.php">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <div class="footer-widget">
                            <h4 class="widget-title">Contact Info</h4>
                            <div class="contact-info">
                                <div class="contact-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Sanjivani University, Kopargaon<br>Maharashtra, India 423601</span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <a href="mailto:spark@sanjivani.edu">spark@sanjivani.edu</a>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <a href="tel:+912425236543">+91 2425 236543</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="copyright">
                            © <?php echo date('Y'); ?> SPARK - Sanjivani Platform for AI, Research & Knowledge. All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <div class="footer-bottom-links">
                            <a href="#">Privacy Policy</a>
                            <a href="#">Terms of Service</a>
                            <a href="#">Code of Conduct</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Notification Container -->
    <div id="notification-container" class="notification-container"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php if (isset($include_calendar) && $include_calendar): ?>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/australia.js"></script>
    <?php endif; ?>

    <script src="<?php echo SITE_URL; ?>/assets/js/custom.js"></script>

    <?php if (isset($include_payment) && $include_payment): ?>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>

    <!-- Page Specific Scripts -->
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline Scripts -->
    <?php if (isset($inline_scripts)): ?>
        <script>
            <?php echo $inline_scripts; ?>
        </script>
    <?php endif; ?>

</body>
</html>