<?php
/**
 * Thamani High School - Cloudinary Integration Helper
 * --------------------------------------------------
 * Direct REST API cURL client for uploading images, documents (PDF, DOCX, XLSX),
 * and media to Cloudinary.
 */

if (!function_exists('cloudinary_get_config')) {
    function cloudinary_get_config() {
        $cloudName    = getenv('CLOUDINARY_CLOUD_NAME') ?: 'thamaniacademy';
        $apiKey       = getenv('CLOUDINARY_API_KEY')    ?: '555989584672519';
        $apiSecret    = getenv('CLOUDINARY_API_SECRET') ?: 'dcmq5dQZfmysvewtwrAZTsCeI3w';
        $uploadPreset = getenv('CLOUDINARY_UPLOAD_PRESET') ?: '';

        // If CLOUDINARY_URL is provided, parse it (format: cloudinary://API_KEY:API_SECRET@CLOUD_NAME)
        $cUrl = getenv('CLOUDINARY_URL');
        if ($cUrl && str_starts_with($cUrl, 'cloudinary://')) {
            $parsed = parse_url($cUrl);
            if ($parsed) {
                if (!empty($parsed['user'])) $apiKey = $parsed['user'];
                if (!empty($parsed['pass'])) $apiSecret = $parsed['pass'];
                if (!empty($parsed['host'])) $cloudName = $parsed['host'];
            }
        }

        return [
            'cloud_name'    => $cloudName,
            'api_key'       => $apiKey,
            'api_secret'    => $apiSecret,
            'upload_preset' => $uploadPreset
        ];
    }
}

if (!function_exists('cloudinary_upload')) {
    /**
     * Upload a local file to Cloudinary
     *
     * @param string $tmpFilePath Path to local file
     * @param string $originalFileName Original filename
     * @param string $typeHint Type hint ('image', 'raw', 'auto')
     * @return array Result array ['success' => bool, 'secure_url' => string, 'public_id' => string, 'error' => string]
     */
    function cloudinary_upload($tmpFilePath, $originalFileName, $typeHint = 'auto') {
        if (!file_exists($tmpFilePath) || !is_readable($tmpFilePath)) {
            return ['success' => false, 'error' => 'Local file does not exist or is not readable.'];
        }

        $cfg = cloudinary_get_config();
        if (empty($cfg['cloud_name']) || (empty($cfg['api_key']) && empty($cfg['upload_preset']))) {
            return ['success' => false, 'error' => 'Cloudinary credentials are not configured.'];
        }

        // Determine resource type based on file extension
        $ext = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $videoExts = ['mp4', 'webm', 'ogg', 'mov', 'avi'];

        if (in_array($ext, $imageExts, true)) {
            $resourceType = 'image';
        } elseif (in_array($ext, $videoExts, true)) {
            $resourceType = 'video';
        } else {
            // PDFs, DOC, DOCX, XLS, XLSX, ZIP are uploaded as raw resources or auto
            $resourceType = 'raw';
        }

        $apiUrl = "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/{$resourceType}/upload";

        $postFields = [];
        $mime = mime_content_type($tmpFilePath) ?: 'application/octet-stream';
        $postFields['file'] = new CURLFile($tmpFilePath, $mime, $originalFileName);

        $timestamp = time();

        if (!empty($cfg['api_secret']) && !empty($cfg['api_key'])) {
            // Signed upload
            $paramsToSign = [
                'timestamp' => $timestamp,
            ];
            ksort($paramsToSign);

            $stringToSign = [];
            foreach ($paramsToSign as $k => $v) {
                $stringToSign[] = "{$k}={$v}";
            }
            $toSign = implode('&', $stringToSign) . $cfg['api_secret'];
            $signature = sha1($toSign);

            $postFields['api_key']   = $cfg['api_key'];
            $postFields['timestamp'] = $timestamp;
            $postFields['signature'] = $signature;
        } elseif (!empty($cfg['upload_preset'])) {
            // Unsigned upload preset
            $postFields['upload_preset'] = $cfg['upload_preset'];
        } else {
            return ['success' => false, 'error' => 'Neither Cloudinary API Secret nor Upload Preset was provided.'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("[Cloudinary Upload Error] cURL failed: " . $curlErr);
            return ['success' => false, 'error' => 'Network error connecting to Cloudinary: ' . $curlErr];
        }

        $json = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300 && !empty($json['secure_url'])) {
            return [
                'success'       => true,
                'secure_url'    => $json['secure_url'],
                'public_id'     => $json['public_id'] ?? '',
                'resource_type' => $json['resource_type'] ?? $resourceType,
                'format'        => $json['format'] ?? $ext,
                'bytes'         => $json['bytes'] ?? filesize($tmpFilePath)
            ];
        }

        $errMsg = $json['error']['message'] ?? ("HTTP " . $httpCode . " error from Cloudinary.");
        error_log("[Cloudinary Upload Failed] HTTP {$httpCode}: {$errMsg}");
        return ['success' => false, 'error' => $errMsg];
    }
}

if (!function_exists('cloudinary_delete')) {
    /**
     * Delete a resource from Cloudinary by public ID
     */
    function cloudinary_delete($publicId, $resourceType = 'image') {
        if (empty($publicId)) return false;

        $cfg = cloudinary_get_config();
        if (empty($cfg['cloud_name']) || empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return false;
        }

        $apiUrl = "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/{$resourceType}/destroy";
        $timestamp = time();

        $toSign = "public_id={$publicId}&timestamp={$timestamp}" . $cfg['api_secret'];
        $signature = sha1($toSign);

        $postFields = [
            'public_id' => $publicId,
            'api_key'   => $cfg['api_key'],
            'timestamp' => $timestamp,
            'signature' => $signature
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $apiUrl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return false;
        $json = json_decode($response, true);
        return isset($json['result']) && $json['result'] === 'ok';
    }
}
?>
