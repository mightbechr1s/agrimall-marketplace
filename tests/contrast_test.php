<?php
/**
 * Guards the design-system colour tokens against WCAG 2.2 AA regressions.
 *
 * Reads the light (`:root`) and dark (`:root[data-theme="dark"]`) token blocks
 * from public/assets/css/design-system.css and checks the pairs that carry
 * text or meaningful boundaries. Tokens are parsed, not hard-coded, so a palette
 * edit that drops contrast fails here instead of shipping.
 *
 * Run: php tests/contrast_test.php
 */
declare(strict_types=1);

$cssPath = dirname(__DIR__) . '/public/assets/css/design-system.css';
$css = (string) file_get_contents($cssPath);
if ($css === '') {
    fwrite(STDERR, "FATAL: could not read $cssPath\n");
    exit(2);
}

/** @return array<string,string> token name => raw value */
function css_tokens(string $css, string $selector): array
{
    $pos = strpos($css, $selector);
    if ($pos === false) {
        return [];
    }
    $open = strpos($css, '{', $pos);
    $close = $open === false ? false : strpos($css, '}', $open);
    if ($open === false || $close === false) {
        return [];
    }
    preg_match_all(
        '/--([a-z0-9-]+)\s*:\s*([^;]+);/i',
        substr($css, $open + 1, $close - $open - 1),
        $matches,
        PREG_SET_ORDER
    );
    $tokens = [];
    foreach ($matches as $m) {
        $tokens[strtolower($m[1])] = trim($m[2]);
    }
    return $tokens;
}

/** Resolve `var(--x)` aliases inside one theme's token map. */
function resolve_token(array $tokens, string $name): string
{
    $seen = [];
    $value = $tokens[$name] ?? '';
    while (preg_match('/^var\(\s*--([a-z0-9-]+)\s*\)$/i', trim($value), $m)) {
        $next = strtolower($m[1]);
        if (isset($seen[$next])) {
            return '';
        }
        $seen[$next] = true;
        $value = $tokens[$next] ?? '';
    }
    return $value;
}

/** @return array{0:int,1:int,2:int,3:float}|null */
function parse_color(string $value): ?array
{
    $value = trim($value);
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m)) {
        $hex = $m[1];
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
            1.0,
        ];
    }
    if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([0-9.]+))?\s*\)$/i', $value, $m)) {
        return [(int) $m[1], (int) $m[2], (int) $m[3], isset($m[4]) ? (float) $m[4] : 1.0];
    }
    return null;
}

/** @param array{0:int,1:int,2:int,3:float} $fg */
function composite(array $fg, array $bg): array
{
    $a = $fg[3];
    return [
        (int) round($fg[0] * $a + $bg[0] * (1 - $a)),
        (int) round($fg[1] * $a + $bg[1] * (1 - $a)),
        (int) round($fg[2] * $a + $bg[2] * (1 - $a)),
        1.0,
    ];
}

