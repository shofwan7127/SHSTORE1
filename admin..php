<?php
// File: admin.php (Opsional)
// Panel admin untuk melihat data

session_start();
$password = 'admin123'; // Ganti dengan password yang aman

// Simple authentication
if (!isset($_SESSION['loggedin'])) {
    if ($_POST['password'] ?? '' === $password) {
        $_SESSION['loggedin'] = true;
    } else {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Login Admin</title>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; }
                .login-box { max-width: 400px; margin: 100px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
                input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; }
                button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Login Admin</h2>
                <form method="POST">
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>';
        exit;
    }
}

// Read data
$dataFile = 'data/nota_history.json';
$data = [];
if (file_exists($dataFile)) {
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true) ?: [];
}

// Export to CSV
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="nota_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice', 'ID Pemesan', 'Jenis Produk', 'Durasi', 'Harga', 'Status', 'Pembayaran', 'Waktu Masuk', 'Waktu Selesai']);
    
    foreach ($data as $item) {
        fputcsv($output, [
            $item['nomorInvoice'] ?? '',
            $item['idPemesan'] ?? '',
            $item['jenisProduk'] ?? '',
            $item['jumlahJamJoki'] ?? '',
            $item['hargaProduk'] ?? '',
            $item['statusTransaksi'] ?? '',
            $item['statusPembayaran'] ?? '',
            $item['currentDateTime'] ?? '',
            $item['completionDateTime'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Delete data
if (isset($_GET['delete']) && isset($_GET['invoice'])) {
    $newData = array_filter($data, function($item) {
        return $item['nomorInvoice'] !== $_GET['invoice'];
    });
    
    file_put_contents($dataFile, json_encode(array_values($newData), JSON_PRETTY_PRINT));
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - SH Store</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        tr:hover { background: #f9f9f9; }
        .btn { padding: 8px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; }
        .btn-danger { background: #f44336; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-box { flex: 1; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; }
        .status-success { color: green; }
        .status-proccess { color: orange; }
        .status-pending { color: blue; }
        .status-failed { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Panel - SH Store</h1>
        <p>Total Transaksi: <?= count($data) ?></p>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= count(array_filter($data, fn($item) => ($item['statusTransaksi'] ?? '') === 'Success')) ?></div>
                <div>Success</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= count(array_filter($data, fn($item) => ($item['statusTransaksi'] ?? '') === 'Proccess')) ?></div>
                <div>Proccess</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= count(array_filter($data, fn($item) => ($item['statusTransaksi'] ?? '') === 'Pending')) ?></div>
                <div>Pending</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= count(array_filter($data, fn($item) => ($item['statusTransaksi'] ?? '') === 'Failed')) ?></div>
                <div>Failed</div>
            </div>
        </div>
        
        <a href="?export=1" class="btn">Export CSV</a>
        <a href="index.html" class="btn" style="background: #2196F3;">Kembali ke Aplikasi</a>
        <a href="?logout=1" class="btn" style="background: #ff9800;">Logout</a>
        
        <table>
            <tr>
                <th>Invoice</th>
                <th>ID Pemesan</th>
                <th>Produk</th>
                <th>Durasi</th>
                <th>Harga</th>
                <th>Status</th>
                <th>Pembayaran</th>
                <th>Waktu</th>
                <th>Aksi</th>
            </tr>
            <?php foreach ($data as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['nomorInvoice'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['idPemesan'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['jenisProduk'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['jumlahJamJoki'] ?? '') ?> jam</td>
                <td><?= htmlspecialchars($item['hargaProduk'] ?? '') ?></td>
                <td class="status-<?= strtolower($item['statusTransaksi'] ?? '') ?>">
                    <?= htmlspecialchars($item['statusTransaksi'] ?? '') ?>
                </td>
                <td><?= htmlspecialchars($item['statusPembayaran'] ?? '') ?></td>
                <td><?= htmlspecialchars($item['currentDateTime'] ?? '') ?></td>
                <td>
                    <a href="?delete=1&invoice=<?= urlencode($item['nomorInvoice'] ?? '') ?>" 
                       class="btn btn-danger" 
                       onclick="return confirm('Hapus nota ini?')">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>