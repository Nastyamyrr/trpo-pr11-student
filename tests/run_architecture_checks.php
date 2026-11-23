<?php

declare(strict_types=1);

/**
 * Автоматическая проверка dataset/architecture_checklist.csv.
 * Для каждого пункта с required=yes запускается своя проверка исходного кода
 * (через token_get_all: комментарии и строки не дают ложных срабатываний).
 * Дополнительно — «гигиена репозитория» (шаг 12 ПР-11): нет секретов и временных файлов в Git.
 *
 * Запуск: php tests/run_architecture_checks.php
 */

require __DIR__ . '/support.php';
require dirname(__DIR__) . '/src/bootstrap.php';

$root = dirname(__DIR__);

const SUPERGLOBALS = ['$_GET', '$_POST', '$_SESSION', '$_SERVER', '$_COOKIE', '$_REQUEST', '$_FILES', '$GLOBALS'];

/** @return list<string> относительные пути php-файлов внутри $dir */
function php_files(string $root, string $dir): array
{
    $base = $root . '/' . $dir;
    if (!is_dir($base)) {
        return [];
    }
    $result = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'php') {
            $result[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }
    sort($result);
    return $result;
}

/** @return list<array{int, string, int}|string> */
function tokens(string $root, string $relative): array
{
    return token_get_all((string) file_get_contents($root . '/' . $relative));
}

function token_text(array|string $t): string
{
    return is_array($t) ? $t[1] : $t;
}

function token_id(array|string $t): int|string
{
    return is_array($t) ? $t[0] : $t;
}

/** Файлы, где встречаются суперглобальные переменные (в комментариях и строках не считаются). */
function files_with_superglobals(string $root, array $files): array
{
    $bad = [];
    foreach ($files as $file) {
        foreach (tokens($root, $file) as $t) {
            if (token_id($t) === T_VARIABLE && in_array(token_text($t), SUPERGLOBALS, true)) {
                $bad[$file] = true;
            }
        }
    }
    return array_keys($bad);
}

/** Файлы, где есть вывод (echo/print/inline HTML) или строки с HTML-тегами. */
function files_with_output(string $root, array $files): array
{
    $bad = [];
    foreach ($files as $file) {
        foreach (tokens($root, $file) as $t) {
            $id = token_id($t);
            if (in_array($id, [T_ECHO, T_PRINT, T_INLINE_HTML, T_OPEN_TAG_WITH_ECHO], true)) {
                $bad[$file] = true;
            }
            if ($id === T_CONSTANT_ENCAPSED_STRING && preg_match('/<\/?(html|body|div|p|h[1-6]|table|tr|td|form|span|a|ul|li|script|br)[\s>\/]/i', token_text($t)) === 1) {
                $bad[$file] = true;
            }
        }
    }
    return array_keys($bad);
}

/** Имена функций/классов (T_STRING) из списка, встретившиеся в файлах. */
function files_using(string $root, array $files, array $names): array
{
    $bad = [];
    foreach ($files as $file) {
        foreach (tokens($root, $file) as $t) {
            if (token_id($t) === T_STRING && in_array(token_text($t), $names, true)) {
                $bad[$file][] = token_text($t);
            }
        }
    }
    return $bad;
}

