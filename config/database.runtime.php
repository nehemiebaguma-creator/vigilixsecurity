<?php

declare(strict_types=1);

require_once __DIR__ . '/runtime.php';

if (!function_exists('vg_db_runtime_file')) {
    function vg_db_runtime_file(): string
    {
        return dirname(__DIR__) . '/storage/db-config.json';
    }
}

if (!function_exists('vg_db_runtime_overrides')) {
    function vg_db_runtime_overrides(): array
    {
        $path = vg_db_runtime_file();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('vg_db_save_runtime_overrides')) {
    function vg_db_save_runtime_overrides(array $config): bool
    {
        $payload = [
            'host' => trim((string) ($config['host'] ?? '127.0.0.1')),
            'port' => trim((string) ($config['port'] ?? '3306')),
            'name' => trim((string) ($config['name'] ?? 'vigilix')),
            'user' => trim((string) ($config['user'] ?? 'root')),
            'pass' => (string) ($config['pass'] ?? ''),
            'updated_at' => date('c'),
        ];

        $path = vg_db_runtime_file();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}

if (!function_exists('vg_db_set_last_error')) {
    function vg_db_set_last_error(string $message): void
    {
        $GLOBALS['vg_db_last_error'] = $message;
    }
}

if (!function_exists('vg_db_last_error')) {
    function vg_db_last_error(): string
    {
        return (string) ($GLOBALS['vg_db_last_error'] ?? '');
    }
}

if (!function_exists('vg_db_config')) {
    function vg_db_config(): array
    {
        $overrides = vg_db_runtime_overrides();

        return [
            'host' => (string) ($overrides['host'] ?? vg_env('DB_HOST', '127.0.0.1')),
            'port' => (string) ($overrides['port'] ?? vg_env('DB_PORT', '3306')),
            'name' => (string) ($overrides['name'] ?? vg_env('DB_NAME', 'vigilix')),
            'user' => (string) ($overrides['user'] ?? vg_env('DB_USER', 'root')),
            'pass' => (string) ($overrides['pass'] ?? vg_env('DB_PASS', '')),
            'charset' => 'utf8mb4',
        ];
    }
}

if (!function_exists('vg_db_hosts')) {
    function vg_db_hosts(array $config): array
    {
        return array_values(array_unique(array_filter([
            $config['host'] ?? '127.0.0.1',
            '127.0.0.1',
            'localhost',
        ])));
    }
}

if (!function_exists('vg_db_driver')) {
    function vg_db_driver(): ?string
    {
        return $GLOBALS['vg_db_driver'] ?? null;
    }
}

if (!function_exists('vg_db_cache_state')) {
    function vg_db_cache_state(string $status, string $message = ''): void
    {
        $GLOBALS['vg_db_connection_status'] = $status;
        $GLOBALS['vg_db_connection_status_message'] = $message;
        $GLOBALS['vg_db_connection_status_at'] = microtime(true);
    }
}

if (!function_exists('vg_db_connection_status_cached')) {
    function vg_db_connection_status_cached(): string
    {
        return (string) ($GLOBALS['vg_db_connection_status'] ?? 'unknown');
    }
}

if (!function_exists('vg_db_connect_timeout_seconds')) {
    function vg_db_connect_timeout_seconds(): int
    {
        $value = (int) vg_env('DB_CONNECT_TIMEOUT', '2');
        return max(1, min(5, $value));
    }
}

if (!function_exists('vg_db_connection')) {
    function vg_db_connection()
    {
        static $connection = false;

        if ($connection !== false) {
            return $connection;
        }

        $config = vg_db_config();
        vg_db_set_last_error('');
        $GLOBALS['vg_db_driver'] = null;
        vg_db_cache_state('connecting');

        $hosts = vg_db_hosts($config);
        $timeoutSeconds = vg_db_connect_timeout_seconds();

        if (extension_loaded('pdo_mysql')) {
            foreach ($hosts as $host) {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=%s;connect_timeout=%d',
                        $host,
                        $config['port'],
                        $config['name'],
                        $config['charset'],
                        $timeoutSeconds
                    );

                    $pdo = new PDO(
                        $dsn,
                        $config['user'],
                        $config['pass'],
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => $timeoutSeconds,
                        ]
                    );

                    $GLOBALS['vg_db_driver'] = 'pdo';
                    vg_db_cache_state('connected');
                    return $connection = $pdo;
                } catch (Throwable $exception) {
                    $message = strtolower($exception->getMessage());

                    if (strpos($message, 'unknown database') !== false || strpos($message, '1049') !== false) {
                        try {
                            $serverDsn = sprintf(
                                'mysql:host=%s;port=%s;charset=%s;connect_timeout=%d',
                                $host,
                                $config['port'],
                                $config['charset'],
                                $timeoutSeconds
                            );

                            $serverPdo = new PDO(
                                $serverDsn,
                                $config['user'],
                                $config['pass'],
                                [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                    PDO::ATTR_TIMEOUT => $timeoutSeconds,
                                ]
                            );

                            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $config['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

                            $dsn = sprintf(
                                'mysql:host=%s;port=%s;dbname=%s;charset=%s;connect_timeout=%d',
                                $host,
                                $config['port'],
                                $config['name'],
                                $config['charset'],
                                $timeoutSeconds
                            );

                            $pdo = new PDO(
                                $dsn,
                                $config['user'],
                                $config['pass'],
                                [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                    PDO::ATTR_TIMEOUT => $timeoutSeconds,
                                ]
                            );

                            $GLOBALS['vg_db_driver'] = 'pdo';
                            vg_db_cache_state('connected');
                            return $connection = $pdo;
                        } catch (Throwable $innerException) {
                            vg_db_set_last_error($innerException->getMessage());
                        }
                    } else {
                        vg_db_set_last_error($exception->getMessage());
                    }
                }
            }
        }

        if (extension_loaded('mysqli')) {
            foreach ($hosts as $host) {
                try {
                    mysqli_report(MYSQLI_REPORT_OFF);
                    $mysqli = mysqli_init();
                    if ($mysqli === false) {
                        vg_db_set_last_error('Initialisation mysqli impossible.');
                        continue;
                    }

                    if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
                        @$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, $timeoutSeconds);
                    }
                    if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
                        @$mysqli->options(MYSQLI_OPT_READ_TIMEOUT, $timeoutSeconds);
                    }

                    @$mysqli->real_connect($host, $config['user'], $config['pass'], '', (int) $config['port']);

                    if ($mysqli->connect_errno) {
                        vg_db_set_last_error($mysqli->connect_error);
                        @$mysqli->close();
                        continue;
                    }

                    $mysqli->set_charset($config['charset']);
                    $dbName = str_replace('`', '', $config['name']);
                    $mysqli->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                    if (!$mysqli->select_db($dbName)) {
                        vg_db_set_last_error($mysqli->error);
                        continue;
                    }

                    $GLOBALS['vg_db_driver'] = 'mysqli';
                    vg_db_cache_state('connected');
                    return $connection = $mysqli;
                } catch (Throwable $exception) {
                    vg_db_set_last_error($exception->getMessage());
                }
            }
        }

        $connection = null;
        vg_db_cache_state('failed', vg_db_last_error());
        return null;
    }
}

