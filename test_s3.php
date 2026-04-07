<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

try {
    echo "Mencoba mengunggah file tes ke Supabase S3...\n";
    $result = Storage::disk('s3')->put('test.txt', 'Halo Supabase ini tes koneksi.');
    
    if ($result) {
        echo "BERHASIL! Koneksi S3 kamu aman.\n";
        echo "URL File: " . Storage::disk('s3')->url('test.txt') . "\n";
    } else {
        echo "GAGAL: Put file mengembalikan hasil False tanpa error spesifik.\n";
    }
} catch (\Throwable $e) {
    echo "ERROR TERTANGKAP:\n";
    echo $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "ALASAN SEBELUMNYA: " . $e->getPrevious()->getMessage() . "\n";
    }
    echo "Tipe Error: " . get_class($e) . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
