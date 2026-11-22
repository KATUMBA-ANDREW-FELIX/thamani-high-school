<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// If requesting root path, render home.php directly
if ($uri === '/' || $uri === '/index.php' || $uri === '/index.html') {
    require __DIR__ . '/home.php';
    exit;
}

// Redirect old static /admin or /admin.html to real PHP Admin Dashboard
if ($uri === '/admin' || $uri === '/admin.html') {
    header('Location: admin_dashboard.php');
    exit;
}

// Redirect legacy login pages to unified login.php
if (in_array($uri, ['/teacher-login', '/teacher-login.php', '/teacher-login.html', '/student-login', '/student-login.php', '/student-login.html'], true)) {
    header('Location: login.php');
    exit;
}

// Redirect raw .html templates to their active PHP controllers
$templateRedirects = [
    '/enrollment.html' => 'enrollment.php',
    '/alumni.html'     => 'alumni.php',
    '/library.html'    => 'library.php',
    '/calendar.html'   => 'calendar.php',
    '/gallery.html'    => 'gallery.php',
];
if (isset($templateRedirects[$uri])) {
    header('Location: ' . $templateRedirects[$uri]);
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

http_response_code(404);
echo 'Not Found';
exit;
?>
