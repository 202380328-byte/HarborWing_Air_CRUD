```php
<?php
require_once "db.php";

$modules = [
    "passengers" => [
        "title" => "Passengers",
        "pk" => "passenger_id",
        "fields" => [
            "first_name" => "First Name",
            "last_name" => "Last Name",
            "email" => "Email",
            "phone" => "Phone",
            "birth_date" => "Birth Date",
            "passport_number" => "Passport Number"
        ]
    ],
    "booking_agents" => [
        "title" => "Booking Agents",
        "pk" => "agent_id",
        "fields" => [
            "first_name" => "First Name",
            "last_name" => "Last Name",
            "email" => "Email",
            "phone" => "Phone"
        ]
    ],
    "reservations" => [
        "title" => "Reservations",
        "pk" => "reservation_id",
        "fields" => [
            "booking_reference" => "Booking Reference",
            "passenger_id" => "Passenger ID",
            "agent_id" => "Agent ID",
            "reservation_date" => "Reservation Date",
            "reservation_status" => "Status",
            "party_size" => "Party Size"
        ]
    ],
    "payments" => [
        "title" => "Payments",
        "pk" => "payment_id",
        "fields" => [
            "payment_date" => "Payment Date",
            "payment_method" => "Payment Method",
            "payment_amount" => "Amount",
            "payment_status" => "Payment Status",
            "transaction_reference" => "Transaction Reference"
        ]
    ],
    "airports" => [
        "title" => "Airports",
        "pk" => "airport_code",
        "fields" => [
            "airport_code" => "Airport Code",
            "airport_name" => "Airport Name",
            "city" => "City",
            "country" => "Country"
        ]
    ],
    "flight_schedules" => [
        "title" => "Flight Schedules",
        "pk" => "flight_number",
        "fields" => [
            "flight_number" => "Flight Number",
            "airline_name" => "Airline Name",
            "aircraft_type_code" => "Aircraft Type",
            "origin_airport" => "Origin Airport",
            "destination_airport" => "Destination Airport",
            "departure_datetime" => "Departure Date and Time",
            "arrival_datetime" => "Arrival Date and Time"
        ]
    ],
    "flight_legs" => [
    "title" => "Flight Legs",
    "pk" => "leg_id",
    "fields" => [
        "leg_id" => "Leg ID",
        "flight_number" => "Flight Number",
        "leg_number" => "Leg Number",
        "departure_airport" => "Departure Airport",
        "arrival_airport" => "Arrival Airport",
        "departure_datetime" => "Departure Date and Time",
        "arrival_datetime" => "Arrival Date and Time"
    ]
],
    "flight_costs" => [
        "title" => "Flight Costs",
        "pk" => "flight_cost_id",
        "fields" => [
            "flight_number" => "Flight Number",
            "aircraft_type_code" => "Aircraft Type",
            "valid_from" => "Valid From",
            "valid_to" => "Valid To",
            "cost_amount" => "Cost Amount",
            "currency_code" => "Currency Code"
        ]
    ]
];

$table = $_GET["table"] ?? "passengers";

if (!isset($modules[$table])) {
    $table = "passengers";
}

$config = $modules[$table];
$pk = $config["pk"];
$message = "";
$error = "";
$editRecord = null;

function escape($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

function getInputType($field) {
    if (str_contains($field, "datetime")) {
        return "datetime-local";
    }

    if (str_contains($field, "date")) {
        return "date";
    }

    if (in_array($field, ["payment_amount", "cost_amount"])) {
        return "number";
    }

    if (in_array($field, ["passenger_id", "agent_id", "party_size", "leg_number"])) {
        return "number";
    }

    return "text";
}

function cleanInput($field, $value) {
    if ($value === "") {
        return null;
    }

    if (str_contains($field, "datetime")) {
        return str_replace("T", " ", $value) . ":00";
    }

    return trim($value);
}

/* CREATE OR UPDATE RECORD */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "save") {
            $recordId = $_POST["record_id"] ?? "";
            $data = [];

            foreach ($config["fields"] as $field => $label) {
                if ($field === $pk && $recordId !== "") {
                    continue;
                }

                $value = $_POST[$field] ?? "";
                $data[$field] = cleanInput($field, $value);
            }

            if (isset($data["email"]) &&
                !filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }

            if (isset($data["party_size"]) &&
                ((int)$data["party_size"] < 1 || !is_numeric($data["party_size"]))) {
                throw new Exception("Party size must be at least 1.");
            }

            if (isset($data["payment_amount"]) && $data["payment_amount"] < 0) {
                throw new Exception("Payment amount cannot be negative.");
            }

            if (isset($data["cost_amount"]) && $data["cost_amount"] < 0) {
                throw new Exception("Flight cost cannot be negative.");
            }

            if (isset($data["departure_datetime"], $data["arrival_datetime"]) &&
                $data["departure_datetime"] !== null &&
                $data["arrival_datetime"] !== null &&
                strtotime($data["arrival_datetime"]) <= strtotime($data["departure_datetime"])) {
                throw new Exception("Arrival must be later than departure.");
            }

            if (isset($data["valid_from"], $data["valid_to"]) &&
                $data["valid_from"] !== null &&
                $data["valid_to"] !== null &&
                $data["valid_to"] < $data["valid_from"]) {
                throw new Exception("Valid To cannot be earlier than Valid From.");
            }

            if (isset($data["airport_code"]) &&
                !preg_match("/^[A-Za-z0-9]{3}$/", $data["airport_code"])) {
                throw new Exception("Airport code must contain exactly 3 letters or numbers.");
            }

            if ($recordId !== "") {
                $setParts = [];

                foreach (array_keys($data) as $field) {
                    $setParts[] = "`$field` = ?";
                }

                $sql = "UPDATE `$table` SET " .
                       implode(", ", $setParts) .
                       " WHERE `$pk` = ?";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception("Could not prepare the update.");
                }

                $values = array_values($data);
                $values[] = $recordId;
                $types = str_repeat("s", count($values));

                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();

                $message = "Record updated successfully.";
            } else {
                $columns = array_keys($data);
                $placeholders = array_fill(0, count($columns), "?");

                $sql = "INSERT INTO `$table` (`" .
                       implode("`, `", $columns) .
                       "`) VALUES (" .
                       implode(", ", $placeholders) . ")";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {
                    throw new Exception("Could not prepare the insert.");
                }

                $values = array_values($data);
                $types = str_repeat("s", count($values));

                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $stmt->close();

                $message = "Record added successfully.";
            }
        }

        /* DELETE RECORD */
        if ($action === "delete") {
            $recordId = $_POST["record_id"] ?? "";

            if ($recordId === "") {
                throw new Exception("Missing record ID.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM `$table` WHERE `$pk` = ?"
            );

            if (!$stmt) {
                throw new Exception("Could not prepare the delete.");
            }

            $stmt->bind_param("s", $recordId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception("No record was deleted.");
            }

            $stmt->close();
            $message = "Record deleted successfully.";
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception) {
            $error = "The operation could not be completed. Check required fields, duplicate values, and related records.";
        } else {
            $error = $e->getMessage();
        }
    }
}

