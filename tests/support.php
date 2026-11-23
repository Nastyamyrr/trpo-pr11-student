<?php

declare(strict_types=1);

/**
 * Помощники для HTTP-тестов: временный сервер `php -S` и простой клиент с cookie.
 * Подключается из tests/run_route_tests.php и tests/run_security_cases.php.
 */

final class TestServer
{
    /** @var resource|null */
    private $process = null;
    public int $port = 0;
    public string $logFile;

    public function __construct(private readonly string $root)
    {
        $this->logFile = sys_get_temp_dir() . '/trpo_test_server_' . getmypid() . '.log';
    }

    public function start(): void
    {
        // Свободный порт: просим систему выбрать любой и сразу освобождаем
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            throw new RuntimeException("Не удалось найти свободный порт: $errstr");
        }
        $this->port = (int) substr((string) strrchr((string) stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $cmd = [
            PHP_BINARY,
            '-d', 'display_errors=1',
            '-d', 'error_reporting=-1',
            '-d', 'log_errors=1',
            '-S', '127.0.0.1:' . $this->port,
            '-t', $this->root . '/public',
        ];
        $this->process = proc_open($cmd, [1 => ['file', $this->logFile, 'w'], 2 => ['file', $this->logFile, 'a']], $pipes, $this->root);
        if (!is_resource($this->process)) {
            throw new RuntimeException('Не удалось запустить php -S');
        }

        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if ($conn !== false) {
                fclose($conn);
                return;
            }
            usleep(100_000);
        }
        throw new RuntimeException('Сервер не запустился за 5 секунд');
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    /** Строки лога сервера с предупреждениями PHP (warning, notice, deprecated, fatal). */
    public function phpProblems(): array
    {
        $log = is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
        $problems = [];
        foreach (explode("\n", $log) as $line) {
            if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|^(Warning|Notice|Deprecated|Fatal error):/', $line) === 1) {
                $problems[] = $line;
            }
        }
        return $problems;
    }

    public function __destruct()
    {
        $this->stop();
        if (is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }
}

final class HttpClient
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(private readonly int $port)
    {
    }

    /**
     * @param array<string, mixed>|null $form данные POST (application/x-www-form-urlencoded)
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function request(string $method, string $path, ?array $form = null): array
    {
        $headers = ['Connection: close'];
        if ($this->cookies !== []) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(
                static fn(string $k, string $v): string => "$k=$v",
                array_keys($this->cookies),
                $this->cookies
            ));
        }

        $content = '';
        if ($form !== null) {
            $content = http_build_query($form);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Content-Length: ' . strlen($content);
        }

        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $content,
            'ignore_errors' => true,   // тело ответа читаем и при 4xx/5xx
            'follow_location' => 0,    // redirect сами не выполняем: нужен именно 302
            'timeout' => 10,
        ]]);

        $body = @file_get_contents('http://127.0.0.1:' . $this->port . $path, false, $context);
        $rawHeaders = $http_response_header ?? [];

        $status = 0;
        $parsed = [];
        foreach ($rawHeaders as $i => $line) {
            if ($i === 0 && preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsed[strtolower(trim($name))] = trim($value);
                if (strtolower(trim($name)) === 'set-cookie' && preg_match('/^\s*([^=;]+)=([^;]*)/', $value, $m) === 1) {
                    $this->cookies[$m[1]] = $m[2];
                }
            }
        }

        return ['status' => $status, 'headers' => $parsed, 'body' => $body === false ? '' : $body];
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $form
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    public function post(string $path, array $form = []): array
    {
        return $this->request('POST', $path, $form);
    }

    /** Число в бейдже корзины в header (из HTML). */
    public static function cartCount(string $html): ?int
    {
        return preg_match('/class="cart-count">(\d+)</', $html, $m) === 1 ? (int) $m[1] : null;
    }
}

/**
 * Читает CSV с разделителем «;», убирая возможный BOM.
 *
 * @return list<array<string, string>>
 */
function read_csv(string $file): array
{
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        throw new RuntimeException("Не удалось открыть $file");
    }
    $header = fgetcsv($handle, 0, ';', '"', '');
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

    $rows = [];
    while (($row = fgetcsv($handle, 0, ';', '"', '')) !== false) {
        if ($row === [null] || count($row) < count($header)) {
            continue;
        }
        $rows[] = array_combine($header, $row);
    }
    fclose($handle);
    return $rows;
}

function report(bool $ok, string $label, string $details = ''): void
{
    printf("[%s] %s%s\n", $ok ? 'OK' : 'FAIL', $label, $details === '' ? '' : '  ' . $details);
}
