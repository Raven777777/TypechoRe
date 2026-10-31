<?php
/** Warn about installed options that have no runtime reads. */

declare(strict_types=1);

$root = dirname(__DIR__);
$files = static function (string $directory) use (&$files): array {
    $result = [];
    foreach (glob($directory . '/*') ?: [] as $path) {
        if (is_dir($path)) {
            $result = [...$result, ...$files($path)];
        } elseif (str_ends_with($path, '.php')) {
            $result[] = $path;
        }
    }
    return $result;
};

$install = file_get_contents($root . '/install.php');
$defaults = strstr(strstr($install, '$options = [') ?: '', '];', true) ?: '';
preg_match_all("/^            '([A-Za-z][A-Za-z0-9_-]*)'\\s*=>/m", $defaults, $matches);
$options = $matches[1];
foreach ($files($root . '/var/Widget/Options') as $file) {
    preg_match_all('/new Form\\\\Element\\\\[A-Za-z]+\\s*\\(\\s*[\'\"]([A-Za-z][A-Za-z0-9:_-]*)[\'\"]|name\\s*=\\s*[\'\"]([A-Za-z][A-Za-z0-9:_-]*)[\'\"]|\\$this->options->([A-Za-z][A-Za-z0-9:_-]*)/', (string) file_get_contents($file), $matches);
    $options = [...$options, ...array_filter(array_merge($matches[1], $matches[2], $matches[3]))];
}

$source = '';
foreach (['admin', 'usr', 'var'] as $directory) {
    foreach ($files($root . '/' . $directory) as $file) {
        if (!str_starts_with($file, $root . '/var/Widget/Options/')) {
            $source .= file_get_contents($file) . "\n";
        }
    }
}
foreach (glob($root . '/*.php') ?: [] as $file) {
    if ($file !== $root . '/install.php') {
        $source .= file_get_contents($file) . "\n";
    }
}
foreach (array_unique($options) as $name) {
    if (!preg_match('/\$this->options->' . preg_quote($name, '/') . '\b/', $source)) {
        fwrite(STDOUT, "WARN unused option: {$name}\n");
    }
}
