```php
<?php
$host = "127.0.0.1";
$username = "";       '''YOUR ACTUAL USERNAME'''
$password = "";        '''YOUR ACTUAL PASS'''
$database = "harborwing_air";
$port = 3306;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $host,
        $username,
        $password,
        $database,
        $port
    );

    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    exit("Database connection failed. Check your local MySQL settings.");
}
?>
```
