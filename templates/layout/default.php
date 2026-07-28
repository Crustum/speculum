<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Speculum</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />
    <link rel="stylesheet" href="/speculum/frontend/styles.css">
    <link rel="stylesheet" href="/speculum/frontend/app.css">
    <style data-scheme="dark" media="max-width: 1px">
<?php
$darkCssPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'webroot' . DIRECTORY_SEPARATOR . 'frontend' . DIRECTORY_SEPARATOR . 'styles-dark.css';
if (is_file($darkCssPath)) {
    echo file_get_contents($darkCssPath);
}
?>
    </style>
    <script>
        window.Speculum = <?= json_encode($speculumScript ?? [
            'path' => 'speculum',
            'timezone' => 'UTC',
            'recording' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
</head>
<body>
    <?= $this->fetch('content') ?>
    <script type="module" src="/speculum/frontend/app.js"></script>
</body>
</html>