/* LOAD RECORD FOR EDITING */
if (isset($_GET["edit"])) {
    $recordId = $_GET["edit"];

    $stmt = $conn->prepare(
        "SELECT * FROM `$table` WHERE `$pk` = ?"
    );

    $stmt->bind_param("s", $recordId);
    $stmt->execute();

    $result = $stmt->get_result();
    $editRecord = $result->fetch_assoc();
    $stmt->close();
}

/* SEARCH AND DISPLAY RECORDS */
$search = trim($_GET["search"] ?? "");
$columns = array_keys($config["fields"]);

$sql = "SELECT * FROM `$table`";
$params = [];
$types = "";

if ($search !== "" && count($columns) > 0) {
    $conditions = [];

    foreach ($columns as $column) {
        $conditions[] = "CAST(`$column` AS CHAR) LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }

    $sql .= " WHERE " . implode(" OR ", $conditions);
}

$sql .= " ORDER BY `$pk` DESC LIMIT 100";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $records = $stmt->get_result();
} else {
    $records = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HarborWing Air - CRUD System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>HarborWing Air</h1>
    <p>Flight Reservation Database Management System</p>
</header>

<nav>
    <?php foreach ($modules as $key => $module): ?>
        <a href="index.php?table=<?= escape($key) ?>">
            <?= escape($module["title"]) ?>
        </a>
    <?php endforeach; ?>
    <a href="reservation_view.php">Reservation Details</a>
</nav>

<main>
    <h2><?= escape($config["title"]) ?></h2>

    <?php if ($message !== ""): ?>
        <p class="success"><?= escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error"><?= escape($error) ?></p>
    <?php endif; ?>

    <section class="panel">
        <h3><?= $editRecord ? "Edit Record" : "Add New Record" ?></h3>

        <form method="POST" action="index.php?table=<?= escape($table) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="record_id"
                   value="<?= escape($editRecord[$pk] ?? "") ?>">

            <div class="form-grid">
                <?php foreach ($config["fields"] as $field => $label): ?>
                    <?php
                    if ($editRecord && $field === $pk) {
                        continue;
                    }

                    $value = $editRecord[$field] ?? "";
                    $inputType = getInputType($field);

                    if ($inputType === "datetime-local" && $value !== "") {
                        $value = date("Y-m-d\TH:i", strtotime($value));
                    }
                    ?>
                    <label>
                        <?= escape($label) ?>

                        <input
                            type="<?= escape($inputType) ?>"
                            name="<?= escape($field) ?>"
                            value="<?= escape($value) ?>"
                            <?= in_array($field, [
                                "first_name", "last_name", "email",
                                "airport_code", "airport_name", "city",
                                "country", "flight_number", "booking_reference"
                            ]) ? "required" : "" ?>
                            <?= $inputType === "number" ? 'step="any"' : "" ?>
                        >
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit">
                <?= $editRecord ? "Update Record" : "Add Record" ?>
            </button>

            <?php if ($editRecord): ?>
                <a class="cancel-button"
                   href="index.php?table=<?= escape($table) ?>">
                    Cancel
                </a>
            <?php endif; ?>
        </form>
    </section>

    <section class="panel">
        <h3>Search <?= escape($config["title"]) ?></h3>

        <form method="GET" action="index.php" class="search-form">
            <input type="hidden" name="table" value="<?= escape($table) ?>">

            <input type="text" name="search"
                   placeholder="Enter keyword..."
                   value="<?= escape($search) ?>">

            <button type="submit">Search</button>
            <a class="cancel-button"
               href="index.php?table=<?= escape($table) ?>">
                Clear
            </a>
        </form>
    </section>

    <section class="panel">
        <h3>Records (maximum 100 displayed)</h3>

        <?php if ($records && $records->num_rows > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= escape($config["fields"][$column]) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($row = $records->fetch_assoc()): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td><?= escape($row[$column] ?? "") ?></td>
                                <?php endforeach; ?>

                                <td class="actions">
                                    <a href="index.php?table=<?= escape($table) ?>&edit=<?= urlencode($row[$pk]) ?>">
                                        Edit
                                    </a>

                                    <form method="POST"
                                          action="index.php?table=<?= escape($table) ?>"
                                          onsubmit="return confirm('Are you sure you want to delete this record?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="record_id"
                                               value="<?= escape($row[$pk]) ?>">
                                        <button type="submit" class="delete-button">
                                            Delete
                                        </button>
                                    </form>

                                    <?php if ($table === "reservations"): ?>
                                        <a href="reservation_view.php?id=<?= urlencode($row[$pk]) ?>">
                                            View Details
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No records found.</p>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>HarborWing Air Database Management System</p>
</footer>

</body>
</html>
