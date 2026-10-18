-- ============================================================
--  PU PARKING MANAGEMENT SYSTEM
--  DATABASE - COMPLETE SCRIPT
--
--  HOW TO IMPORT:
--  1. Buka phpMyAdmin
--  2. Klik tab "Import"
--  3. Pilih file ini -> klik Go
--
--  ATAU via CLI:
--     mysql -u root < parking_db_complete.sql
--
--  Default Login:
--  Username : admin       Password : admin123
--  Username : operator1   Password : operator123
-- ============================================================

-- ============================================================
--  FILE 1 OF 2: TABLES, INDEXES & SEED DATA
--  parking_system/database_1_tables.sql
--
--  HOW TO IMPORT:
--  phpMyAdmin: Import > choose this file > Go
--  CLI:        mysql -u root < database_1_tables.sql
--
--  Import this file FIRST, then import database_2_routines.sql
-- ============================================================

-- ============================================================
--  PARKING MANAGEMENT SYSTEM - DATABASE SCHEMA
--  Normalized to Third Normal Form (3NF)
--  
--  3NF Analysis:
--  - 1NF: All attributes are atomic, no repeating groups
--  - 2NF: All non-key attributes fully dependent on PK
--  - 3NF: No transitive dependencies (e.g., rate info in
--          separate table, not repeated per transaction)
-- ============================================================

CREATE DATABASE IF NOT EXISTS parking_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE parking_db;

