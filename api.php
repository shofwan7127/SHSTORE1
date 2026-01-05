<?php
// File: api.php
// Backend untuk menyimpan data ke file TXT

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Konfigurasi
define('DATA_FILE', 'data/nota_history.json');
define('BACKUP_DIR', 'data/backups/');

// Buat folder jika belum ada
if (!file_exists('data')) {
    mkdir('data', 0777, true);
}
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0777, true);
}

// Fungsi untuk membaca data dari file
function readDataFromFile() {
    if (!file_exists(DATA_FILE)) {
        return [];
    }
    
    $content = file_get_contents(DATA_FILE);
    if (empty($content)) {
        return [];
    }
    
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Fungsi untuk menyimpan data ke file
function saveDataToFile($data) {
    // Buat backup sebelum menyimpan
    if (file_exists(DATA_FILE)) {
        $backupFile = BACKUP_DIR . 'backup_' . date('Y-m-d_H-i-s') . '.json';
        copy(DATA_FILE, $backupFile);
    }
    
    // Hapus backup lama (simpan 7 hari terakhir saja)
    $backupFiles = glob(BACKUP_DIR . 'backup_*.json');
    $oneWeekAgo = strtotime('-7 days');
    
    foreach ($backupFiles as $backupFile) {
        if (filemtime($backupFile) < $oneWeekAgo) {
            unlink($backupFile);
        }
    }
    
    // Simpan data baru
    $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(DATA_FILE, $jsonData) !== false;
}

// Fungsi untuk membuat file log
function logActivity($action, $invoice = '', $status = 'success') {
    $logFile = 'data/activity_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$status] $action" . ($invoice ? " - Invoice: $invoice" : "") . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Main handler
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'getHistory':
            $data = readDataFromFile();
            logActivity('Mengambil riwayat transaksi');
            echo json_encode($data);
            break;
            
        case 'saveNota':
            $notaData = json_decode($_POST['notaData'] ?? '[]', true);
            
            if (empty($notaData)) {
                throw new Exception('Data nota tidak valid');
            }
            
            $allData = readDataFromFile();
            $allData[] = $notaData;
            
            if (saveDataToFile($allData)) {
                logActivity('Menyimpan nota baru', $notaData['nomorInvoice'] ?? '');
                echo json_encode(['success' => true, 'message' => 'Nota berhasil disimpan']);
            } else {
                throw new Exception('Gagal menyimpan ke file');
            }
            break;
            
        case 'updateNota':
            $notaData = json_decode($_POST['notaData'] ?? '[]', true);
            
            if (empty($notaData) || !isset($notaData['nomorInvoice'])) {
                throw new Exception('Data nota tidak valid');
            }
            
            $allData = readDataFromFile();
            $found = false;
            
            foreach ($allData as &$item) {
                if ($item['nomorInvoice'] === $notaData['nomorInvoice']) {
                    $item = $notaData;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                if (saveDataToFile($allData)) {
                    logActivity('Memperbarui nota', $notaData['nomorInvoice']);
                    echo json_encode(['success' => true, 'message' => 'Nota berhasil diperbarui']);
                } else {
                    throw new Exception('Gagal memperbarui file');
                }
            } else {
                throw new Exception('Nota tidak ditemukan');
            }
            break;
            
        case 'deleteNota':
            $invoiceNumber = $_POST['invoiceNumber'] ?? '';
            
            if (empty($invoiceNumber)) {
                throw new Exception('Nomor invoice tidak valid');
            }
            
            $allData = readDataFromFile();
            $newData = array_filter($allData, function($item) use ($invoiceNumber) {
                return $item['nomorInvoice'] !== $invoiceNumber;
            });
            
            // Reset array index
            $newData = array_values($newData);
            
            if (saveDataToFile($newData)) {
                logActivity('Menghapus nota', $invoiceNumber);
                echo json_encode(['success' => true, 'message' => 'Nota berhasil dihapus']);
            } else {
                throw new Exception('Gagal menghapus dari file');
            }
            break;
            
        case 'exportCSV':
            $data = readDataFromFile();
            
            if (empty($data)) {
                throw new Exception('Tidak ada data untuk diexport');
            }
            
            $csvData = "Invoice,ID Pemesan,Jenis Produk,Durasi,Harga,Status Transaksi,Status Pembayaran,Waktu Masuk,Waktu Selesai\n";
            
            foreach ($data as $item) {
                $csvData .= sprintf(
                    '%s,%s,%s,%s jam,%s,%s,%s,%s,%s',
                    $item['nomorInvoice'] ?? '',
                    $item['idPemesan'] ?? '',
                    $item['jenisProduk'] ?? '',
                    $item['jumlahJamJoki'] ?? '',
                    $item['hargaProduk'] ?? '',
                    $item['statusTransaksi'] ?? '',
                    $item['statusPembayaran'] ?? '',
                    $item['currentDateTime'] ?? '',
                    $item['completionDateTime'] ?? ''
                ) . "\n";
            }
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="nota_export_' . date('Y-m-d') . '.csv"');
            echo $csvData;
            logActivity('Export data ke CSV');
            exit;
            
        case 'getStats':
            $data = readDataFromFile();
            
            $stats = [
                'total' => count($data),
                'success' => 0,
                'proccess' => 0,
                'pending' => 0,
                'failed' => 0,
                'paid' => 0,
                'unpaid' => 0
            ];
            
            foreach ($data as $item) {
                if (isset($item['statusTransaksi'])) {
                    switch ($item['statusTransaksi']) {
                        case 'Success': $stats['success']++; break;
                        case 'Proccess': $stats['proccess']++; break;
                        case 'Pending': $stats['pending']++; break;
                        case 'Failed': $stats['failed']++; break;
                    }
                }
                
                if (isset($item['statusPembayaran'])) {
                    switch ($item['statusPembayaran']) {
                        case 'Paid': $stats['paid']++; break;
                        case 'Unpaid': $stats['unpaid']++; break;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            throw new Exception('Aksi tidak dikenali');
    }
    
} catch (Exception $e) {
    logActivity($e->getMessage(), '', 'error');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>