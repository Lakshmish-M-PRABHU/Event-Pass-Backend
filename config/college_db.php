<?php
// Optional .env support for local/dev setups.
// This does not override existing environment variables.
$envPath = __DIR__ . "/../.env";
if (file_exists($envPath)) {
  $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "" || str_starts_with($line, "#")) {
      continue;
    }
    $parts = explode("=", $line, 2);
    if (count($parts) !== 2) {
      continue;
    }
    $key = trim($parts[0]);
    $value = trim($parts[1]);
    if ($key === "") {
      continue;
    }
    if (strlen($value) >= 2) {
      $first = $value[0];
      $last = $value[strlen($value) - 1];
      if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
        $value = substr($value, 1, -1);
      }
    }
    if (getenv($key) === false) {
      putenv("{$key}={$value}");
      $_ENV[$key] = $value;
      $_SERVER[$key] = $value;
    }
  }
}

$dbUrl = getenv("COLLEGE_DB_URL") ?: "mysql://root:@localhost:3306/college_db";

$parts = parse_url($dbUrl);
if ($parts === false) {
  throw new Exception("Invalid COLLEGE_DB_URL");
}

$host = $parts["host"] ?? "localhost";
$port = $parts["port"] ?? 3306;
$user = $parts["user"] ?? "root";
$pass = $parts["pass"] ?? "";
$dbName = isset($parts["path"]) ? ltrim($parts["path"], "/") : "college_db";
$query = [];
if (!empty($parts["query"])) {
  parse_str($parts["query"], $query);
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];

// Optional SSL settings for hosted DBs (Aiven, etc.)
if (isset($query["ssl-mode"]) && strtoupper((string)$query["ssl-mode"]) === "REQUIRED") {
  $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
  $sslCa = getenv("COLLEGE_DB_SSL_CA") ?: getenv("COLLEGE_DB_SSL");
  if ($sslCa) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
  }
}

$collegeDB = new PDO($dsn, $user, $pass, $options);
