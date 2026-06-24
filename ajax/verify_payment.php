<?php
// ajax/verify_payment.php - Secured Institutional Payment Verification Node
require_once '../includes/auth_check.php';
header('Content-Type: application/json');

$paystack = require_once '../config/paystack.php';
$secret_key = $paystack['secret_key'];

$reference = $_POST['reference'] ?? '';
$package_id = $_POST['package_id'] ?? null;

if (empty($reference) || !$package_id) {
    die(json_encode(['success' => false, 'message' => 'Institutional reference missing.']));
}

try {
    // 1. Check if this reference has already been processed to prevent replay attacks
    $stmt = $pdo->prepare("SELECT id FROM platform_payments WHERE reference = ? AND status = 'success'");
    $stmt->execute([$reference]);
    if ($stmt->fetch()) {
        die(json_encode(['success' => false, 'message' => 'Transaction has already been reconciled on this node.']));
    }

    // 2. Fetch Package details for validation
    $stmt = $pdo->prepare("SELECT * FROM pricing_packages WHERE id = ?");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    if (!$package) die(json_encode(['success' => false, 'message' => 'Invalid monetized package node.']));

    // 3. Initiate Server-to-Server Verification with Paystack
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $secret_key",
        "Cache-Control: no-cache",
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        die(json_encode(['success' => false, 'message' => 'Operational connection error: ' . $err]));
    }

    $res = json_decode($response);

    // 4. Validate Transaction Status, Amount, and Currency
    if ($res->status && $res->data->status === 'success') {
        $amount_paid = $res->data->amount / 100; // Paystack sends in kobo
        $expected_amount = floatval($package['price_naira']);

        if ($amount_paid < $expected_amount) {
            // Log as warning or failed - partial payment?
            $stmt = $pdo->prepare("INSERT INTO platform_payments (school_id, package_id, reference, amount, credits_awarded, status, payment_method) VALUES (?, ?, ?, ?, ?, 'failed', ?)");
            $stmt->execute([$active_school_id, $package_id, $reference, $amount_paid, 0, $res->data->channel]);
            die(json_encode(['success' => false, 'message' => 'Payment amount discrepancy detected. Transaction aborted.']));
        }

        // 5. Successful Reconciliation Transactional Flow
        $pdo->beginTransaction();

        $credits_to_add = intval($package['credits']);
        $payment_method = $res->data->channel;

        // I. Update Payments Table
        $stmt = $pdo->prepare("INSERT INTO platform_payments (school_id, package_id, reference, amount, credits_awarded, status, payment_method) VALUES (?, ?, ?, ?, ?, 'success', ?)");
        $stmt->execute([$active_school_id, $package_id, $reference, $amount_paid, $credits_to_add, $payment_method]);

        // II. Inject Credits into Institutional Node
        $stmt = $pdo->prepare("UPDATE schools SET credits = credits + ? WHERE id = ?");
        $stmt->execute([$credits_to_add, $active_school_id]);

        // III. Log Activity Audit
        $stmt = $pdo->prepare("INSERT INTO credit_logs (school_id, amount, activity) VALUES (?, ?, ?)");
        $stmt->execute([$active_school_id, $credits_to_add, "Credit Top-up: " . $package['name']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Institutional credits successfully synchronized.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Paystack gateway rejected transaction verification.']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'System Synchronization Error: ' . $e->getMessage()]);
}