/** @return array{string, string} [namespace, class] из файла */
function declared(string $root, string $file): array
{
    $ns = '';
    $class = '';
    $t = tokens($root, $file);
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        if (token_id($t[$i]) === T_NAMESPACE) {
            for ($j = $i + 1; $j < $n && token_text($t[$j]) !== ';'; $j++) {
                if (in_array(token_id($t[$j]), [T_STRING, T_NAME_QUALIFIED], true)) {
                    $ns .= token_text($t[$j]);
                }
            }
        }
        if (in_array(token_id($t[$i]), [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && ($i === 0 || token_id($t[$i - 1]) !== T_DOUBLE_COLON) && $class === '') {
            for ($j = $i + 1; $j < $n; $j++) {
                if (token_id($t[$j]) === T_STRING) {
                    $class = token_text($t[$j]);
                    break;
                }
            }
        }
    }
    return [$ns, $class];
}

$srcFiles = php_files($root, 'src');
$viewFiles = php_files($root, 'views');
$controllerFiles = array_values(array_filter($srcFiles, static fn(string $f): bool => str_starts_with($f, 'src/Controllers/')));
$modelFiles = array_values(array_filter($srcFiles, static fn(string $f): bool => str_starts_with($f, 'src/Models/')));
$classFiles = array_values(array_filter($srcFiles, static fn(string $f): bool => !in_array($f, ['src/bootstrap.php', 'src/Support/functions.php'], true)));

/**
 * Каждая проверка возвращает список проблем (пустой = пройдена).
 *
 * @var array<string, callable(): list<string>> $checks
 */
$checks = [];

$checks['Single entry point'] = static function () use ($root): array {
    $problems = [];
    $public = array_map('basename', php_files($root, 'public'));
    if ($public !== ['index.php']) {
        $problems[] = 'в public/ должен быть один php-файл index.php, найдено: ' . implode(', ', $public);
    }
    $index = file_get_contents($root . '/public/index.php');
    if (!str_contains((string) $index, 'new App\\Application(') || !str_contains((string) $index, '->run()')) {
        $problems[] = 'public/index.php должен создавать App\\Application и вызывать run()';
    }
    foreach (tokens($root, 'public/index.php') as $t) {
        if (in_array(token_id($t), [T_CLASS, T_FUNCTION, T_ECHO, T_INLINE_HTML], true)) {
            $problems[] = 'в public/index.php не должно быть классов, функций и вывода';
            break;
        }
    }
    $sessionStarts = array_keys(files_using($root, array_merge(php_files($root, 'src'), php_files($root, 'views'), ['public/index.php']), ['session_start']));
    if ($sessionStarts !== ['public/index.php']) {
        $problems[] = 'session_start() должен вызываться только в public/index.php, найдено: ' . implode(', ', $sessionStarts);
    }
    return $problems;
};

$checks['Router without HTML'] = static function () use ($root): array {
    $problems = [];
    foreach (['src/Http/Router.php', 'src/Application.php'] as $file) {
        if (files_with_output($root, [$file]) !== []) {
            $problems[] = "$file содержит вывод или HTML";
        }
        if (isset(files_using($root, [$file], ['View', 'header', 'http_response_code'])[$file])) {
            $problems[] = "$file обращается к View/заголовкам: это работа контроллеров";
        }
    }
    return $problems;
};

$checks['Controllers choose views'] = static function () use ($root, $controllerFiles): array {
    $problems = [];
    foreach (files_with_output($root, $controllerFiles) as $file) {
        $problems[] = "$file формирует вывод/HTML сам";
    }
    foreach ($controllerFiles as $file) {
        $code = (string) file_get_contents($root . '/' . $file);
        if (!str_contains($code, 'View::render(')) {
            $problems[] = "$file не выбирает view через View::render()";
        }
        preg_match_all("/View::render\(\s*'([a-z0-9_\/-]+)'/i", $code, $m);
        foreach ($m[1] as $view) {
            if (!is_file($root . '/views/' . $view . '.php')) {
                $problems[] = "$file: view «$view» не существует";
            }
        }
    }
    return $problems;
};

$checks['Models without HTTP globals'] = static function () use ($root, $modelFiles, $srcFiles): array {
    $problems = [];
    foreach (files_with_superglobals($root, $modelFiles) as $file) {
        $problems[] = "$file использует суперглобальные переменные";
    }
    foreach (files_using($root, $modelFiles, ['header', 'http_response_code', 'session_start', 'setcookie']) as $file => $names) {
        $problems[] = "$file вызывает HTTP-функции: " . implode(', ', array_unique($names));
    }
    // Шире: сервисы, репозитории и валидатор тоже не читают глобальные переменные (кроме HTTP-слоя)
    $domain = array_values(array_filter($srcFiles, static fn(string $f): bool => preg_match('#^src/(Services|Repositories|Validation|Models)/#', $f) === 1));
    foreach (files_with_superglobals($root, $domain) as $file) {
        $problems[] = "$file (слой данных/сервисов) использует суперглобальные переменные";
    }
    return $problems;
};

$checks['Views without data access'] = static function () use ($root, $viewFiles): array {
    $problems = [];
    foreach (files_with_superglobals($root, $viewFiles) as $file) {
        $problems[] = "$file использует суперглобальные переменные";
    }
    $forbidden = ['file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'json_decode', 'session_start', 'header', 'http_response_code', 'unlink', 'mkdir'];
    foreach (files_using($root, $viewFiles, $forbidden) as $file => $names) {
        $problems[] = "$file вызывает " . implode(', ', array_unique($names));
    }
    foreach ($viewFiles as $file) {
        $t = tokens($root, $file);
        foreach ($t as $i => $token) {
            $id = token_id($token);
            if (in_array($id, [T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true)) {
                $problems[] = "$file подключает другие файлы";
            }
            if (in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true) && preg_match('/(Repositories\\\\|Services\\\\|Repository$|Service$)/', token_text($token)) === 1) {
                $problems[] = "$file обращается к репозиторию/сервису: " . token_text($token);
            }
        }
    }
    return array_values(array_unique($problems));
};

$checks['Namespaces'] = static function () use ($root, $classFiles): array {
    $problems = [];
    foreach ($classFiles as $file) {
        $dir = trim(dirname(substr($file, strlen('src/'))), '.');
        $expected = 'App' . ($dir === '' ? '' : '\\' . str_replace('/', '\\', $dir));
        [$ns, $class] = declared($root, $file);
        if ($ns !== $expected) {
            $problems[] = "$file: namespace «$ns», ожидался «$expected»";
        }
        if ($class !== basename($file, '.php')) {
            $problems[] = "$file: класс «$class» не совпадает с именем файла";
        }
    }
    return $problems;
};

$checks['Autoload'] = static function () use ($root, $classFiles): array {
    $problems = [];
    $bootstrap = (string) file_get_contents($root . '/src/bootstrap.php');
    if (!str_contains($bootstrap, 'spl_autoload_register')) {
        $problems[] = 'src/bootstrap.php не регистрирует автозагрузчик';
    }
    // Каждый класс загружается автозагрузкой (мы ни одного не подключали вручную)
    foreach ($classFiles as $file) {
        [$ns, $class] = declared($root, $file);
        $fqcn = $ns . '\\' . $class;
        if (!class_exists($fqcn) && !interface_exists($fqcn)) {
            $problems[] = "$fqcn не загружается автозагрузкой";
        }
    }
    // Нет ручных require/include классов: require разрешён только в bootstrap (функции) и View (шаблоны)
    $allowedRequire = ['src/bootstrap.php', 'src/Support/View.php'];
    foreach (php_files($root, 'src') as $file) {
        foreach (tokens($root, $file) as $t) {
            $id = token_id($t);
            if (in_array($id, [T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE], true) && $file !== 'src/bootstrap.php') {
                $problems[] = "$file использует require_once/include";
            }
            if ($id === T_REQUIRE && !in_array($file, $allowedRequire, true)) {
                $problems[] = "$file использует require";
            }
        }
    }
    return array_values(array_unique($problems));
};

$checks['404 handler'] = static function () use ($root): array {
    $problems = [];
    if (!method_exists(App\Controllers\ErrorController::class, 'notFound')) {
        $problems[] = 'нет ErrorController::notFound()';
    }
    if (!method_exists(App\Http\Router::class, 'setNotFoundHandler')) {
        $problems[] = 'у Router нет setNotFoundHandler()';
    }
    if (!str_contains((string) file_get_contents($root . '/src/Application.php'), 'setNotFoundHandler')) {
        $problems[] = 'Application не регистрирует обработчик 404';
    }
    if (!is_file($root . '/views/error.php')) {
        $problems[] = 'нет views/error.php';
    }
    return $problems;
};

$failed = 0;
echo "--- dataset/architecture_checklist.csv ---\n";
foreach (read_csv($root . '/dataset/architecture_checklist.csv') as $row) {
    if (strtolower($row['required']) !== 'yes') {
        printf("[--] %s (не обязательно)\n", $row['item']);
        continue;
    }
    if (!isset($checks[$row['item']])) {
        $failed++;
        report(false, $row['item'], 'для пункта нет автоматической проверки');
        continue;
    }
    $problems = $checks[$row['item']]();
    $failed += $problems === [] ? 0 : 1;
    report($problems === [], $row['item'], $problems === [] ? '' : '— ' . implode('; ', $problems));
}

echo "--- гигиена репозитория (шаг 12) ---\n";
if (is_dir($root . '/.git') && trim((string) shell_exec('command -v git 2>/dev/null')) !== '') {
    $tracked = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($root) . ' ls-files')));

    $badNames = [];
    foreach ($tracked as $path) {
        if (preg_match('#(^|/)(\.env(\..*)?|\.DS_Store|Thumbs\.db|.*\.(log|tmp|swp|bak))$|^storage/orders\.json$|^\.idea/|^\.vscode/#', $path) === 1 && $path !== '.env.example') {
            $badNames[] = $path;
        }
    }
    $failed += $badNames === [] ? 0 : 1;
    report($badNames === [], 'в Git нет временных/локальных файлов', $badNames === [] ? '' : implode(', ', $badNames));

    $secrets = [];
    foreach ($tracked as $path) {
        $full = $root . '/' . $path;
        if (!is_file($full) || filesize($full) > 1_000_000) {
            continue;
        }
        $content = (string) file_get_contents($full);
        if (preg_match('/(password|passwd|secret|api[_-]?key|private[_-]?key)\s*[:=]\s*[\'"][^\'"\s]{3,}[\'"]|-----BEGIN [A-Z ]*PRIVATE KEY-----|ghp_[A-Za-z0-9]{20,}/i', $content) === 1) {
            $secrets[] = $path;
        }
    }
    $failed += $secrets === [] ? 0 : 1;
    report($secrets === [], 'в Git нет паролей и секретов', $secrets === [] ? '' : implode(', ', $secrets));

    $ignore = (string) @file_get_contents($root . '/.gitignore');
    $ok = $ignore !== '' && str_contains($ignore, 'storage') && str_contains($ignore, '.env');
    $failed += $ok ? 0 : 1;
    report($ok, '.gitignore покрывает storage/ и .env');
} else {
    echo "[--] папка .git не найдена: проверка Git-гигиены пропущена\n";
}

echo $failed === 0 ? "\nАрхитектурный чек-лист выполнен.\n" : "\nНе выполнено пунктов: $failed\n";
exit($failed === 0 ? 0 : 1);