if (!function_exists('vg_db_connected')) {
    function vg_db_connected(): bool
    {
        return vg_db_connection() !== null;
    }
}

if (!function_exists('vg_db_escape_identifier')) {
    function vg_db_escape_identifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier) ?: '';
    }
}

if (!function_exists('vg_db_quote')) {
    function vg_db_quote($value): string
    {
        if ($value === null || $value === '') {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $connection = vg_db_connection();
        $string = (string) $value;

        if ($connection instanceof PDO) {
            return $connection->quote($string);
        }

        if ($connection instanceof mysqli) {
            return "'" . $connection->real_escape_string($string) . "'";
        }

        return "'" . addslashes($string) . "'";
    }
}

if (!function_exists('vg_db_exec')) {
    function vg_db_exec(string $sql): bool
    {
        $connection = vg_db_connection();
        if ($connection instanceof PDO) {
            try {
                $connection->exec($sql);
                return true;
            } catch (Throwable $exception) {
                vg_db_set_last_error($exception->getMessage());
                return false;
            }
        }

        if ($connection instanceof mysqli) {
            $result = $connection->query($sql);
            if ($result === false) {
                vg_db_set_last_error($connection->error);
                return false;
            }
            return true;
        }

        return false;
    }
}

if (!function_exists('vg_db_query_rows')) {
    function vg_db_query_rows(string $sql): array
    {
        $connection = vg_db_connection();
        if ($connection instanceof PDO) {
            try {
                $stmt = $connection->query($sql);
                $rows = $stmt->fetchAll();
                return is_array($rows) ? $rows : [];
            } catch (Throwable $exception) {
                vg_db_set_last_error($exception->getMessage());
                return [];
            }
        }

        if ($connection instanceof mysqli) {
            $result = $connection->query($sql);
            if ($result === false) {
                vg_db_set_last_error($connection->error);
                return [];
            }

            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }

        return [];
    }
}

if (!function_exists('vg_db_query_value')) {
    function vg_db_query_value(string $sql)
    {
        $rows = vg_db_query_rows($sql);
        if (empty($rows)) {
            return null;
        }

        $first = $rows[0];
        return array_shift($first);
    }
}

if (!function_exists('vg_db_insert_row')) {
    function vg_db_insert_row(string $table, array $payload): bool
    {
        $columns = [];
        $values = [];

        foreach ($payload as $column => $value) {
            $columns[] = '`' . vg_db_escape_identifier((string) $column) . '`';
            $values[] = vg_db_quote($value);
        }

        $sql = 'INSERT INTO `' . vg_db_escape_identifier($table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        return vg_db_exec($sql);
    }
}

if (!function_exists('vg_db_insert_or_update_row')) {
    function vg_db_insert_or_update_row(string $table, array $payload, string $primaryKey = 'id'): bool
    {
        $columns = [];
        $values = [];
        $updates = [];

        foreach ($payload as $column => $value) {
            $clean = vg_db_escape_identifier((string) $column);
            $columns[] = '`' . $clean . '`';
            $values[] = vg_db_quote($value);
            if ($clean !== vg_db_escape_identifier($primaryKey)) {
                $updates[] = '`' . $clean . '`=VALUES(`' . $clean . '`)';
            }
        }

        $sql = 'INSERT INTO `' . vg_db_escape_identifier($table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
        if (!empty($updates)) {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        }

        return vg_db_exec($sql);
    }
}

if (!function_exists('vg_db_has_table')) {
    function vg_db_has_table(string $table): bool
    {
        $safe = vg_db_escape_identifier($table);
        if ($safe === '') {
            return false;
        }

        $value = vg_db_query_value("SHOW TABLES LIKE '{$safe}'");
        return $value !== null;
    }
}

if (!function_exists('vg_db_status')) {
    function vg_db_status(): array
    {
        $config = vg_db_config();
        $connection = vg_db_connection();
        $driver = vg_db_driver();

        if ($connection === null) {
            return [
                'connected' => false,
                'database' => $config['name'],
                'host' => $config['host'] . ':' . $config['port'],
                'message' => 'Connexion MySQL indisponible avec la configuration courante.',
                'details' => vg_db_last_error(),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'mysqli' => extension_loaded('mysqli'),
                'driver' => null,
            ];
        }

        $version = vg_db_query_value('SELECT VERSION() AS version');

        return [
            'connected' => true,
            'database' => $config['name'],
            'host' => $config['host'] . ':' . $config['port'],
            'message' => 'Connexion MySQL active.',
            'details' => '',
            'version' => (string) ($version ?? 'unknown'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mysqli' => extension_loaded('mysqli'),
            'driver' => $driver,
        ];
    }
}
