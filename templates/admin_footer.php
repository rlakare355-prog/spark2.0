        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer admin-footer">
        <div class="footer-bottom">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="copyright mb-0">
                            © <?php echo date('Y'); ?> SPARK Admin Panel - All rights reserved.
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <span class="text-muted">Version 1.0</span>
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