-- ============================================================
-- TABLE 1: vehicle_types
-- Purpose: Master list of vehicle categories
-- 3NF Note: Rate info stored here, not in vehicles or 
--           transactions (eliminates transitive dependency)
-- ============================================================
CREATE TABLE vehicle_types (
    type_id     INT PRIMARY KEY AUTO_INCREMENT,
    type_name   VARCHAR(50)    NOT NULL UNIQUE,  -- e.g. 'Car', 'Motorcycle', 'Truck'
    rate_per_hour DECIMAL(10,2) NOT NULL,
    description VARCHAR(100)   NULL,
    created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- TABLE 2: vehicles
-- Purpose: Registered vehicle master data
-- 3NF Note: type_id FK to vehicle_types (no transitive dep.)
--           Owner info in separate owners table
-- ============================================================
CREATE TABLE owners (
    owner_id    INT PRIMARY KEY AUTO_INCREMENT,
    full_name   VARCHAR(100)  NOT NULL,
    phone       VARCHAR(20)   NULL,
    email       VARCHAR(100)  NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE vehicles (
    vehicle_id      INT PRIMARY KEY AUTO_INCREMENT,
    plate_number    VARCHAR(20)  NOT NULL UNIQUE,
    type_id         INT          NOT NULL,
    brand           VARCHAR(50)  NULL,
    model           VARCHAR(50)  NULL,
    color           VARCHAR(30)  NULL,
    owner_id        INT          NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicle_type  FOREIGN KEY (type_id)  REFERENCES vehicle_types(type_id),
    CONSTRAINT fk_vehicle_owner FOREIGN KEY (owner_id) REFERENCES owners(owner_id)
);

-- ============================================================
-- TABLE 3: parking_slots
-- Purpose: Physical parking slot inventory
-- 3NF Note: Slot location data fully depends on slot_id only
-- ============================================================
CREATE TABLE parking_slots (
    slot_id     INT PRIMARY KEY AUTO_INCREMENT,
    slot_code   VARCHAR(10)  NOT NULL UNIQUE,  -- e.g. 'A-01', 'B-12'
    zone        VARCHAR(10)  NOT NULL,         -- e.g. 'A', 'B', 'C'
    floor       TINYINT      NOT NULL DEFAULT 1,
    slot_status ENUM('available','occupied','reserved','maintenance') DEFAULT 'available',
    type_id     INT          NULL,             -- optional: reserved for vehicle type
    CONSTRAINT fk_slot_type FOREIGN KEY (type_id) REFERENCES vehicle_types(type_id)
);

-- ============================================================
-- TABLE 4: parking_transactions
-- Purpose: Core check-in/check-out records
-- 3NF Note: fee_total derived from check-out time & rate,
--           stored for reporting; rate_snapshot stored to
--           preserve historical accuracy
-- ============================================================
CREATE TABLE parking_transactions (
    transaction_id  INT PRIMARY KEY AUTO_INCREMENT,
    vehicle_id      INT             NOT NULL,
    slot_id         INT             NOT NULL,
    check_in_time   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    check_out_time  DATETIME        NULL,
    duration_hours  DECIMAL(10,2)   NULL,  -- calculated on check-out
    rate_snapshot   DECIMAL(10,2)   NOT NULL,  -- rate at time of check-in
    fee_total       DECIMAL(10,2)   NULL,       -- calculated on check-out
    payment_status  ENUM('unpaid','paid','waived') DEFAULT 'unpaid',
    payment_method  ENUM('cash','card','e-wallet') NULL,
    operator_id     INT             NULL,             -- operator who processed the transaction
    operator_notes  TEXT            NULL,
    created_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_vehicle  FOREIGN KEY (vehicle_id)  REFERENCES vehicles(vehicle_id),
    CONSTRAINT fk_txn_slot     FOREIGN KEY (slot_id)     REFERENCES parking_slots(slot_id)
);

-- ============================================================
-- TABLE 5: operators (staff accounts)
-- 3NF Note: Separate from transactions to avoid redundancy
-- ============================================================
CREATE TABLE operators (
    operator_id INT PRIMARY KEY AUTO_INCREMENT,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,  -- hashed
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin','operator') DEFAULT 'operator',
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- FIX: operators table must exist before adding FK from parking_transactions.
-- Added here via ALTER TABLE after both tables are defined.
ALTER TABLE parking_transactions
    ADD CONSTRAINT fk_txn_operator FOREIGN KEY (operator_id) REFERENCES operators(operator_id);

-- ============================================================
-- INDEXES for query performance
-- ============================================================
CREATE INDEX idx_vehicles_plate       ON vehicles(plate_number);
CREATE INDEX idx_transactions_vehicle ON parking_transactions(vehicle_id);
CREATE INDEX idx_transactions_slot    ON parking_transactions(slot_id);
CREATE INDEX idx_transactions_checkin ON parking_transactions(check_in_time);
CREATE INDEX idx_slots_status         ON parking_slots(slot_status);

-- ============================================================

-- ============================================================
-- TABLE 6: parking_logs  (audit trail written by triggers)
-- ============================================================
CREATE TABLE parking_logs (
    log_id         INT PRIMARY KEY AUTO_INCREMENT,
    event_type     VARCHAR(30)  NOT NULL,
    transaction_id INT          NULL,
    plate_number   VARCHAR(20)  NULL,
    slot_code      VARCHAR(10)  NULL,
    detail         TEXT         NULL,
    logged_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  STORED FUNCTIONS
-- ============================================================



-- ============================================================
--  FILE 2 OF 2: FUNCTIONS, PROCEDURES & TRIGGERS
--  parking_system/database_2_routines.sql
--
--  HOW TO IMPORT:
--  phpMyAdmin: Select 'parking_db' database first, then:
--              Import > choose this file > Go
--  CLI:        mysql -u root parking_db < database_2_routines.sql
--
--  Import database_1_tables.sql FIRST before this file.
-- ============================================================

DELIMITER $$

-- Drop existing objects before recreating (safe re-import)
DROP FUNCTION  IF EXISTS fn_calculate_fee$$
DROP FUNCTION  IF EXISTS fn_get_duration_hours$$
DROP FUNCTION  IF EXISTS fn_count_available_slots$$
DROP PROCEDURE IF EXISTS sp_do_checkin$$
DROP PROCEDURE IF EXISTS sp_do_checkout$$
DROP TRIGGER   IF EXISTS trg_after_checkin$$
DROP TRIGGER   IF EXISTS trg_after_checkout$$
DROP TRIGGER   IF EXISTS trg_prevent_double_park$$


-- FUNCTION 1: fn_calculate_fee
-- Calculates total parking fee. Rounds up to nearest hour, minimum 1 hour.
-- Example: SELECT fn_calculate_fee('2024-01-01 08:00:00', '2024-01-01 10:30:00', 5000);
CREATE FUNCTION fn_calculate_fee(
    p_check_in  DATETIME,
    p_check_out DATETIME,
    p_rate      DECIMAL(10,2)
)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE v_seconds BIGINT;
    DECLARE v_hours   INT;
    SET v_seconds = TIMESTAMPDIFF(SECOND, p_check_in, p_check_out);
    IF v_seconds < 0 THEN SET v_seconds = 0; END IF;
    SET v_hours = GREATEST(1, CEIL(v_seconds / 3600));
    RETURN v_hours * p_rate;
END$$

-- FUNCTION 2: fn_get_duration_hours
-- Returns parking duration in whole hours (rounded up, minimum 1).
-- Example: SELECT fn_get_duration_hours(check_in_time, check_out_time) FROM parking_transactions;
CREATE FUNCTION fn_get_duration_hours(
    p_check_in  DATETIME,
    p_check_out DATETIME
)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_seconds BIGINT;
    SET v_seconds = TIMESTAMPDIFF(SECOND, p_check_in, p_check_out);
    IF v_seconds < 0 THEN SET v_seconds = 0; END IF;
    RETURN GREATEST(1, CEIL(v_seconds / 3600));
END$$

-- FUNCTION 3: fn_count_available_slots
-- Returns available slot count. Pass NULL for all zones, or 'A' for a specific zone.
-- Example: SELECT fn_count_available_slots(NULL);
--          SELECT fn_count_available_slots('A');
CREATE FUNCTION fn_count_available_slots(
    p_zone VARCHAR(10)
)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_count INT;
    IF p_zone IS NULL THEN
        SELECT COUNT(*) INTO v_count FROM parking_slots WHERE slot_status = 'available';
    ELSE
        SELECT COUNT(*) INTO v_count FROM parking_slots WHERE slot_status = 'available' AND zone = p_zone;
    END IF;
    RETURN v_count;
END$$

-- ============================================================
--  STORED PROCEDURES
-- ============================================================

-- PROCEDURE 1: sp_do_checkin
-- Full atomic check-in: validates slot & vehicle, upserts vehicle,
-- inserts transaction, marks slot occupied. Uses LEAVE label for clean exits.
-- Example: CALL sp_do_checkin('B 1234 ABC', 2, 3, 1, @txn_id, @slot_code, @rate, @ok, @msg);
--          SELECT @ok, @msg, @txn_id, @slot_code, @rate;
CREATE PROCEDURE sp_do_checkin(
    IN  p_plate       VARCHAR(20),
    IN  p_type_id     INT,
    IN  p_slot_id     INT,
    IN  p_operator_id INT,
    OUT p_txn_id      INT,
    OUT p_slot_code   VARCHAR(10),
    OUT p_rate        DECIMAL(10,2),
    OUT p_success     TINYINT(1),
    OUT p_message     VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_vehicle_id INT           DEFAULT NULL;
    DECLARE v_rate       DECIMAL(10,2) DEFAULT NULL;
    DECLARE v_slot_code  VARCHAR(10)   DEFAULT NULL;
    DECLARE v_existing   INT           DEFAULT 0;

    SET p_success = 0;
    SET p_txn_id  = NULL;
    SET p_message = '';

    -- 1. Check slot is available
    SELECT slot_code INTO v_slot_code
    FROM parking_slots
    WHERE slot_id = p_slot_id AND slot_status = 'available'
    LIMIT 1;

    IF v_slot_code IS NULL THEN
        SET p_message = 'Selected slot is no longer available';
        LEAVE proc_label;
    END IF;

    -- 2. Check vehicle not already parked
    SELECT COUNT(*) INTO v_existing
    FROM parking_transactions t
    JOIN vehicles v ON t.vehicle_id = v.vehicle_id
    WHERE v.plate_number = UPPER(TRIM(p_plate))
      AND t.check_out_time IS NULL;

    IF v_existing > 0 THEN
        SET p_message = 'This vehicle is already parked';
        LEAVE proc_label;
    END IF;

    -- 3. Get rate
    SELECT rate_per_hour INTO v_rate
    FROM vehicle_types WHERE type_id = p_type_id LIMIT 1;

    IF v_rate IS NULL THEN
        SET p_message = 'Invalid vehicle type';
        LEAVE proc_label;
    END IF;

    -- 4. Get or create vehicle
    SELECT vehicle_id INTO v_vehicle_id
    FROM vehicles WHERE plate_number = UPPER(TRIM(p_plate)) LIMIT 1;

    IF v_vehicle_id IS NULL THEN
        INSERT INTO vehicles (plate_number, type_id)
        VALUES (UPPER(TRIM(p_plate)), p_type_id);
        SET v_vehicle_id = LAST_INSERT_ID();
    END IF;

    -- 5. Insert transaction (trg_after_checkin auto-logs to parking_logs)
    INSERT INTO parking_transactions (vehicle_id, slot_id, rate_snapshot, operator_id)
    VALUES (v_vehicle_id, p_slot_id, v_rate, p_operator_id);

    SET p_txn_id    = LAST_INSERT_ID();
    SET p_slot_code = v_slot_code;
    SET p_rate      = v_rate;

    -- 6. Mark slot occupied
    UPDATE parking_slots SET slot_status = 'occupied' WHERE slot_id = p_slot_id;

    SET p_success = 1;
    SET p_message = 'Check-in successful';
END$$

-- PROCEDURE 2: sp_do_checkout
-- Full atomic check-out: fetches transaction, calculates fee via stored functions,
-- updates record, frees slot. trg_after_checkout auto-logs to parking_logs.
-- Example: CALL sp_do_checkout(7, 'cash', 1, @plate, @slot, @fee, @hours, @ok, @msg);
--          SELECT @ok, @msg, @plate, @slot, @fee, @hours;
CREATE PROCEDURE sp_do_checkout(
    IN  p_txn_id         INT,
    IN  p_payment_method VARCHAR(20),
    IN  p_operator_id    INT,
    OUT p_plate          VARCHAR(20),
    OUT p_slot_code      VARCHAR(10),
    OUT p_fee            DECIMAL(10,2),
    OUT p_hours          INT,
    OUT p_success        TINYINT(1),
    OUT p_message        VARCHAR(255)
)
proc_label: BEGIN
    DECLARE v_slot_id  INT;
    DECLARE v_check_in DATETIME;
    DECLARE v_rate     DECIMAL(10,2);
    DECLARE v_now      DATETIME;

    SET p_success = 0;
    SET p_message = '';
    SET v_now     = NOW();

    -- 1. Fetch active transaction
    SELECT v.plate_number, s.slot_id, s.slot_code, t.check_in_time, t.rate_snapshot
    INTO   p_plate, v_slot_id, p_slot_code, v_check_in, v_rate
    FROM parking_transactions t
    JOIN vehicles v      ON t.vehicle_id = v.vehicle_id
    JOIN parking_slots s ON t.slot_id    = s.slot_id
    WHERE t.transaction_id = p_txn_id AND t.check_out_time IS NULL
    LIMIT 1;

    IF p_plate IS NULL THEN
        SET p_message = 'Active transaction not found';
        LEAVE proc_label;
    END IF;

    -- 2. Calculate using stored functions
    SET p_fee   = fn_calculate_fee(v_check_in, v_now, v_rate);
    SET p_hours = fn_get_duration_hours(v_check_in, v_now);

    -- 3. Update transaction (trg_after_checkout auto-logs to parking_logs)
    UPDATE parking_transactions
    SET check_out_time = v_now,
        duration_hours = p_hours,
        fee_total      = p_fee,
        payment_status = 'paid',
        payment_method = p_payment_method,
        operator_id    = p_operator_id
    WHERE transaction_id = p_txn_id;

    -- 4. Free the slot
    UPDATE parking_slots SET slot_status = 'available' WHERE slot_id = v_slot_id;

    SET p_success = 1;
    SET p_message = 'Check-out successful';
END$$

-- ============================================================
--  TRIGGERS
-- ============================================================

-- TRIGGER 1: trg_after_checkin
-- Fires AFTER INSERT on parking_transactions.
-- Auto-writes a CHECK_IN audit record to parking_logs.
CREATE TRIGGER trg_after_checkin
AFTER INSERT ON parking_transactions
FOR EACH ROW
BEGIN
    DECLARE v_plate VARCHAR(20);
    DECLARE v_slot  VARCHAR(10);
    SELECT plate_number INTO v_plate FROM vehicles      WHERE vehicle_id = NEW.vehicle_id;
    SELECT slot_code    INTO v_slot  FROM parking_slots WHERE slot_id    = NEW.slot_id;
    INSERT INTO parking_logs (event_type, transaction_id, plate_number, slot_code, detail)
    VALUES (
        'CHECK_IN', NEW.transaction_id, v_plate, v_slot,
        CONCAT('Checked in at slot ', v_slot, ' | Rate: Rp ', FORMAT(NEW.rate_snapshot, 0), '/hour')
    );
END$$

-- TRIGGER 2: trg_after_checkout
-- Fires AFTER UPDATE on parking_transactions.
-- Auto-writes CHECK_OUT audit record when check_out_time changes from NULL.
CREATE TRIGGER trg_after_checkout
AFTER UPDATE ON parking_transactions
FOR EACH ROW
BEGIN
    DECLARE v_plate VARCHAR(20);
    DECLARE v_slot  VARCHAR(10);
    IF OLD.check_out_time IS NULL AND NEW.check_out_time IS NOT NULL THEN
        SELECT plate_number INTO v_plate FROM vehicles      WHERE vehicle_id = NEW.vehicle_id;
        SELECT slot_code    INTO v_slot  FROM parking_slots WHERE slot_id    = NEW.slot_id;
        INSERT INTO parking_logs (event_type, transaction_id, plate_number, slot_code, detail)
        VALUES (
            'CHECK_OUT', NEW.transaction_id, v_plate, v_slot,
            CONCAT('Checked out | Duration: ', NEW.duration_hours, ' hr(s)',
                   ' | Fee: Rp ', FORMAT(NEW.fee_total, 0),
                   ' | Payment: ', IFNULL(NEW.payment_method, '-'))
        );
    END IF;
END$$

-- TRIGGER 3: trg_prevent_double_park
-- Fires BEFORE INSERT on parking_transactions.
-- Database-level guard: blocks a second active session for the same vehicle,
-- even if the application-level check is somehow bypassed.
CREATE TRIGGER trg_prevent_double_park
BEFORE INSERT ON parking_transactions
FOR EACH ROW
BEGIN
    DECLARE v_active INT DEFAULT 0;
    SELECT COUNT(*) INTO v_active
    FROM parking_transactions
    WHERE vehicle_id = NEW.vehicle_id AND check_out_time IS NULL;
    IF v_active > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Vehicle already has an active parking session';
    END IF;
END$$

DELIMITER ;

-- SEED DATA
-- ============================================================

INSERT INTO vehicle_types (type_name, rate_per_hour, description) VALUES
('Motorcycle',  2000, 'All types of motorcycles & scooters'),
('Car',         5000, 'Sedan, SUV, MPV'),
('Minibus',     8000, 'ELF, Angkot, small buses'),
('Truck',      12000, 'Heavy trucks & large vehicles');

INSERT INTO parking_slots (slot_code, zone, floor, slot_status, type_id) VALUES
('A-01','A',1,'available',NULL), ('A-02','A',1,'available',NULL),
('A-03','A',1,'available',NULL), ('A-04','A',1,'available',NULL),
('A-05','A',1,'available',NULL), ('A-06','A',1,'available',NULL),
('B-01','B',1,'available',NULL), ('B-02','B',1,'available',NULL),
('B-03','B',1,'available',NULL), ('B-04','B',1,'available',NULL),
('C-01','C',2,'available',NULL), ('C-02','C',2,'available',NULL),
('C-03','C',2,'available',NULL), ('C-04','C',2,'maintenance',NULL),
('M-01','M',1,'available',1),   ('M-02','M',1,'available',1),
('M-03','M',1,'available',1),   ('M-04','M',1,'available',1),
('M-05','M',1,'available',1),   ('M-06','M',1,'available',1);

INSERT INTO operators (username, password, full_name, role) VALUES
('admin', '$2y$10$placeholder_hash_admin', 'Administrator', 'admin'),
('operator1', '$2y$10$placeholder_hash_op1', 'Budi Santoso', 'operator');

INSERT INTO owners (full_name, phone, email) VALUES
('Andi Wijaya',   '08123456789', 'andi@email.com'),
('Siti Rahayu',   '08234567890', 'siti@email.com'),
('Doni Pratama',  '08345678901', NULL);

INSERT INTO vehicles (plate_number, type_id, brand, model, color, owner_id) VALUES
('B 1234 ABC', 2, 'Toyota',  'Avanza',   'Silver',  1),
('B 5678 DEF', 1, 'Honda',   'Vario',    'Black',   2),
('D 9012 GHI', 2, 'Daihatsu','Xenia',    'White',   3),
('B 3344 JKL', 1, 'Yamaha',  'NMAX',     'Blue',    NULL),
('F 5566 MNO', 2, 'Suzuki',  'Ertiga',   'Red',     NULL);


-- ============================================================
--  PASSWORD SETUP (bcrypt hashes)
-- ============================================================
-- ============================================================
--  PASSWORD PATCH
--  File: database_3_passwords.sql
--
--  Import ini SETELAH database_1_tables.sql
--
--  Default login:
--  Username : admin       Password : admin123
--  Username : operator1   Password : operator123
--
--  Ganti password setelah login pertama!
-- ============================================================

USE parking_db;

UPDATE operators SET password = '$2y$10$RkloaRyTTQB7m0mW2Qu4lu9H7eLDemt5U0NZxPwQonUT0YUoe6v86' WHERE username = 'admin';
UPDATE operators SET password = '$2y$10$wzbtRKYrMTKyXq6z2mupQO5bwSksNjchB2P5Lq4xpTH70f2.isnF2' WHERE username = 'operator1';

