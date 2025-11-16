<?php
// SPARK Platform - Student Contact Page
if (!function_exists('generateToken')) {
    require_once __DIR__ . '/../includes/config.php';
}
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../includes/database.php';
}
if (!function_exists('sendEmail')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $subject = sanitize($_POST['subject']);
        $message = sanitize($_POST['message']);

        // Validate required fields
        $required = ['name', 'email', 'subject', 'message'];
        foreach ($required as $field) {
            if (empty(${$field})) {
                throw new Exception("All fields are required");
            }
        }

        // Validate email
        if (!isValidEmail($email)) {
            throw new Exception("Please enter a valid email address");
        }

        // Validate message length
        if (strlen($message) < 10) {
            throw new Exception("Message must be at least 10 characters long");
        }

        if (strlen($message) > 1000) {
            throw new Exception("Message must be less than 1000 characters");
        }

        // Insert message into database
        $messageData = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message
        ];

        dbInsert('contact_messages', $messageData);

        // Send notification email to admin
        $adminSubject = "New Contact Message: " . $subject;
        $adminBody = "
            <h2>New Contact Message Received</h2>
            <div style='background: var(--card-bg); padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid var(--border-color);'>
                <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p><strong>Message:</strong></p>
                <div style='background: var(--glass-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
            </div>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>IP Address:</strong> " . getClientIP() . "</p>
        ";

        sendEmail(getSetting('admin_email'), $adminSubject, $adminBody);

        // Send confirmation email to user
        $userSubject = "Thank You for Contacting SPARK";
        $userBody = "
            <h2>Message Received!</h2>
            <p>Dear " . htmlspecialchars($name) . ",</p>
            <p>Thank you for reaching out to SPARK. We have received your message and will get back to you as soon as possible.</p>
            <div style='background: var(--card-bg); padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid var(--border-color);'>
                <h3>Message Details:</h3>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p><strong>Your Message:</strong></p>
                <div style='background: var(--glass-bg); padding: 15px; border-radius: 8px; border: 1px solid var(--glass-border);'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
            </div>
            <p>We typically respond within 24-48 hours.</p>
            <p>Best regards,<br>SPARK Team</p>
        ";

        sendEmail($email, $userSubject, $userBody);

        $_SESSION['success'][] = "Your message has been sent successfully! We'll get back to you soon.";
        header('Location: contact.php?sent=1');
        exit();

    } catch (Exception $e) {
        $_SESSION['errors'][] = $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }
}

$page_title = 'Contact';
$page_subtitle = 'Get in Touch with SPARK';
$breadcrumb = [
    ['name' => 'Home', 'link' => SITE_URL . '/student/', 'active' => false],
    ['name' => 'Contact', 'link' => '', 'active' => true]
];
$include_student_nav = true;

include __DIR__ . '/../templates/header.php';
?>

<!-- Contact Hero Section -->
<section class="contact-hero py-5">
    <div class="container">
        <div class="hero-content text-center" data-aos="fade-up">
            <h1 class="hero-title">
                <i class="fas fa-envelope me-2"></i> Get in Touch
            </h1>
            <p class="hero-subtitle">
                Have questions or suggestions? We'd love to hear from you!
            </p>
            <?php if (isset($_GET['sent'])): ?>
            <div class="success-message" data-aos="fade-up" data-aos-delay="100">
                <i class="fas fa-check-circle fa-3x mb-3"></i>
                <h3>Message Sent Successfully!</h3>
                <p>Thank you for contacting SPARK. We'll get back to you soon.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Contact Information Section -->
<section class="contact-info-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="info-content">
                        <h4>Location</h4>
                        <p>Sanjivani University<br>Kopargaon, Maharashtra 423601<br>India</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-content">
                        <h4>Email</h4>
                        <p><a href="mailto:spark@sanjivani.edu">spark@sanjivani.edu</a></p>
                        <p><a href="mailto:contact@sanjivani.edu">contact@sanjivani.edu</a></p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="info-card">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-content">
                        <h4>Phone</h4>
                        <p><a href="tel:+912425236543">+91 2425 236543</a></p>
                        <p><a href="tel:+912425236544">+91 2425 236544</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Form Section -->
