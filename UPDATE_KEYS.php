<?php
// SPARK Platform - API Keys Update Script
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPARK Platform - Update API Keys</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #fff;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .update-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .title {
            color: #00ff88;
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2rem;
        }
        .key-section {
            margin-bottom: 2rem;
        }
        .key-label {
            color: #00ff88;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        .code-block {
            background: #000;
            border: 1px solid #333;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #00ff88;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            word-break: break-all;
        }
        .highlight {
            background: rgba(0, 255, 136, 0.2);
            border-left: 3px solid #00ff88;
            padding-left: 1rem;
        }
        .update-btn {
            background: #00ff88;
            color: #000;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
        }
        .update-btn:hover {
            background: #00cc6a;
            transform: translateY(-2px);
        }
        .copy-btn {
            background: #333;
            color: #00ff88;
            border: 1px solid #00ff88;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .copy-btn:hover {
            background: #00ff88;
            color: #000;
        }
        .success-message {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            color: #28a745;
        }
        .tab-container {
            margin-bottom: 2rem;
        }
        .nav-tabs {
            border-bottom: 2px solid #00ff88;
        }
        .nav-tabs .nav-link {
            color: #ccc;
            border: none;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        .nav-tabs .nav-link:hover {
            color: #00ff88;
        }
        .nav-tabs .nav-link.active {
            color: #00ff88;
            background: rgba(0, 255, 136, 0.1);
            border-bottom-color: #00ff88;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="title">
            <i class="fas fa-key me-3"></i>Update API Keys
        </div>

        <!-- Tab Navigation -->
        <div class="tab-container">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" data-bs-target="#mailjet-tab" type="button" role="tab">
                        <i class="fas fa-envelope me-2"></i>Mailjet
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" data-bs-target="#razorpay-tab" type="button" role="tab">
                        <i class="fas fa-credit-card me-2"></i>Razorpay
                    </a>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Mailjet Tab -->
            <div class="tab-pane fade show active" id="mailjet-tab">
                <div class="update-card">
                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-mail-bulk me-2"></i>Mailjet API Configuration
                        </div>
                        <div class="code-block">
                            // Mailjet Configuration
                            define('MAILJET_API_KEY', 'your_actual_api_key_here');
                            define('MAILJET_API_SECRET', 'your_actual_api_secret_here');
                            define('MAILJET_TEST_MODE', false); // Set to false in production
                        </div>
                        <button class="copy-btn" onclick="copyToClipboard('mailjet')">
                            <i class="fas fa-clipboard me-2"></i>Copy Mailjet Config
                        </button>
                    </div>

                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-cog me-2"></i>Update Method
                        </div>
                        <div class="highlight">
                            <strong>Method 1: Update via Admin Panel</strong>
                            <ol style="margin-left: 1rem;">
                                <li>Go to: <code>/admin/email_settings.php</code></li>
                                <li>Enter your Mailjet API Key and Secret</li>
                                <li>Click "Send Test Email" to verify</li>
                                <li>Click "Save Settings"</li>
                            </ol>
                            <br>
                            <strong>Method 2: Direct Config File Update</strong>
                            <ol style="margin-left: 1rem;">
                                <li>Open: <code>includes/config.php</code></li>
                                <li>Replace the placeholder values with your actual keys</li>
                                <li>Upload the updated file to your server</li>
                            </ol>
                        </div>
                    </div>

                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-link me-2"></i>Quick Links
                        </div>
                        <div>
                            <a href="https://app.mailjet.com/signup" target="_blank" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-external-link-alt me-2"></i>Create Mailjet Account
                            </a>
                            <a href="https://app.mailjet.com/account/api_keys" target="_blank" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-key me-2"></i>Get API Keys
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Razorpay Tab -->
            <div class="tab-pane fade" id="razorpay-tab">
                <div class="update-card">
                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-credit-card me-2"></i>Razorpay Payment Configuration
                        </div>
                        <div class="code-block">
                            // Razorpay Configuration
                            define('RAZORPAY_KEY_ID', 'your_actual_key_id_here');
                            define('RAZORPAY_KEY_SECRET', 'your_actual_key_secret_here');
                        </div>
                        <button class="copy-btn" onclick="copyToClipboard('razorpay')">
                            <i class="fas fa-clipboard me-2"></i>Copy Razorpay Config
                        </button>
                    </div>

                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-cog me-2"></i>Update Method
                        </div>
                        <div class="highlight">
                            <strong>Method 1: Update via Composer</strong>
                            <ol style="margin-left: 1rem;">
                                <li>Run: <code>composer update razorkit/razorpay</code></li>
                                <li>The library will automatically load from environment variables</li>
                            </ol>
                            <br>
                            <strong>Method 2: Direct Config File Update</strong>
                            <ol style="margin-left: 1rem;">
                                <li>Open: <code>includes/config.php</code></li>
                                <li>Replace the placeholder values with your actual keys</li>
                                <li>Upload the updated file to your server</li>
                            </ol>
                        </div>
                    </div>

                    <div class="key-section">
                        <div class="key-label">
                            <i class="fas fa-link me-2"></i>Quick Links
                        </div>
                        <div>
                            <a href="https://dashboard.razorpay.com/signup" target="_blank" class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-external-link-alt me-2"></i>Create Razorpay Account
                            </a>
                            <a href="https://dashboard.razorpay.com/keys" target="_blank" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-key me-2"></i>Get API Keys
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="text-center">
            <a href="index.php" class="btn btn-primary btn-lg">
                <i class="fas fa-home me-2"></i>Go to Platform
            </a>
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="success-message" style="display: none;">
            <i class="fas fa-check-circle fa-2x me-3"></i>
            API Keys configuration copied to clipboard!
            <br>
            <strong>Next Steps:</strong>
            <ol>
                <li>Update your config.php file with the copied values</li>
                <li>Test the integrations in your admin panel</li>
                <li>Verify email sending and payment processing</li>
            </ol>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const configs = {
            mailjet: `// Mailjet Configuration
define('MAILJET_API_KEY', 'your_actual_api_key_here');
define('MAILJET_API_SECRET', 'your_actual_api_secret_here');
define('MAILJET_TEST_MODE', false); // Set to false in production`,
            razorpay: `// Razorpay Configuration
define('RAZORPAY_KEY_ID', 'your_actual_key_id_here');
define('RAZORPAY_KEY_SECRET', 'your_actual_key_secret_here');`
        };

        function copyToClipboard(type) {
            const text = configs[type];

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    showSuccessMessage();
                }).catch(err => {
                    console.error('Failed to copy: ', err);
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            textArea.style.left = '-9999px';

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showSuccessMessage();
            } catch (err) {
                console.error('Failed to copy: ', err);
            }

            document.body.removeChild(textArea);
        }

        function showSuccessMessage() {
            const successDiv = document.getElementById('successMessage');
            successDiv.style.display = 'block';
            successDiv.scrollIntoView({ behavior: 'smooth' });

            // Hide after 10 seconds
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 10000);
        }

        // Auto-select first tab based on URL hash
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1);
            if (hash === 'razorpay') {
                document.querySelector('[data-bs-target="#razorpay-tab"]').click();
            }
        });

        // Update URL hash when tabs are changed
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                const target = e.target.getAttribute('data-bs-target');
                window.location.hash = target.substring(1);
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'c') {
                const activeTab = document.querySelector('.tab-pane.active');
                if (activeTab && activeTab.id === 'mailjet-tab') {
                    copyToClipboard('mailjet');
                } else if (activeTab && activeTab.id === 'razorpay-tab') {
                    copyToClipboard('razorpay');
                }
            }
        });
    </script>
</body>
</html>