<?php
// ============================================================
//  API: Vehicle Check-Out
//  File: pages/checkout.php
//  Uses stored procedure: sp_do_checkout
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$txnId         = (int)($_POST['transaction_id'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'cash';

if (!$txnId) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']); exit;
}

try {
    $db = getDB();

    // Call stored procedure sp_do_checkout
    // It uses fn_calculate_fee & fn_get_duration_hours internally,
    // and the audit log is written automatically by trg_after_checkout.
    $db->prepare("CALL sp_do_checkout(?, ?, ?, @plate, @slot_code, @fee, @hours, @ok, @msg)")
       ->execute([$txnId, $paymentMethod, 1]);

    $result = $db->query("SELECT @plate AS plate, @slot_code AS slot_code,
                                 @fee AS fee, @hours AS hours,
                                 @ok AS ok, @msg AS msg")
                 ->fetch();

    if (!$result['ok']) {
        echo json_encode(['success' => false, 'message' => $result['msg']]); exit;
    }

    echo json_encode([
        'success'        => true,
        'message'        => 'Check-out successful!',
        'plate'          => $result['plate'],
        'slot_code'      => $result['slot_code'],
        'duration_hours' => (int) $result['hours'],
        'fee_total'      => formatRupiah((float) $result['fee']),
        'fee_raw'        => (float) $result['fee'],
        'payment_method' => $paymentMethod
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