<section class="contact-form-section py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-6">
                <div class="form-container" data-aos="fade-right">
                    <h2 class="form-title">Send Us a Message</h2>
                    <p class="form-description">
                        Fill out the form below and we'll respond as soon as possible.
                    </p>

                    <?php include __DIR__ . '/../includes/alerts.php'; ?>

                    <form id="contactForm" class="contact-form needs-validation" method="POST" novalidate>
                        <div class="form-group mb-4">
                            <label for="name" class="form-label">Your Name *</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['name'] ?? ''); ?>"
                                   required maxlength="100">
                            <div class="invalid-feedback">Please provide your name</div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['email'] ?? ''); ?>"
                                   required maxlength="255">
                            <div class="invalid-feedback">Please provide a valid email address</div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                   value="<?php echo htmlspecialchars($_SESSION['form_data']['subject'] ?? ''); ?>"
                                   required maxlength="255">
                            <div class="invalid-feedback">Please provide a subject</div>
                        </div>

                        <div class="form-group mb-4">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message"
                                      rows="6" required maxlength="1000"
                                      placeholder="Tell us how we can help you..."><?php echo htmlspecialchars($_SESSION['form_data']['message'] ?? ''); ?></textarea>
                            <div class="form-help">
                                <small>Minimum 10 characters, maximum 1000 characters</small>
                                <div class="char-counter">
                                    <span id="charCount">0</span> / 1000
                                </div>
                            </div>
                            <div class="invalid-feedback">Message must be between 10-1000 characters</div>
                        </div>

                        <div class="form-group mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="copy_me" name="copy_me"
                                       value="1" checked>
                                <label class="form-check-label" for="copy_me">
                                    Send me a copy of this message
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>
                                <span class="btn-text">Send Message</span>
                                <span class="btn-loading" style="display: none;">
                                    <i class="fas fa-spinner fa-spin me-2"></i>
                                    Sending...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="additional-info" data-aos="fade-left">
                    <h2 class="info-title">Quick Links</h2>
                    <div class="quick-links">
                        <a href="team.php" class="quick-link">
                            <i class="fas fa-users me-2"></i>
                            <span>Meet Our Team</span>
                        </a>
                        <a href="events.php" class="quick-link">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <span>Upcoming Events</span>
                        </a>
                        <a href="gallery.php" class="quick-link">
                            <i class="fas fa-images me-2"></i>
                            <span>View Gallery</span>
                        </a>
                        <a href="faq.php" class="quick-link">
                            <i class="fas fa-question-circle me-2"></i>
                            <span>Frequently Asked Questions</span>
                        </a>
                    </div>

                    <h2 class="info-title">Office Hours</h2>
                    <div class="office-hours">
                        <div class="hours-item">
                            <strong>Monday - Friday:</strong>
                            <span>9:00 AM - 6:00 PM</span>
                        </div>
                        <div class="hours-item">
                            <strong>Saturday:</strong>
                            <span>10:00 AM - 4:00 PM</span>
                        </div>
                        <div class="hours-item">
                            <strong>Sunday:</strong>
                            <span>Closed</span>
                        </div>
                    </div>

                    <h2 class="info-title">Social Media</h2>
                    <div class="social-links">
                        <a href="#" class="social-link facebook" target="_blank" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link twitter" target="_blank" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link instagram" target="_blank" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link linkedin" target="_blank" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link github" target="_blank" title="GitHub">
                            <i class="fab fa-github"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="map-section py-5">
    <div class="container">
        <div class="map-container" data-aos="zoom-in">
            <h2 class="map-title">Find Us on Campus</h2>
            <div class="map-embed">
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3740.3719!2d76.6907!3d19.075!2m3!1f0v1.5!2f76.6907!2d19.075!2m3!1f0!2f0!2f19.075!2m3!1f0!2m2!1s76.6907%2C19.075!2m3!1f76.6907%2C19.075!2m1!2f3740.3719%2C76.6907!2s2!1s2!2f19.075!2m1!1s2!2s2!2s0!2m0!0!5"
                    width="100%"
                    height="400"
                    style="border:0; border-radius: 15px;"
                    allowfullscreen=""
                    loading="lazy">
                </iframe>
            </div>
            <div class="map-directions">
                <a href="https://maps.google.com/?q=Sanjivani+University+Kopargaon" target="_blank" class="btn btn-outline-primary">
                    <i class="fas fa-directions me-2"></i>
                    Get Directions
                </a>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section py-5">
    <div class="container">
        <h2 class="section-title text-center mb-4" data-aos="fade-up">
            Frequently Asked Questions
        </h2>
        <div class="faq-accordion" id="faqAccordion">
            <div class="faq-item" data-aos="fade-up" data-aos-delay="100">
                <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                    <span>How do I join SPARK?</span>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="collapse" id="faq1" data-bs-parent="#faqAccordion">
                    <div class="faq-answer">
                        <p>To join SPARK, simply register on our website. Fill out the registration form with your academic details and you'll receive a confirmation email. Once verified, you can access all SPARK features including events, research projects, and opportunities.</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                    <span>What events does SPARK organize?</span>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="collapse" id="faq2" data-bs-parent="#faqAccordion">
                    <div class="faq-answer">
                        <p>SPARK organizes various events including workshops, hackathons, seminars, technical talks, and networking sessions. These events focus on AI, machine learning, research methodologies, and emerging technologies. Check our events page for the latest schedule.</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                    <span>How can I contribute to research projects?</span>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="collapse" id="faq3" data-bs-parent="#faqAccordion">
                    <div class="faq-answer">
                        <p>You can contribute to research projects by exploring our projects page and joining projects that match your interests and skills. Each project has a coordinator who will guide you. You can also propose new project ideas through our contact form.</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
                <button class="faq-question" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                    <span>What are the benefits of joining SPARK?</span>
                    <i class="fas fa-plus"></i>
                </button>
                <div class="collapse" id="faq4" data-bs-parent="#faqAccordion">
                    <div class="faq-answer">
                        <p>Joining SPARK gives you access to hands-on learning, networking opportunities, industry connections, certificates, research experience, participation in hackathons and competitions, leadership development, and exposure to cutting-edge technologies.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<style>
