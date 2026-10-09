<?php
declare(strict_types=1);

use Crustum\Speculum\Frontend\Assets;
use Crustum\Speculum\Speculum;

$requestNonce = $this->request->getAttribute('cspNonce');
$cspNonce = is_string($requestNonce) && $requestNonce !== '' ? $requestNonce : Speculum::$cspNonce;
$nonceAttribute = $cspNonce !== '' ? ' nonce="' . h($cspNonce) . '"' : '';
$scriptOptions = ['type' => 'module'];
if ($cspNonce !== '') {
    $scriptOptions['nonce'] = $cspNonce;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <?php
    $csrfToken = $this->request->getAttribute('csrfToken');
    if ($csrfToken) :
        ?>
        <meta name="csrf-token" content="<?= h($csrfToken) ?>">
    <?php endif; ?>
    <title>Speculum</title>
    <style<?= $nonceAttribute ?>>[v-cloak]{display:none}</style>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600" rel="stylesheet" />
    <?= $this->Html->css(Assets::cssUrls()) ?>
    <style data-scheme="dark" media="max-width: 1px"<?= $nonceAttribute ?>>
<?php
$darkCssPath = Assets::diskFile(Assets::STYLES_DARK_CSS);
if (is_file($darkCssPath)) {
    echo file_get_contents($darkCssPath);
}
?>
    </style>
    <script<?= $nonceAttribute ?>>
        window.Speculum = <?= json_encode($speculumScript ?? [
            'path' => $path ?? 'speculum',
            'timezone' => $timezone ?? 'UTC',
            'recording' => $recording ?? true,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
</head>
<body>
<?= $this->fetch('content') ?>
<?= $this->Html->script(Assets::jsUrl(), $scriptOptions) ?>
</body>
</html>
