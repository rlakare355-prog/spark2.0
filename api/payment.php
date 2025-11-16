<?php
// SPARK Platform - Payment API
header('Content-Type: application/json');

// Load Composer autoloader if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/MailjetService.php';

// Use Razorpay library if available
if (class_exists('Razorpay\Api\Api')) {
    $razorpayAvailable = true;
} else {
    $razorpayAvailable = false;
}

// Require login for all payment operations
requireLogin();

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'create_order':
            handleCreateOrder();
            break;

        case 'verify_payment':
            handleVerifyPayment();
            break;

        case 'get_payment_status':
            handleGetPaymentStatus();
            break;

        default:
            apiError('Invalid action', 400);
    }
} catch (Exception $e) {
    apiError($e->getMessage(), 500);
}

function handleCreateOrder() {
    $eventId = $_POST['event_id'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $currency = $_POST['currency'] ?? 'INR';

    if (!$eventId || !$amount) {
        apiError('Event ID and amount are required', 400);
    }

    // Validate event
    $event = dbFetch("
        SELECT e.*, COUNT(DISTINCT er.id) as registered_count
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id
        WHERE e.id = ? AND e.is_active = 1 AND e.event_date > NOW()
        GROUP BY e.id
    ", [$eventId]);

    if (!$event) {
        apiError('Event not found or registration closed', 404);
    }

    // Check if already registered
    $userId = $_SESSION['user_id'];
    $existing = dbFetch("
        SELECT id FROM event_registrations
        WHERE event_id = ? AND student_id = ?
    ", [$eventId, $userId]);

    if ($existing) {
        apiError('You are already registered for this event', 409);
    }

    // Check capacity
    if ($event['max_participants'] && $event['registered_count'] >= $event['max_participants']) {
        apiError('Event is full', 409);
    }

    // Verify amount matches event fee
    $expectedAmount = $event['fee'] * 100; // Convert to paise
    if ($amount != $expectedAmount) {
        apiError('Payment amount mismatch', 400);
    }

    // Get Razorpay credentials
    $keyId = getSetting('razorpay_key_id');
    $keySecret = getSetting('razorpay_key_secret');

    if (empty($keyId) || empty($keySecret)) {
        apiError('Payment gateway not configured', 503);
    }

    // Create Razorpay order
    $razorpayOrderData = [
        'amount' => $amount,
        'currency' => $currency,
        'receipt' => 'spark_event_' . $eventId . '_' . time(),
        'payment_capture' => 1,
        'notes' => [
            'event_id' => $eventId,
            'student_id' => $userId,
            'student_email' => $_SESSION['email']
        ]
    ];

    $razorpayOrderJson = json_encode($razorpayOrderData);
    $razorpayUrl = 'https://api.razorpay.com/v1/orders';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $razorpayUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $razorpayOrderJson);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($keyId . ':' . $keySecret)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        apiError('Failed to create payment order', 503);
    }

    $razorpayOrder = json_decode($response, true);

    // Store order in database
    $orderData = [
        'student_id' => $userId,
        'event_id' => $eventId,
        'amount' => $amount / 100, // Convert back to rupees
        'payment_id' => $razorpayOrder['id'],
        'order_id' => $razorpayOrder['id'],
        'status' => 'created'
    ];

    dbInsert('payments', $orderData);

    apiSuccess('Payment order created successfully', [
        'key_id' => $keyId,
        'order_id' => $razorpayOrder['id'],
        'amount' => $amount,
        'currency' => $currency,
        'event_name' => $event['title']
    ]);
}

function handleVerifyPayment() {
    $paymentId = $_POST['payment_id'] ?? null;
    $orderId = $_POST['order_id'] ?? null;
    $signature = $_POST['signature'] ?? null;
    $eventId = $_POST['event_id'] ?? null;
    $specialRequirements = $_POST['special_requirements'] ?? '';

    if (!$paymentId || !$orderId || !$signature || !$eventId) {
        apiError('Missing payment verification parameters', 400);
    }

    // Verify Razorpay signature
    $keySecret = getSetting('razorpay_key_secret');
    $generatedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $keySecret);

    if ($generatedSignature !== $signature) {
        apiError('Invalid payment signature', 400);
    }

    // Get payment details from Razorpay
    $razorpayUrl = 'https://api.razorpay.com/v1/payments/' . $paymentId;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $razorpayUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(getSetting('razorpay_key_id') . ':' . $keySecret)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        apiError('Failed to verify payment', 503);
    }

    $paymentData = json_decode($response, true);

    // Check payment status
    if ($paymentData['status'] !== 'captured') {
        apiError('Payment not successful', 400);
    }

    $userId = $_SESSION['user_id'];

    // Start database transaction
    dbTransaction(function($pdo) use ($userId, $eventId, $paymentData, $specialRequirements) {
        // Update payment record
        dbUpdate('payments', [
            'status' => 'captured',
            'payment_method' => $paymentData['method'] ?? 'online'
        ], 'order_id = ? AND student_id = ?', [$paymentData['order_id'], $userId]);

        // Create event registration
        $registrationData = [
            'event_id' => $eventId,
            'student_id' => $userId,
            'payment_status' => 'paid',
            'payment_id' => $paymentData['id'],
            'razorpay_order_id' => $paymentData['order_id'],
            'amount_paid' => $paymentData['amount'] / 100
        ];

        $registrationId = dbInsert('event_registrations', $registrationData);

        // Update event registration count if needed
        $event = dbFetch("SELECT max_participants, registered_count FROM events WHERE id = ?", [$eventId]);
        if ($event['max_participants']) {
            $newCount = $event['registered_count'] + 1;
            dbUpdate('events', ['registered_count' => $newCount], 'id = ?', [$eventId]);
        }

        return $registrationId;
    });

    // Send confirmation email using Mailjet
    $event = dbFetch("SELECT title, event_date, location FROM events WHERE id = ?", [$eventId]);
    $student = dbFetch("SELECT first_name, email FROM students WHERE id = ?", [$userId]);

    try {
        $mailjet = new MailjetService();
        $result = $mailjet->sendEventRegistrationEmail(
            $student['email'],
            $student['first_name'],
            $event['title'],
            $event['event_date'],
            $paymentData['amount'] / 100
        );

        if (!$result['success']) {
            error_log("Failed to send payment confirmation email: " . ($result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("Failed to send payment confirmation email: " . $e->getMessage());
    }

    apiSuccess('Payment verified and registration confirmed', [
        'registration_id' => $registrationId,
        'event_name' => $event['title']
    ]);
}

function handleGetPaymentStatus() {
    $orderId = $_GET['order_id'] ?? null;

    if (!$orderId) {
        apiError('Order ID is required', 400);
    }

    $payment = dbFetch("
        SELECT * FROM payments
        WHERE order_id = ? AND student_id = ?
    ", [$orderId, $_SESSION['user_id']]);

    if (!$payment) {
        apiError('Payment not found', 404);
    }

    apiSuccess('Payment status retrieved', [
        'status' => $payment['status'],
        'amount' => $payment['amount'],
        'created_at' => $payment['created_at']
    ]);
}
?>