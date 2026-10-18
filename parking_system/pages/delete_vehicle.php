<?php
// ============================================================
//  API: Delete Vehicle
//  File: pages/delete_vehicle.php
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
if (!$vehicleId) {
    echo json_encode(['success' => false, 'message' => 'Vehicle ID required']);
    exit;
}

try {
    $db = getDB();

    // Block deletion if vehicle is currently parked
    $active = $db->prepare(
        "SELECT transaction_id FROM parking_transactions
         WHERE vehicle_id = ? AND check_out_time IS NULL"
    );
    $active->execute([$vehicleId]);
    if ($active->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete: this vehicle is currently parked. Check it out first.']);
        exit;
    }

    // Fetch owner_id before delete
    $ownerStmt = $db->prepare("SELECT owner_id FROM vehicles WHERE vehicle_id = ?");
    $ownerStmt->execute([$vehicleId]);
    $row = $ownerStmt->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
        exit;
    }
    $ownerId = (int) ($row['owner_id'] ?: 0);

    $db->beginTransaction();

    // Delete transaction history for this vehicle (hard delete for clean removal)
    $db->prepare("DELETE FROM parking_transactions WHERE vehicle_id = ?")->execute([$vehicleId]);

    // Delete vehicle
    $db->prepare("DELETE FROM vehicles WHERE vehicle_id = ?")->execute([$vehicleId]);

    // Delete owner only if they have no other vehicles
    if ($ownerId) {
        $others = $db->prepare("SELECT COUNT(*) FROM vehicles WHERE owner_id = ?");
        $others->execute([$ownerId]);
        if ((int) $others->fetchColumn() === 0) {
            $db->prepare("DELETE FROM owners WHERE owner_id = ?")->execute([$ownerId]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Vehicle and its history deleted successfully']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction())
        $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}