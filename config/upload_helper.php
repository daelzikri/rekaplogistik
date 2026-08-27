<?php
/**
 * Image Upload & Hybrid Multi-Tier Conversion Helper
 * Sistem Stok & Serah Terima Barang Logistik
 */

/**
 * Generate UUID v4 String
 * @return string
 */
function generate_uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Memeriksa Keamanan File Upload (Magic Bytes, Payload, Extension, Size)
 * @param array $file Single file array from $_FILES
 * @param int $maxSizeMax 10MB
 * @return array ['valid' => bool, 'error' => string, 'mime' => string, 'ext' => string]
 */
function validate_uploaded_image(array $file, int $maxSizeBytes = 10485760): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Struktur file upload tidak valid.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas upload server (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form (MAX_FILE_SIZE).',
            UPLOAD_ERR_PARTIAL    => 'File hanya terunggah sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file ke disk server.',
            UPLOAD_ERR_EXTENSION  => 'Upload file dihentikan oleh ekstensi PHP.'
        ];
        return ['valid' => false, 'error' => $uploadErrors[$file['error']] ?? 'Terjadi kesalahan saat upload file.'];
    }

    if ($file['size'] > $maxSizeBytes) {
        return ['valid' => false, 'error' => 'Ukuran file terlalu besar (Maksimal 10 MB).'];
    }

    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath)) {
        return ['valid' => false, 'error' => 'File yang diunggah tidak valid atau mencurigakan.'];
    }

    // Extension Scrutiny
    $originalName = strtolower($file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $forbiddenExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'cgi', 'pl', 'exe', 'asp', 'aspx', 'jsp', 'sh', 'bat', 'cmd', 'htaccess'];
    if (in_array($extension, $forbiddenExts, true)) {
        return ['valid' => false, 'error' => 'Ekstensi file dilarang untuk alasan keamanan.'];
    }

    // Check for double extension (e.g., image.php.jpg)
    $nameParts = explode('.', $originalName);
    if (count($nameParts) > 2) {
        foreach (array_slice($nameParts, 1, -1) as $middleExt) {
            if (in_array(strtolower($middleExt), $forbiddenExts, true)) {
                return ['valid' => false, 'error' => 'Nama file mengandung ekstensi ganda yang berbahaya.'];
            }
        }
    }

    // Magic Bytes MIME check
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);

    $allowedMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/webp',
        'image/heic', 'image/heif', 'image/x-heic', 'image/x-heif'
    ];

    if (!in_array($mime, $allowedMimes, true)) {
        return ['valid' => false, 'error' => "Tipe file tidak didukung ($mime). Hanya foto (JPG, PNG, WEBP, HEIC) yang diizinkan."];
    }

    // Anti PHP Script Payload inside image content
    $contentSnippet = file_get_contents($tmpPath, false, null, 0, 4096);
    if (preg_match('/<\?(php|=)/i', $contentSnippet)) {
        return ['valid' => false, 'error' => 'File terdeteksi mengandung skrip PHP terselubung. Upload ditolak!'];
    }

    return ['valid' => true, 'error' => '', 'mime' => $mime, 'ext' => $extension];
}

/**
 * Hybrid Multi-Tier Image Conversion to WebP
 * Tier 1: Client HEIC2any (sudah dikonversi ke JPEG/PNG di browser)
 * Tier 2: PHP Imagick Extension
 * Tier 3: Python CLI script (convert_heic.py via pillow-heif)
 * Tier 4: PHP GD Library Fallback
 *
 * @param string $sourceTmpPath
 * @param string $destPath
 * @param string $mime
 * @param string $ext
 * @return bool
 */
