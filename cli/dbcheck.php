<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: CLI only');
}

define('ROOT_PATH', dirname(__DIR__) . '/');
require_once ROOT_PATH . 'config/config.php';

$apply = in_array('--apply', $argv, true);
$sqlFile = ROOT_PATH . 'install/install.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "install/install.sql not found\n");
    exit(1);
}
$sql = file_get_contents($sqlFile);
preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-zA-Z0-9_]+)` \((.*?)\n\) ENGINE/is', $sql, $tables, PREG_SET_ORDER);

$expect = array();
foreach ($tables as $t) {
    $name = $t[1];
    $cols = array();
    foreach (explode("\n", $t[2]) as $line) {
        if (preg_match('/^\s+`([a-zA-Z0-9_]+)`\s+(\S.*?),?\s*$/', $line, $m)) {
            $def = rtrim(trim($m[2]), ',');
            $cols[$m[1]] = $def;
        }
    }
    $expect[$name] = $cols;
}

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (Exception $e) {
    fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$prefix = defined('DB_PREFIX') ? DB_PREFIX : '';
$missTables = array();
$missCols = array();
$extraCols = array();
$stat = array('ok' => 0, 'table' => 0, 'col' => 0);

foreach ($expect as $tbl => $cols) {
    $full = $prefix . $tbl;
    $has = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($full))->fetchAll(PDO::FETCH_NUM);
    if (!$has) {
        $missTables[] = $full;
        continue;
    }
    $actual = array();
    $res = $pdo->query('SHOW COLUMNS FROM `' . $full . '`')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res as $r) {
        $actual[] = $r['Field'];
    }
    foreach ($cols as $c => $def) {
        if (!in_array($c, $actual, true)) {
            $missCols[] = array('table' => $full, 'col' => $c, 'def' => $def);
        } else {
            $stat['ok']++;
        }
    }
    $known = array_keys($cols);
    foreach ($actual as $a) {
        if (!in_array($a, $known, true)) {
            $extraCols[] = $full . '.' . $a;
        }
    }
}

echo "=== EnovelCms DB structure check ===\n";
echo 'target: ' . DB_NAME . ' (prefix "' . $prefix . '")\n';
echo 'expected tables: ' . count($expect) . " , columns matched: " . $stat['ok'] . "\n\n";

if ($missTables) {
    echo 'MISSING TABLES (' . count($missTables) . "):\n";
    foreach ($missTables as $t) {
        echo '  - ' . $t . "\n";
    }
    echo "\n";
}

if ($missCols) {
    echo 'MISSING COLUMNS (' . count($missCols) . "):\n";
    foreach ($missCols as $m) {
        echo '  - ' . $m['table'] . '.' . $m['col'] . "\n";
    }
    echo "\n--- ALTER statements ---\n";
    foreach ($missCols as $m) {
        echo 'ALTER TABLE `' . $m['table'] . '` ADD COLUMN `' . $m['col'] . '` ' . $m['def'] . ";\n";
    }
    echo "\n";
} else {
    echo "MISSING COLUMNS: none\n\n";
}

if ($extraCols) {
    echo 'EXTRA COLUMNS (' . count($extraCols) . " , usually safe to keep):\n";
    foreach ($extraCols as $c) {
        echo '  - ' . $c . "\n";
    }
    echo "\n";
}

if ($apply && $missCols) {
    echo "--- applying ---\n";
    foreach ($missCols as $m) {
        try {
            $pdo->exec('ALTER TABLE `' . $m['table'] . '` ADD COLUMN `' . $m['col'] . '` ' . $m['def']);
            echo '  OK  ' . $m['table'] . '.' . $m['col'] . "\n";
        } catch (Exception $e) {
            echo '  FAIL ' . $m['table'] . '.' . $m['col'] . ' : ' . $e->getMessage() . "\n";
        }
    }
}

if (!$apply && ($missTables || $missCols)) {
    echo "run with --apply to add the missing columns (tables are never auto-created)\n";
}

exit(($missTables || $missCols) ? 2 : 0);
