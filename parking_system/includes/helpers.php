<?php
// ============================================================
//  Helper Functions
//  File: includes/helpers.php
// ============================================================

require_once __DIR__ . '/db.php';

/** Format IDR currency */
function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/** Calculate parking fee */
function calculateFee(string $checkIn, string $checkOut, float $ratePerHour): array {
    $in  = new DateTime($checkIn);
    $out = new DateTime($checkOut);
    $diffSeconds = $out->getTimestamp() - $in->getTimestamp();
    if ($diffSeconds < 0) $diffSeconds = 0;
    // Round up to nearest hour, minimum 1 hour
    $hours = max(1, ceil($diffSeconds / 3600));
    $fee   = $hours * $ratePerHour;
    return ['hours' => $hours, 'fee' => $fee];
}

/** Get dashboard summary stats */
function getDashboardStats(): array {
    $db = getDB();
    $stats = [];

    // Active vehicles (currently parked)
    $stats['active'] = (int) $db->query(
        "SELECT COUNT(*) FROM parking_transactions WHERE check_out_time IS NULL"
    )->fetchColumn();

    // Available slots
    $stats['available'] = (int) $db->query(
        "SELECT COUNT(*) FROM parking_slots WHERE slot_status = 'available'"
    )->fetchColumn();

    // Total slots
    $stats['total_slots'] = (int) $db->query(
        "SELECT COUNT(*) FROM parking_slots WHERE slot_status != 'maintenance'"
    )->fetchColumn();

    // Today's revenue
    $stats['revenue_today'] = (float) $db->query(
        "SELECT COALESCE(SUM(fee_total),0) FROM parking_transactions 
         WHERE DATE(check_out_time) = CURDATE() AND payment_status = 'paid'"
    )->fetchColumn();

    // Today's transactions
    $stats['txn_today'] = (int) $db->query(
        "SELECT COUNT(*) FROM parking_transactions WHERE DATE(check_in_time) = CURDATE()"
    )->fetchColumn();

    return $stats;
}

/** Get currently parked vehicles */
function getActiveParking(): array {
    $db = getDB();
    return $db->query(
        "SELECT t.transaction_id, v.plate_number, vt.type_name, 
                t.check_in_time, s.slot_code, s.zone,
                vt.rate_per_hour, v.brand, v.color
         FROM parking_transactions t
         JOIN vehicles v        ON t.vehicle_id = v.vehicle_id
         JOIN vehicle_types vt  ON v.type_id    = vt.type_id
         JOIN parking_slots s   ON t.slot_id    = s.slot_id
         WHERE t.check_out_time IS NULL
         ORDER BY t.check_in_time DESC"
    )->fetchAll();
}

/** Get available slots */
function getAvailableSlots(): array {
    $db = getDB();
    return $db->query(
        "SELECT slot_id, slot_code, zone, floor FROM parking_slots 
         WHERE slot_status = 'available' ORDER BY zone, slot_code"
    )->fetchAll();
}

/** Get all vehicle types with rates */
function getVehicleTypes(): array {
    return getDB()->query("SELECT * FROM vehicle_types ORDER BY type_name")->fetchAll();
}

/** Get recent transaction history */
function getTransactionHistory(int $limit = 50): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT t.transaction_id, v.plate_number, vt.type_name, s.slot_code,
                t.check_in_time, t.check_out_time, t.duration_hours,
                t.fee_total, t.payment_status, t.payment_method
         FROM parking_transactions t
         JOIN vehicles v       ON t.vehicle_id = v.vehicle_id
         JOIN vehicle_types vt ON v.type_id    = vt.type_id
         JOIN parking_slots s  ON t.slot_id    = s.slot_id
         WHERE t.check_out_time IS NOT NULL
         ORDER BY t.check_out_time DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** Get or create vehicle by plate number */
function findOrCreateVehicle(string $plate, int $typeId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE plate_number = ?");
    $stmt->execute([strtoupper(trim($plate))]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['vehicle_id'];
    // Create new vehicle record
    $ins = $db->prepare("INSERT INTO vehicles (plate_number, type_id) VALUES (?, ?)");
    $ins->execute([strtoupper(trim($plate)), $typeId]);
    return (int) $db->lastInsertId();
}

/** Count available slots using stored function fn_count_available_slots
 *  Pass zone string (e.g. 'A') or null for all zones.
 */
function countAvailableSlots(?string $zone = null): int {
    $db   = getDB();
    $stmt = $db->prepare("SELECT fn_count_available_slots(?) AS cnt");
    $stmt->execute([$zone]);
    return (int) $stmt->fetchColumn();
}

/** Get recent audit log entries from parking_logs table (populated by triggers) */
function getAuditLog(int $limit = 50): array {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT log_id, event_type, transaction_id, plate_number,
                slot_code, detail, logged_at
         FROM parking_logs
         ORDER BY logged_at DESC
         LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
