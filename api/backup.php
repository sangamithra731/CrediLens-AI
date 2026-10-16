<?php
/**
 * CrediLens AI — Database Backup & Recovery API
 * Handles database export, backup storage, and restore procedures.
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

// Only admin can access backup/restore
if ($_SESSION['user_role'] !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Administrative privileges required'], 403);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createBackup();
        break;
    case 'list':
        listBackups();
        break;
    case 'download':
        downloadBackup();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid backup action'], 400);
}

function createBackup() {
    $pdo = getDBConnection();
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Ymd_His');
    $filename = 'credilens_backup_' . $timestamp . '.sql';
    $filepath = $backupDir . '/' . $filename;

    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sqlDump = "-- ============================================\n";
    $sqlDump .= "-- CrediLens AI Database Backup\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Platform Version: " . APP_VERSION . "\n";
    $sqlDump .= "-- ============================================\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $totalRecords = 0;

    foreach ($tables as $table) {
        // Table structure
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sqlDump .= "-- Structure for table `$table`\n";
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlDump .= $createStmt[1] . ";\n\n";

        // Table data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $totalRecords += count($rows);

        if (!empty($rows)) {
            $sqlDump .= "-- Data for table `$table` (" . count($rows) . " rows)\n";
            $columns = array_keys($rows[0]);
            $colList = implode('`, `', $columns);

            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $valSets = [];
                foreach ($chunk as $r) {
                    $escaped = array_map(function($v) use ($pdo) {
                        return ($v === null) ? 'NULL' : $pdo->quote($v);
                    }, array_values($r));
                    $valSets[] = "(" . implode(', ', $escaped) . ")";
                }
                $sqlDump .= "INSERT INTO `$table` (`$colList`) VALUES\n" . implode(",\n", $valSets) . ";\n";
            }
            $sqlDump .= "\n";
        }
    }

    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    $written = @file_put_contents($filepath, $sqlDump);
    if ($written === false) {
        jsonResponse(['success' => false, 'message' => 'Failed to write backup file to disk.'], 500);
    }

    $filesize = filesize($filepath);

    // Save metadata
    $ins = $pdo->prepare("INSERT INTO system_backups (filename, filesize_bytes, tables_count, records_count, created_by, status)
        VALUES (?, ?, ?, ?, ?, 'success')");
    $ins->execute([$filename, $filesize, count($tables), $totalRecords, $_SESSION['user_id']]);

    logAudit($_SESSION['user_id'], 'backup_created', 'system', $pdo->lastInsertId());

    jsonResponse([
        'success' => true,
        'message' => "Backup '$filename' created successfully!",
        'details' => [
            'filename' => $filename,
            'filesize_kb' => round($filesize / 1024, 2),
            'tables_count' => count($tables),
            'records_count' => $totalRecords,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

function listBackups() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT sb.*, u.full_name as creator_name 
        FROM system_backups sb 
        LEFT JOIN users u ON sb.created_by = u.id 
        ORDER BY sb.created_at DESC");
    jsonResponse(['success' => true, 'backups' => $stmt->fetchAll()]);
}

function downloadBackup() {
    $filename = basename($_GET['file'] ?? '');
    $filepath = __DIR__ . '/../backups/' . $filename;

    if (!file_exists($filepath)) {
        jsonResponse(['success' => false, 'message' => 'Backup file not found'], 404);
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}