function convert_and_save_image(string $sourceTmpPath, string $destPath, string $mime, string $ext): bool {
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // If extension is HEIC/HEIF, attempt HEIC pipeline
    $isHeic = in_array($ext, ['heic', 'heif'], true) || strpos($mime, 'heic') !== false || strpos($mime, 'heif') !== false;

    // TIER 2: Imagick
    if (extension_loaded('imagick')) {
        try {
            $imagick = new Imagick($sourceTmpPath);
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(85);
            $imagick->stripImage(); // Remove EXIF metadata for security & size reduction
            $result = $imagick->writeImage($destPath);
            $imagick->clear();
            $imagick->destroy();
            if ($result && file_exists($destPath) && filesize($destPath) > 0) {
                return true;
            }
        } catch (Exception $e) {
            error_log("Imagick conversion failed: " . $e->getMessage());
        }
    }

    // TIER 3: Python CLI Fallback for HEIC
    if ($isHeic) {
        $pythonScript = __DIR__ . '/../scripts/convert_heic.py';
        if (file_exists($pythonScript)) {
            $cmd = sprintf(
                'python %s %s %s 2>&1',
                escapeshellarg($pythonScript),
                escapeshellarg($sourceTmpPath),
                escapeshellarg($destPath)
            );
            exec($cmd, $output, $returnCode);
            if ($returnCode === 0 && file_exists($destPath) && filesize($destPath) > 0) {
                return true;
            } else {
                error_log("Python HEIC converter output: " . implode("\n", $output));
            }
        }
    }

    // TIER 4: GD Library Fallback
    if (function_exists('imagecreatefromstring')) {
        $imgData = file_get_contents($sourceTmpPath);
        $image = @imagecreatefromstring($imgData);
        if ($image !== false) {
            // Preserve transparency if PNG/WEBP
            imagealphablending($image, true);
            imagesavealpha($image, true);

            $saved = imagewebp($image, $destPath, 85);
            imagedestroy($image);
            if ($saved && file_exists($destPath) && filesize($destPath) > 0) {
                return true;
            }
        }
    }

    // Last Fallback: Direct Copy if format is standard (JPG/PNG/WEBP)
    if (!$isHeic) {
        return copy($sourceTmpPath, $destPath);
    }

    return false;
}

/**
 * Normalisasi dan Memproses Multi File Upload ($_FILES)
 * @param array $filesInput Raw $_FILES['bukti_foto'] or $_FILES['foto_barang']
 * @param string $targetSubDir Subfolder inside uploads/ ('barang' or 'transaksi')
 * @return array List of uploaded files metadata [['file_path', 'format_asli', 'nama_file_server']]
 */
function process_multiple_image_uploads(array $filesInput, string $targetSubDir): array {
    $results = [];
    $baseUploadDir = __DIR__ . '/../public/uploads/' . trim($targetSubDir, '/') . '/';

    // Restructure $_FILES array if multiple files uploaded
    $fileList = [];
    if (is_array($filesInput['name'])) {
        $count = count($filesInput['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($filesInput['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            $fileList[] = [
                'name'     => $filesInput['name'][$i],
                'type'     => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error'    => $filesInput['error'][$i],
                'size'     => $filesInput['size'][$i]
            ];
        }
    } else {
        if ($filesInput['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileList[] = $filesInput;
        }
    }

    foreach ($fileList as $file) {
        $val = validate_uploaded_image($file);
        if (!$val['valid']) {
            throw new Exception("Gagal mengunggah foto '{$file['name']}': " . $val['error']);
        }

        $uuid = generate_uuid4();
        $serverFileName = $uuid . '.webp';
        $fullDestPath = $baseUploadDir . $serverFileName;
        $relativePath = 'uploads/' . trim($targetSubDir, '/') . '/' . $serverFileName;

        $success = convert_and_save_image($file['tmp_name'], $fullDestPath, $val['mime'], $val['ext']);
        if (!$success) {
            throw new Exception("Gagal memproses dan menyimpan file gambar '{$file['name']}'.");
        }

        $results[] = [
            'file_path'        => $relativePath,
            'format_asli'      => strtoupper($val['ext']),
            'nama_file_server' => $serverFileName
        ];
    }

    return $results;
}