/* Contact Page Styles */
.contact-hero {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    border-bottom: 1px solid var(--border-color);
}

.hero-title {
    color: var(--text-primary);
    font-size: 3rem;
    font-weight: bold;
    margin-bottom: 1rem;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    color: var(--text-secondary);
    font-size: 1.2rem;
    margin-bottom: 2rem;
}

.success-message {
    background: var(--card-bg);
    border: 2px solid var(--success-color);
    border-radius: 20px;
    padding: 3rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 20px 40px rgba(0, 255, 136, 0.3);
}

.success-message i {
    color: var(--success-color);
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: pulse 2s ease-in-out infinite;
}

.success-message h3 {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.success-message p {
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.contact-info-section {
    background: var(--card-bg);
    border-bottom: 1px solid var(--border-color);
}

.info-card {
    display: flex;
    align-items: flex-start;
    padding: 2rem;
    border-radius: 15px;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(0, 255, 136, 0.2);
    border-color: var(--accent-color);
}

.info-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(45deg, var(--accent-color), var(--neon-blue));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    font-size: 1.5rem;
    margin-right: 1.5rem;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.info-card:hover .info-icon {
    transform: scale(1.1);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.info-content h4 {
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.info-content p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

.info-content a {
    color: var(--accent-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.info-content a:hover {
    color: var(--neon-blue);
    text-decoration: underline;
}

.contact-form-section {
    background: var(--secondary-color);
}

.form-container {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 2.5rem;
    backdrop-filter: blur(10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.form-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 1rem;
}

.form-description {
    color: var(--text-secondary);
    margin-bottom: 2rem;
}

.form-group {
    position: relative;
}

.form-label {
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.75rem;
    display: block;
}

.form-control {
    background: var(--primary-color);
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    border-radius: 10px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 30px rgba(0, 255, 136, 0.3);
    background: var(--primary-color);
}

.form-help {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 0.5rem;
}

.char-counter {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.form-check {
    background: rgba(0, 255, 136, 0.1);
    border: 1px solid var(--accent-color);
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.3s ease;
}

.form-check:hover {
    background: rgba(0, 255, 136, 0.2);
}

.form-actions {
    text-align: center;
    margin-top: 2rem;
}

.additional-info {
    padding: 2rem;
}

.info-title {
    color: var(--text-primary);
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 2rem;
}

.quick-links {
    display: grid;
    gap: 1rem;
    margin-bottom: 3rem;
}

.quick-link {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
}

.quick-link:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 255, 136, 0.3);
}

.quick-link i {
    font-size: 1.2rem;
    margin-right: 0.75rem;
    color: var(--accent-color);
    transition: all 0.3s ease;
}

.quick-link:hover i {
    color: var(--primary-color);
}

.quick-link span {
    font-weight: 600;
}

.office-hours {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 3rem;
    backdrop-filter: blur(10px);
}

.hours-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border-color);
}

.hours-item:last-child {
    border-bottom: none;
}

.hours-item strong {
    color: var(--text-primary);
}

.hours-item span {
    color: var(--accent-color);
    font-weight: 600;
}

.social-links {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.social-link.facebook { background: #1877f2; }
.social-link.twitter { background: #1da1f2; }
.social-link.instagram { background: linear-gradient(45deg, #f09433, #e1306c, #f77737); }
.social-link.linkedin { background: #0077b5; }
.social-link.github { background: #333; }

.social-link i {
    color: white;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.social-link:hover i {
    transform: scale(1.2);
}

.map-section {
    background: var(--card-bg);
}

.map-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    text-align: center;
    margin-bottom: 2rem;
}

.map-container {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.map-embed {
    position: relative;
}

.map-directions {
    text-align: center;
    margin-top: 1rem;
}

.faq-section {
    background: var(--secondary-color);
    border-top: 1px solid var(--border-color);
}

.section-title {
    color: var(--text-primary);
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 3rem;
}

.faq-accordion {
    max-width: 800px;
    margin: 0 auto;
}

.faq-item {
    margin-bottom: 1rem;
    border-radius: 15px;
    overflow: hidden;
}

.faq-question {
    width: 100%;
    background: var(--card-bg);
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    padding: 1.25rem;
    font-size: 1.1rem;
    font-weight: 600;
    text-align: left;
    transition: all 0.3s ease;
    position: relative;
}

.faq-question:hover {
    background: var(--glass-bg);
    border-color: var(--accent-color);
}

.faq-question i {
    position: absolute;
    right: 1.25rem;
    top: 50%;
    transform: translateY(-50%);
    transition: transform 0.3s ease;
}

.faq-question[aria-expanded="true"] i {
    transform: translateY(-50%) rotate(45deg);
}

.faq-answer {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-top: none;
}

.faq-answer p {
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

/* Loading State */
#submitBtn.loading {
    pointer-events: none;
    opacity: 0.8;
}

#submitBtn.loading .btn-text {
    display: none;
}

#submitBtn.loading .btn-loading {
    display: inline;
}

/* Form Validation */
.was-validated .form-control:invalid {
    border-color: var(--error-color);
}

.was-validated .form-control:invalid:focus {
    box-shadow: 0 0 30px rgba(255, 71, 87, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .hero-title {
        font-size: 2rem;
    }

    .info-card {
        flex-direction: column;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .info-icon {
        margin-right: 0;
        margin-bottom: 1rem;
    }

    .form-container {
        padding: 2rem 1.5rem;
    }

    .quick-links {
        grid-template-columns: 1fr;
    }

    .social-links {
        justify-content: center;
    }

    .faq-question {
        font-size: 1rem;
        padding: 1rem;
    }

    .faq-question i {
        right: 1rem;
    }
}

/* Animations */
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counter for message
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('charCount');

    if (messageTextarea && charCount) {
        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCount.textContent = length;

            if (length > 1000) {
                charCount.style.color = 'var(--error-color)';
            } else if (length < 10) {
                charCount.style.color = 'var(--warning-color)';
            } else {
                charCount.style.color = 'var(--text-secondary)';
            }
        });
    }

    // Form validation
    const contactForm = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submitBtn');

    contactForm.addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        } else {
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        }
    });

    // Real-time email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // FAQ accordion icon toggle
    document.querySelectorAll('.faq-question').forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (this.getAttribute('aria-expanded') === 'true') {
                setTimeout(() => {
                    icon.classList.remove('fa-plus');
                    icon.classList.add('fa-minus');
                }, 10);
            } else {
                setTimeout(() => {
                    icon.classList.remove('fa-minus');
                    icon.classList.add('fa-plus');
                }, 10);
            }
        });
    });

    // Form field animations
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach((control, index) => {
        control.style.animationDelay = `${index * 0.1}s`;
        control.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // Input focus effects
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });

        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });

    // Quick links hover effects
    document.querySelectorAll('.quick-link').forEach((link, index) => {
        link.style.animationDelay = `${index * 0.1}s`;
        link.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // FAQ items animation
    document.querySelectorAll('.faq-item').forEach((item, index) => {
        item.style.animationDelay = `${index * 0.1}s`;
        item.style.animation = 'slideInUp 0.6s ease-out both';
    });

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);

    // Clear session form data
    <?php if (isset($_SESSION['form_data'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        <?php unset($_SESSION['form_data']); ?>
    });
    <?php endif; ?>
});
</script>