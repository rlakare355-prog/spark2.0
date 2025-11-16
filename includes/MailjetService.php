<?php
/**
 * Mailjet Email Service
 * Handles all email communications through Mailjet API
 */

class MailjetService {
    private $api_key;
    private $api_secret;
    private $from_email;
    private $from_name;
    private $is_test_mode;

    public function __construct() {
        $this->api_key = defined('MAILJET_API_KEY') ? MAILJET_API_KEY : '';
        $this->api_secret = defined('MAILJET_API_SECRET') ? MAILJET_API_SECRET : '';
        $this->from_email = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@sanjivani.edu';
        $this->from_name = defined('FROM_NAME') ? FROM_NAME : 'SPARK Platform';
        $this->is_test_mode = defined('MAILJET_TEST_MODE') && MAILJET_TEST_MODE;
    }

    /**
     * Send email using Mailjet API
     */
    public function sendEmail($to, $subject, $html_content, $text_content = '', $attachments = []) {
        if (empty($this->api_key) || empty($this->api_secret)) {
            throw new Exception('Mailjet API credentials not configured');
        }

        if (!is_array($to)) {
            $to = [['email' => $to]];
        } elseif (isset($to['email'])) {
            $to = [$to];
        }

        $data = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $this->from_email,
                        'Name' => $this->from_name
                    ],
                    'To' => $to,
                    'Subject' => $subject,
                    'HTMLPart' => $html_content,
                    'TextPart' => $text_content ?: strip_tags($html_content)
                ]
            ]
        ];

        // Add attachments if provided
        if (!empty($attachments)) {
            $data['Messages'][0]['Attachments'] = [];
            foreach ($attachments as $attachment) {
                $data['Messages'][0]['Attachments'][] = [
                    'ContentType' => $attachment['type'],
                    'Filename' => $attachment['name'],
                    'Base64Content' => base64_encode(file_get_contents($attachment['path']))
                ];
            }
        }

        // Log email attempt
        $this->logEmailAttempt($to, $subject, 'send');

        if ($this->is_test_mode) {
            // In test mode, just log the email and return success
            error_log("TEST MODE: Email would be sent to " . json_encode($to) . " with subject: $subject");
            return ['success' => true, 'test_mode' => true];
        }

        $result = $this->makeApiRequest('/send', 'POST', $data);

        if (isset($result['Messages'][0]['Status']) && $result['Messages'][0]['Status'] === 'success') {
            $this->logEmailAttempt($to, $subject, 'success');
            return ['success' => true, 'message_id' => $result['Messages'][0]['To'][0]['MessageID']];
        } else {
            $error = $result['Messages'][0]['Errors'][0]['ErrorMessage'] ?? 'Unknown error';
            $this->logEmailAttempt($to, $subject, 'error', $error);
            throw new Exception("Failed to send email: $error");
        }
    }

    /**
     * Send email template
     */
    public function sendTemplate($to, $template_id, $variables = []) {
        if (empty($this->api_key) || empty($this->api_secret)) {
            throw new Exception('Mailjet API credentials not configured');
        }

        if (!is_array($to)) {
            $to = [['email' => $to]];
        } elseif (isset($to['email'])) {
            $to = [$to];
        }

        $data = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $this->from_email,
                        'Name' => $this->from_name
                    ],
                    'To' => $to,
                    'TemplateID' => $template_id,
                    'TemplateLanguage' => true,
                    'TemplateErrorReporting' => [
                        'Email' => $this->from_email,
                        'Name' => $this->from_name
                    ],
                    'TemplateErrorDeliver' => true,
                    'Variables' => $variables
                ]
            ]
        ];

        $this->logEmailAttempt($to, "Template ID: $template_id", 'send');

        if ($this->is_test_mode) {
            error_log("TEST MODE: Template email would be sent to " . json_encode($to) . " with template ID: $template_id");
            return ['success' => true, 'test_mode' => true];
        }

        $result = $this->makeApiRequest('/send', 'POST', $data);

        if (isset($result['Messages'][0]['Status']) && $result['Messages'][0]['Status'] === 'success') {
            $this->logEmailAttempt($to, "Template ID: $template_id", 'success');
            return ['success' => true, 'message_id' => $result['Messages'][0]['To'][0]['MessageID']];
        } else {
            $error = $result['Messages'][0]['Errors'][0]['ErrorMessage'] ?? 'Unknown error';
            $this->logEmailAttempt($to, "Template ID: $template_id", 'error', $error);
            throw new Exception("Failed to send template email: $error");
        }
    }

    /**
     * Send verification email
     */
    public function sendVerificationEmail($to, $name, $verification_link) {
        $subject = 'Verify Your SPARK Platform Account';
        $html_content = $this->getEmailTemplate('verification', [
            'name' => $name,
            'verification_link' => $verification_link,
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $name, $reset_link) {
        $subject = 'Reset Your SPARK Platform Password';
        $html_content = $this->getEmailTemplate('password_reset', [
            'name' => $name,
            'reset_link' => $reset_link,
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Send event registration confirmation
     */
    public function sendEventRegistrationEmail($to, $name, $event_title, $event_date, $amount_paid = 0) {
        $subject = "Event Registration Confirmed: $event_title";
        $html_content = $this->getEmailTemplate('event_registration', [
            'name' => $name,
            'event_title' => $event_title,
            'event_date' => formatDate($event_date),
            'amount_paid' => formatCurrency($amount_paid),
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation($to, $name, $event_title, $amount, $transaction_id) {
        $subject = 'Payment Confirmation - SPARK Platform';
        $html_content = $this->getEmailTemplate('payment_confirmation', [
            'name' => $name,
            'event_title' => $event_title,
            'amount' => formatCurrency($amount),
            'transaction_id' => $transaction_id,
            'payment_date' => formatDate(date('Y-m-d')),
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Send certificate notification
     */
    public function sendCertificateEmail($to, $name, $certificate_title, $certificate_link) {
        $subject = "Certificate Issued: $certificate_title";
        $html_content = $this->getEmailTemplate('certificate', [
            'name' => $name,
            'certificate_title' => $certificate_title,
            'certificate_link' => $certificate_link,
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Send opportunity notification
     */
    public function sendOpportunityNotification($to, $name, $opportunity_title, $opportunity_type) {
        $subject = "New $opportunity_type Opportunity: $opportunity_title";
        $html_content = $this->getEmailTemplate('opportunity', [
            'name' => $name,
            'opportunity_title' => $opportunity_title,
            'opportunity_type' => $opportunity_type,
            'year' => date('Y')
        ]);

        return $this->sendEmail($to, $subject, $html_content);
    }

    /**
     * Get email statistics
     */
    public function getEmailStatistics($start_date = null, $end_date = null) {
        if ($this->is_test_mode) {
            return [
                'total_sent' => 0,
                'total_delivered' => 0,
                'total_opened' => 0,
                'total_clicked' => 0,
                'delivery_rate' => 0,
                'open_rate' => 0,
                'click_rate' => 0,
                'chart_data' => []
            ];
        }

        try {
            $params = [];
            if ($start_date) {
                $params['StartDate'] = $start_date;
            }
            if ($end_date) {
                $params['EndDate'] = $end_date;
            }

            $result = $this->makeApiRequest('/REST/statcounters', 'GET', $params);

            return [
                'total_sent' => $result['Sent'][0]['Sent'] ?? 0,
                'total_delivered' => $result['Delivered'][0]['Delivered'] ?? 0,
                'total_opened' => $result['Opened'][0]['Opened'] ?? 0,
                'total_clicked' => $result['Clicked'][0]['Clicked'] ?? 0,
                'delivery_rate' => $result['Sent'][0]['Sent'] > 0 ?
                    (($result['Delivered'][0]['Delivered'] ?? 0) / $result['Sent'][0]['Sent']) * 100 : 0,
                'open_rate' => $result['Delivered'][0]['Delivered'] > 0 ?
                    (($result['Opened'][0]['Opened'] ?? 0) / $result['Delivered'][0]['Delivered']) * 100 : 0,
                'click_rate' => $result['Opened'][0]['Opened'] > 0 ?
                    (($result['Clicked'][0]['Clicked'] ?? 0) / $result['Opened'][0]['Opened']) * 100 : 0
            ];
        } catch (Exception $e) {
            error_log("Error getting email statistics: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Make API request to Mailjet
     */
    private function makeApiRequest($endpoint, $method = 'GET', $data = null) {
        $url = 'https://api.mailjet.com/v3' . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->api_key . ':' . $this->api_secret);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("cURL error: $curl_error");
        }

        $result = json_decode($response, true);

        if ($http_code !== 200) {
            $error = $result['ErrorMessage'] ?? 'API request failed';
            throw new Exception("Mailjet API error ($http_code): $error");
        }

        return $result;
    }

    /**
     * Get email template
     */
    private function getEmailTemplate($type, $variables) {
        $templates = [
            'verification' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Email Verification - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>Email Verification</h2>
                        </div>
                        <div class="content">
                            <h3>Hello {{name}},</h3>
                            <p>Thank you for registering on the SPARK Platform. Please click the button below to verify your email address:</p>
                            <p style="text-align: center;">
                                <a href="{{verification_link}}" class="button">Verify Email Address</a>
                            </p>
                            <p><strong>Note:</strong> This verification link will expire in 24 hours.</p>
                            <p>If you did not create an account on SPARK Platform, please ignore this email.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'password_reset' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Password Reset - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #f5576c; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>Password Reset</h2>
                        </div>
                        <div class="content">
                            <h3>Hello {{name}},</h3>
                            <p>We received a request to reset your password for your SPARK Platform account.</p>
                            <p style="text-align: center;">
                                <a href="{{reset_link}}" class="button">Reset Password</a>
                            </p>
                            <div class="warning">
                                <strong>Important:</strong> This reset link will expire in 1 hour for security reasons.
                            </div>
                            <p>If you did not request a password reset, please ignore this email or contact support immediately.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'event_registration' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Event Registration Confirmed - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .event-details { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4facfe; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>Registration Confirmed!</h2>
                        </div>
                        <div class="content">
                            <h3>Hello {{name}},</h3>
                            <p>Great news! Your registration for the event has been confirmed successfully.</p>

                            <div class="event-details">
                                <h4>{{event_title}}</h4>
                                <p><strong>Date:</strong> {{event_date}}</p>
                                <p><strong>Amount Paid:</strong> {{amount_paid}}</p>
                            </div>

                            <p>Please arrive at the venue 15 minutes before the event starts. Don\'t forget to bring your ID card!</p>
                            <p>We look forward to seeing you there!</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'payment_confirmation' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Payment Confirmation - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: #333; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .payment-details { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #43e97b; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>Payment Successful!</h2>
                        </div>
                        <div class="content">
                            <h3>Hello {{name}},</h3>
                            <p>Your payment has been processed successfully. Thank you for using SPARK Platform!</p>

                            <div class="payment-details">
                                <h4>Payment Details</h4>
                                <p><strong>Event:</strong> {{event_title}}</p>
                                <p><strong>Amount:</strong> {{amount}}</p>
                                <p><strong>Transaction ID:</strong> {{transaction_id}}</p>
                                <p><strong>Payment Date:</strong> {{payment_date}}</p>
                            </div>

                            <p>A receipt has been generated and sent to your registered email address. You can also view your payment history in your dashboard.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'certificate' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Certificate Issued - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .certificate-details { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #fa709a; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>Certificate Issued!</h2>
                        </div>
                        <div class="content">
                            <h3>Congratulations {{name}}!</h3>
                            <p>We are pleased to inform you that your certificate has been issued successfully.</p>

                            <div class="certificate-details">
                                <h4>{{certificate_title}}</h4>
                                <p>Your hard work and dedication have been recognized. This certificate validates your achievement and can be shared with potential employers.</p>
                            </div>

                            <p style="text-align: center;">
                                <a href="{{certificate_link}}" class="button" style="display: inline-block; background: #fa709a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0;">View & Download Certificate</a>
                            </p>

                            <p>Congratulations once again on your achievement!</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            'opportunity' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>New Opportunity - SPARK Platform</title>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .opportunity-details { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>SPARK Platform</h1>
                            <h2>New Opportunity Available!</h2>
                        </div>
                        <div class="content">
                            <h3>Hello {{name}},</h3>
                            <p>Exciting news! A new opportunity has been posted on SPARK Platform that might be perfect for you.</p>

                            <div class="opportunity-details">
                                <h4>{{opportunity_title}}</h4>
                                <p><strong>Type:</strong> {{opportunity_type}}</p>
                                <p>Don\'t miss this chance to advance your career and gain valuable experience.</p>
                            </div>

                            <p>Visit the platform to learn more about this opportunity and submit your application.</p>
                        </div>
                        <div class="footer">
                            <p>&copy; {{year}} SPARK Platform - Sanjivani College of Engineering. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];

        $template = $templates[$type] ?? '';

        // Replace variables
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }

    /**
     * Log email attempt
     */
    private function logEmailAttempt($to, $subject, $status, $error = null) {
        try {
            $email_string = is_array($to) ? json_encode($to) : $to;

            $stmt = getPDO()->prepare("INSERT INTO email_logs (to_email, subject, status, error_message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$email_string, $subject, $status, $error]);

        } catch (Exception $e) {
            error_log("Failed to log email attempt: " . $e->getMessage());
        }
    }
}
?>