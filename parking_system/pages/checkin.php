<?php
// ============================================================
//  API: Vehicle Check-In
//  File: pages/checkin.php
//  Uses stored procedure: sp_do_checkin
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$plate  = trim($_POST['plate_number'] ?? '');
$typeId = (int)($_POST['type_id'] ?? 0);
$slotId = (int)($_POST['slot_id'] ?? 0);

if (!$plate || !$typeId || !$slotId) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']); exit;
}
if (!preg_match('/^[A-Z0-9\s\-]+$/i', $plate)) {
    echo json_encode(['success' => false, 'message' => 'Invalid plate number format']); exit;
}

try {
    $db = getDB();

    // Call stored procedure sp_do_checkin
    // It handles: slot validation, duplicate check, vehicle upsert,
    // transaction insert, slot status update, and audit log (via trigger).
    $db->prepare("CALL sp_do_checkin(?, ?, ?, ?, @txn_id, @slot_code, @rate, @ok, @msg)")
       ->execute([strtoupper(trim($plate)), $typeId, $slotId, 1]);

    $result = $db->query("SELECT @txn_id AS txn_id, @slot_code AS slot_code,
                                 @rate AS rate, @ok AS ok, @msg AS msg")
                 ->fetch();

    if (!$result['ok']) {
        echo json_encode(['success' => false, 'message' => $result['msg']]); exit;
    }

    echo json_encode([
        'success'        => true,
        'message'        => 'Check-in successful!',
        'transaction_id' => (int) $result['txn_id'],
        'slot_code'      => $result['slot_code'],
        'check_in'       => date('d M Y H:i'),
        'plate'          => strtoupper(trim($plate)),
        'rate'           => formatRupiah((float) $result['rate']) . '/hour'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
