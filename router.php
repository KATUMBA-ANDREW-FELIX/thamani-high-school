<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If requesting root path, render home.php directly
if ($uri === '/' || $uri === '/index.php' || $uri === '/index.html') {
    require __DIR__ . '/home.php';
    exit;
}

// Serve existing static files (css, js, images, html) directly
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    if (str_ends_with($file, '.php')) {
        require $file;
        exit;
    }
    return false;
}

// Extensionless fallback to .php or .html
if (file_exists($file . '.php')) {
    require $file . '.php';
    exit;
}

if (file_exists($file . '.html')) {
    require $file . '.html';
    exit;
}

return false;
?>
