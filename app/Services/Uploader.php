<?php
declare(strict_types=1);

/** Upload aman: cek error, ukuran, MIME asli (finfo), nama acak, ekstensi dari MIME. */
final class Uploader
{
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return string nama file tersimpan (tanpa path) */
    public static function saveImage(array $file, string $dir, int $maxBytes = 3_145_728): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload gagal / file belum dipilih.');
        if ($file['size'] > $maxBytes) throw new RuntimeException('Ukuran file maksimal ' . round($maxBytes / 1048576) . ' MB.');
        if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('File tidak valid.');

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::EXT[$mime]) || !@getimagesize($file['tmp_name'])) throw new RuntimeException('Format harus JPG, PNG, atau WEBP.');

        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Folder upload tidak dapat dibuat.');
        $name = bin2hex(random_bytes(16)) . '.' . self::EXT[$mime];
        if (!move_uploaded_file($file['tmp_name'], rtrim($dir, '/') . '/' . $name)) throw new RuntimeException('Gagal menyimpan file.');
        return $name;
    }
}