/** @param array{0:int,1:int,2:int,3:float} $c */
function luminance(array $c): float
{
    $channel = static function (int $v): float {
        $s = $v / 255;
        return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * $channel($c[0]) + 0.7152 * $channel($c[1]) + 0.0722 * $channel($c[2]);
}

/** @param array{0:int,1:int,2:int,3:float} $a @param array{0:int,1:int,2:int,3:float} $b */
function contrast(array $a, array $b): float
{
    $la = luminance($a);
    $lb = luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

$light = css_tokens($css, ':root {');
$dark = css_tokens($css, ':root[data-theme="dark"] {');

$pass = 0;
$fail = [];
$check = function (string $label, float $ratio, float $min) use (&$pass, &$fail): void {
    if ($ratio + 0.001 >= $min) {
        $pass++;
        return;
    }
    $fail[] = sprintf('%s: %.2f:1 (needs %.1f:1)', $label, $ratio, $min);
};

/**
 * [theme label, tokens, foreground token, background token, minimum ratio]
 * Background tokens that are translucent are composited over `--canvas`.
 */
$pairs = [
    ['light', $light, 'text', 'canvas', 4.5],
    ['light', $light, 'text', 'paper', 4.5],
    ['light', $light, 'text', 'bg-primary', 4.5],
    ['light', $light, 'text-soft', 'canvas', 4.5],
    ['light', $light, 'text-soft', 'paper', 4.5],
    ['light', $light, 'text-soft', 'bg-primary', 4.5],
    ['light', $light, 'primary', 'canvas', 4.5],
    ['light', $light, 'primary', 'paper', 4.5],
    ['light', $light, 'accent', 'canvas', 4.5],
    ['light', $light, 'danger', 'canvas', 4.5],
    ['light', $light, 'success', 'canvas', 4.5],
    ['light', $light, 'footer-text', 'footer-bg', 4.5],
    ['light', $light, 'status-ok-ink', 'status-ok-fill', 4.5],
    ['light', $light, 'status-info-ink', 'status-info-fill', 4.5],
    ['light', $light, 'status-warn-ink', 'status-warn-fill', 4.5],
    ['light', $light, 'status-bad-ink', 'status-bad-fill', 4.5],
    ['light', $light, 'field-border', 'canvas', 3.0],
    ['dark', $dark, 'text', 'canvas', 4.5],
    ['dark', $dark, 'text', 'paper', 4.5],
    ['dark', $dark, 'text-soft', 'canvas', 4.5],
    ['dark', $dark, 'text-soft', 'paper', 4.5],
    ['dark', $dark, 'primary', 'canvas', 4.5],
    ['dark', $dark, 'primary', 'paper', 4.5],
    ['dark', $dark, 'danger', 'canvas', 4.5],
    ['dark', $dark, 'status-ok-ink', 'status-ok-fill', 4.5],
    ['dark', $dark, 'status-info-ink', 'status-info-fill', 4.5],
    ['dark', $dark, 'status-warn-ink', 'status-warn-fill', 4.5],
    ['dark', $dark, 'status-bad-ink', 'status-bad-fill', 4.5],
    ['dark', $dark, 'field-border', 'canvas', 3.0],
];

foreach ($pairs as [$theme, $tokens, $fgName, $bgName, $min]) {
    $fg = parse_color(resolve_token($tokens, $fgName));
    $bgRaw = parse_color(resolve_token($tokens, $bgName));
    $canvas = parse_color(resolve_token($tokens, 'canvas'));
    if ($fg === null || $bgRaw === null || $canvas === null) {
        $fail[] = "$theme: token $fgName/$bgName missing or not a plain colour";
        continue;
    }
    // Translucent fills sit on top of the page canvas.
    $bg = $bgRaw[3] < 1.0 ? composite($bgRaw, $canvas) : $bgRaw;
    // A translucent foreground (e.g. a hairline border) is composited over its
    // own background before measuring.
    $fgSolid = $fg[3] < 1.0 ? composite($fg, $bg) : $fg;
    $check("$theme $fgName on $bgName", contrast($fgSolid, $bg), $min);
}

// Button labels: white/ink on the filled brand colour.
$lightOnGold = parse_color(resolve_token($light, 'gold-ink'));
$lightGold = parse_color(resolve_token($light, 'gold'));
if ($lightOnGold && $lightGold) {
    $check('light gold-ink on gold', contrast($lightOnGold, $lightGold), 4.5);
}
$darkOnGold = parse_color(resolve_token($dark, 'gold-ink'));
$darkGold = parse_color(resolve_token($dark, 'gold'));
if ($darkOnGold && $darkGold) {
    $check('dark gold-ink on gold', contrast($darkOnGold, $darkGold), 4.5);
}

echo 'assertions passed: ' . $pass . PHP_EOL;
echo 'assertions failed: ' . count($fail) . PHP_EOL;
foreach ($fail as $line) {
    echo '  FAIL ' . $line . PHP_EOL;
}
if ($fail) {
    exit(1);
}
echo 'OK: design tokens meet WCAG AA contrast in both themes' . PHP_EOL;
