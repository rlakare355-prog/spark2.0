<?php
// SPARK Platform - Installation & Setup Script
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPARK Platform - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #333;
            border: 2px solid #555;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .step.active {
            background: #00ff88;
            border-color: #00ff88;
            color: #000;
        }
        .step.completed {
            background: #28a745;
            border-color: #28a745;
        }
        .install-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .code-block {
            background: #000;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 1rem;
            color: #00ff88;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
        }
        .section-title {
            color: #00ff88;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .action-buttons {
            text-align: center;
            margin-top: 2rem;
        }
        .copy-btn {
            background: #00ff88;
            color: #000;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .copy-btn:hover {
            background: #00cc6a;
        }
    </style>
</head>
<body>
    <div class="container install-container">
        <div class="text-center mb-5">
            <h1 style="color: #00ff88; font-size: 3rem; font-weight: bold;">
                <i class="fas fa-bolt me-3"></i>SPARK Platform
            </h1>
            <p style="color: #ccc;">Installation & Setup Guide</p>
        </div>

        <!-- Progress Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">1</div>
            <div class="step" id="step2">2</div>
            <div class="step" id="step3">3</div>
            <div class="step" id="step4">4</div>
        </div>

        <!-- Step 1: Environment Check -->
        <div class="install-card" id="step1-content">
            <h3 class="section-title"><i class="fas fa-server me-2"></i>Environment Requirements</h3>

            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: #00ff88;">PHP Requirements:</h5>
                    <ul style="list-style: none; padding-left: 0;">
                        <li><i class="fas fa-check text-success"></i> PHP 8.0+ <?php echo version_compare(PHP_VERSION, '8.0', '>=') ? '✅' : '❌ ' . PHP_VERSION; ?></li>
                        <li><i class="fas fa-check text-success"></i> cURL Extension: <?php echo extension_loaded('curl') ? '✅' : '❌'; ?></li>
                        <li><i class="fas fa-check text-success"></i> JSON Extension: <?php echo extension_loaded('json') ? '✅' : '❌'; ?></li>
                        <li><i class="fas fa-check text-success"></i> mbstring Extension: <?php echo extension_loaded('mbstring') ? '✅' : '❌'; ?></li>
                        <li><i class="fas fa-check text-success"></i> GD Extension: <?php echo extension_loaded('gd') ? '✅' : '❌'; ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5 style="color: #00ff88;">File Permissions:</h5>
                    <ul style="list-style: none; padding-left: 0;">
                        <li><i class="fas fa-check text-success"></i> Writable: includes/</li>
                        <li><i class="fas fa-check text-success"></i> Writable: assets/images/</li>
                        <li><i class="fas fa-check text-success"></i> Writable: assets/qrcodes/</li>
                    </ul>
                </div>
            </div>

            <div class="action-buttons">
                <button class="copy-btn" onclick="copyCommand('composer install')">
                    <i class="fas fa-clipboard me-2"></i>Copy Install Command
                </button>
            </div>
        </div>

        <!-- Step 2: Composer Installation -->
        <div class="install-card" id="step2-content" style="display: none;">
            <h3 class="section-title"><i class="fas fa-terminal me-2"></i>Install Dependencies</h3>

            <h5 style="color: #00ff88;">Run Composer Commands:</h5>
            <div class="code-block">
                composer require mailjet/mailjet-apiv3-php
                composer require razorkit/razorpay
                composer require endroid/qr-code
                composer install
            </div>

            <h5 style="color: #00ff88;">Create Directory Structure:</h5>
            <div class="code-block">
                mkdir -p assets/qrcodes
                mkdir -p assets/images/profiles
                chmod 755 assets/qrcodes
                chmod 755 assets/images/profiles
            </div>

            <div class="action-buttons">
                <button class="copy-btn" onclick="copyCommand('mkdir commands')">
                    <i class="fas fa-clipboard me-2"></i>Copy All Commands
                </button>
            </div>
        </div>

        <!-- Step 3: Database Setup -->
        <div class="install-card" id="step3-content" style="display: none;">
            <h3 class="section-title"><i class="fas fa-database me-2"></i>Database Configuration</h3>

            <div class="code-block">
                -- CREATE DATABASE
                CREATE DATABASE spark_platform;

                -- IMPORT SCHEMA
                mysql -u root -p spark_platform < database/schema.sql

                -- CREATE CONFIG FILE
                cp includes/config.example.php includes/config.php
                # Edit includes/config.php with your credentials
            </div>

            <h5 style="color: #00ff88;">Config Template:</h5>
            <div class="code-block">
                <?php
                // Database Settings
                define('DB_HOST', 'localhost');
                define('DB_NAME', 'spark_platform');
                define('DB_USER', 'your_username');
                define('DB_PASS', 'your_password');

                // API Keys (update with your actual keys)
                define('MAILJET_API_KEY', 'your_mailjet_api_key');
                define('MAILJET_API_SECRET', 'your_mailjet_api_secret');
                define('RAZORPAY_KEY_ID', 'your_razorpay_key_id');
                define('RAZORPAY_KEY_SECRET', 'your_razorpay_key_secret');

                // Site Settings
                define('SITE_URL', 'https://yourdomain.com/spark');
                define('SITE_NAME', 'SPARK - Sanjivani Platform');
                ?>
            </div>

            <div class="action-buttons">
                <button class="copy-btn" onclick="copyCommand('db commands')">
                    <i class="fas fa-clipboard me-2"></i>Copy Database Commands
                </button>
            </div>
        </div>

        <!-- Step 4: API Keys Setup -->
        <div class="install-card" id="step4-content" style="display: none;">
            <h3 class="section-title"><i class="fas fa-key me-2"></i>API Keys Configuration</h3>

            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: #00ff88;">Mailjet Setup:</h5>
                    <ol>
                        <li>Go to <a href="https://app.mailjet.com/signup" target="_blank" style="color: #00ff88;">mailjet.com</a></li>
                        <li>Sign up for free account</li>
                        <li>Go to Account → REST API</li>
                        <li>Generate API Key and Secret</li>
                        <li>Update in admin panel: Email Settings</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h5 style="color: #00ff88;">Razorpay Setup:</h5>
                    <ol>
                        <li>Go to <a href="https://dashboard.razorpay.com/signup" target="_blank" style="color: #00ff88;">razorpay.com</a></li>
                        <li>Create business account</li>
                        <li>Go to Settings → API Keys</li>
                        <li>Generate Key ID and Key Secret</li>
                        <li>Update in admin panel or config file</li>
                    </ol>
                </div>
            </div>

            <h5 style="color: #00ff88;">Configuration File Locations:</h5>
            <div class="code-block">
                Admin Panel Settings: /admin/email_settings.php
                Direct Config Update: /includes/config.php
                Database Schema: /database/schema.sql
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-4">
            <button class="btn btn-secondary me-2" onclick="previousStep()">
                <i class="fas fa-arrow-left"></i> Previous
            </button>
            <button class="btn btn-success me-2" onclick="nextStep()">
                Next <i class="fas fa-arrow-right"></i>
            </button>
            <button class="btn btn-warning me-2" onclick="window.open('https://github.com/your-org/spark', '_blank')">
                <i class="fas fa-book"></i> Documentation
            </button>
        </div>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        function showStep(step) {
            // Hide all steps
            for (let i = 1; i <= totalSteps; i++) {
                document.getElementById(`step${i}-content`).style.display = 'none';
                const stepIndicator = document.getElementById(`step${i}`);
                stepIndicator.classList.remove('active');
                stepIndicator.classList.remove('completed');
            }

            // Show current step
            document.getElementById(`step${step}-content`).style.display = 'block';

            // Update indicators
            for (let i = 1; i < step; i++) {
                document.getElementById(`step${i}`).classList.add('completed');
            }
            document.getElementById(`step${step}`).classList.add('active');

            // Update navigation buttons
            updateNavigationButtons(step);
        }

        function updateNavigationButtons(step) {
            const prevBtn = document.querySelector('.btn-secondary');
            const nextBtn = document.querySelector('.btn-success');

            prevBtn.style.display = step === 1 ? 'none' : 'inline-block';
            nextBtn.textContent = step === totalSteps ? 'Finish Installation' : `Next <i class="fas fa-arrow-right"></i>`;
            nextBtn.onclick = step === totalSteps ? finishInstallation : () => nextStep(step);
        }

        function nextStep(step = currentStep) {
            if (step < totalSteps) {
                currentStep = step + 1;
                showStep(currentStep);
            }
        }

        function previousStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        function copyCommand(type) {
            let commands = '';

            switch(type) {
                case 'composer install':
                    commands = `composer require mailjet/mailjet-apiv3-php\ncomposer require razorkit/razorpay\ncomposer require endroid/qr-code\ncomposer install`;
                    break;
                case 'mkdir commands':
                    commands = `mkdir -p assets/qrcodes\nmkdir -p assets/images/profiles\nchmod 755 assets/qrcodes\nchmod 755 assets/images/profiles`;
                    break;
                case 'db commands':
                    commands = `CREATE DATABASE spark_platform;\nmysql -u root -p spark_platform < database/schema.sql`;
                    break;
            }

            // Copy to clipboard
            navigator.clipboard.writeText(commands).then(() => {
                // Show success message
                showNotification('Commands copied to clipboard!', 'success');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                showNotification('Failed to copy commands', 'error');
            });
        }

        function finishInstallation() {
            if (confirm('Installation guide completed! Would you like to visit your SPARK platform?')) {
                window.location.href = '/student/';
            }
        }

        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'error'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert">
                    <i class="fas fa-times"></i>
                </button>
            `;

            document.body.appendChild(notification);

            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Initialize
        showStep(1);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') {
                nextStep();
            } else if (e.key === 'ArrowLeft') {
                previousStep();
            } else if (e.key === 'Enter') {
                nextStep();
            }
        });

        // Step progress auto-advance
        const checkEnvironment = () => {
            const phpOk = version_compare(PHP_VERSION, '8.0', '>=');
            const curlOk = extension_loaded('curl');
            const jsonOk = extension_loaded('json');
            const mbstringOk = extension_loaded('mbstring');
            const gdOk = extension_loaded('gd');

            if (phpOk && curlOk && jsonOk && mbstringOk && gdOk) {
                // Mark step 1 as completed after 3 seconds
                setTimeout(() => {
                    document.getElementById('step1').classList.add('completed');
                    document.getElementById('step1').classList.remove('active');
                }, 3000);
            }
        };

        // Check environment on load
        checkEnvironment();
    </script>
</body>
</html>