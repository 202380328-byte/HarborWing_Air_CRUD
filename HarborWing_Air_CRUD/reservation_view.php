```php
<?php
require_once "db.php";

function escape($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$reservationId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$message = "";
$error = "";

if (!$reservationId || $reservationId < 1) {
    exit("Invalid reservation ID. Open this page from the Reservations table.");
}

/* HANDLE LINKING AND UNLINKING */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    try {
        if ($action === "link_leg") {
            $legId = filter_input(INPUT_POST, "leg_id", FILTER_VALIDATE_INT);

            if (!$legId || $legId < 1) {
                throw new Exception("Please select a valid flight leg.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO reservation_legs (reservation_id, leg_id)
                 VALUES (?, ?)"
            );
            $stmt->bind_param("ii", $reservationId, $legId);
            $stmt->execute();
            $stmt->close();

            $message = "Flight leg linked successfully.";
        } elseif ($action === "unlink_leg") {
            $legId = filter_input(INPUT_POST, "leg_id", FILTER_VALIDATE_INT);

            if (!$legId || $legId < 1) {
                throw new Exception("Invalid flight leg.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM reservation_legs
                 WHERE reservation_id = ? AND leg_id = ?"
            );
            $stmt->bind_param("ii", $reservationId, $legId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("That flight leg was not linked to this reservation.");
            }

            $stmt->close();
            $message = "Flight leg unlinked. The flight leg record was not deleted.";
        } elseif ($action === "link_payment") {
            $paymentId = filter_input(INPUT_POST, "payment_id", FILTER_VALIDATE_INT);

            if (!$paymentId || $paymentId < 1) {
                throw new Exception("Please select a valid payment.");
            }

            $stmt = $conn->prepare(
                "INSERT INTO reservation_payments (reservation_id, payment_id)
                 VALUES (?, ?)"
            );
            $stmt->bind_param("ii", $reservationId, $paymentId);
            $stmt->execute();
            $stmt->close();

            $message = "Payment linked successfully.";
        } elseif ($action === "unlink_payment") {
            $paymentId = filter_input(INPUT_POST, "payment_id", FILTER_VALIDATE_INT);

            if (!$paymentId || $paymentId < 1) {
                throw new Exception("Invalid payment.");
            }

            $stmt = $conn->prepare(
                "DELETE FROM reservation_payments
                 WHERE reservation_id = ? AND payment_id = ?"
            );
            $stmt->bind_param("ii", $reservationId, $paymentId);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                throw new Exception("That payment was not linked to this reservation.");
            }

            $stmt->close();
            $message = "Payment unlinked. The payment record was not deleted.";
        } else {
            throw new Exception("Unknown action.");
        }
    } catch (Throwable $e) {
        if ($e instanceof mysqli_sql_exception) {
            if ((int)$e->getCode() === 1062) {
                $error = "This item is already linked to the reservation.";
            } else {
                $error = "The operation failed. Check that the selected reservation, flight leg, or payment exists.";
            }
        } else {
            $error = $e->getMessage();
        }
    }
}

/* LOAD RESERVATION, PASSENGER, AND AGENT */
$stmt = $conn->prepare(
    "SELECT
        r.reservation_id,
        r.booking_reference,
        r.reservation_date,
        r.reservation_status,
        r.party_size,
        p.first_name AS passenger_first_name,
        p.last_name AS passenger_last_name,
        p.email AS passenger_email,
        a.first_name AS agent_first_name,
        a.last_name AS agent_last_name,
        a.email AS agent_email
     FROM reservations r
     JOIN passengers p ON r.passenger_id = p.passenger_id
     JOIN booking_agents a ON r.agent_id = a.agent_id
     WHERE r.reservation_id = ?"
);

$stmt->bind_param("i", $reservationId);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reservation) {
    exit("Reservation not found.");
}

/* LOAD FLIGHT LEGS LINKED TO THIS RESERVATION */
$stmt = $conn->prepare(
    "SELECT
        fl.leg_id,
        fl.flight_number,
        fl.leg_number,
        fl.departure_airport,
        fl.arrival_airport,
        fl.departure_datetime,
        fl.arrival_datetime
     FROM reservation_legs rl
     JOIN flight_legs fl ON rl.leg_id = fl.leg_id
     WHERE rl.reservation_id = ?
     ORDER BY fl.leg_number"
);

$stmt->bind_param("i", $reservationId);
$stmt->execute();
$linkedLegs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* LOAD AVAILABLE FLIGHT LEGS THAT ARE NOT LINKED */
$stmt = $conn->prepare(
    "SELECT leg_id, flight_number, leg_number,
            departure_airport, arrival_airport
     FROM flight_legs
     WHERE leg_id NOT IN (
         SELECT leg_id
         FROM reservation_legs
         WHERE reservation_id = ?
     )
     ORDER BY flight_number, leg_number"
);

$stmt->bind_param("i", $reservationId);
$stmt->execute();
$availableLegs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* LOAD PAYMENTS LINKED TO THIS RESERVATION */
$stmt = $conn->prepare(
    "SELECT
        p.payment_id,
        p.payment_date,
        p.payment_method,
        p.payment_amount,
        p.payment_status,
        p.transaction_reference
     FROM reservation_payments rp
     JOIN payments p ON rp.payment_id = p.payment_id
     WHERE rp.reservation_id = ?
     ORDER BY p.payment_date DESC"
);

$stmt->bind_param("i", $reservationId);
$stmt->execute();
$linkedPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* LOAD AVAILABLE PAYMENTS THAT ARE NOT LINKED */
$stmt = $conn->prepare(
    "SELECT payment_id, payment_date, payment_method,
            payment_amount, payment_status
     FROM payments
     WHERE payment_id NOT IN (
         SELECT payment_id
         FROM reservation_payments
         WHERE reservation_id = ?
     )
     ORDER BY payment_date DESC"
);

$stmt->bind_param("i", $reservationId);
$stmt->execute();
$availablePayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Details - HarborWing Air</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>HarborWing Air</h1>
    <p>Reservation Details and Relationship Management</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="index.php?table=reservations">Reservations</a>
    <a href="index.php?table=flight_legs">Flight Legs</a>
    <a href="index.php?table=payments">Payments</a>
</nav>

<main>
    <h2>Reservation Details</h2>

    <?php if ($message !== ""): ?>
        <p class="success"><?= escape($message) ?></p>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <p class="error"><?= escape($error) ?></p>
    <?php endif; ?>

    <section class="panel">
        <h3>Reservation Information</h3>

        <p><strong>Reservation ID:</strong>
            <?= escape($reservation["reservation_id"]) ?></p>

        <p><strong>Booking Reference:</strong>
            <?= escape($reservation["booking_reference"]) ?></p>

        <p><strong>Date:</strong>
            <?= escape($reservation["reservation_date"]) ?></p>

        <p><strong>Status:</strong>
            <?= escape($reservation["reservation_status"]) ?></p>

        <p><strong>Party Size:</strong>
            <?= escape($reservation["party_size"]) ?></p>

        <h3>Passenger</h3>
        <p>
            <?= escape($reservation["passenger_first_name"]) ?>
            <?= escape($reservation["passenger_last_name"]) ?>
        </p>
        <p>Email: <?= escape($reservation["passenger_email"]) ?></p>

        <h3>Booking Agent</h3>
        <p>
            <?= escape($reservation["agent_first_name"]) ?>
            <?= escape($reservation["agent_last_name"]) ?>
        </p>
        <p>Email: <?= escape($reservation["agent_email"]) ?></p>

        <a class="cancel-button"
           href="index.php?table=reservations">Back to Reservations</a>
    </section>

    <section class="panel">
        <h3>Linked Flight Legs</h3>

        <?php if (count($linkedLegs) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Leg ID</th>
                            <th>Flight</th>
                            <th>Leg Number</th>
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Departure Time</th>
                            <th>Arrival Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkedLegs as $leg): ?>
                            <tr>
                                <td><?= escape($leg["leg_id"]) ?></td>
                                <td><?= escape($leg["flight_number"]) ?></td>
                                <td><?= escape($leg["leg_number"]) ?></td>
                                <td><?= escape($leg["departure_airport"]) ?></td>
                                <td><?= escape($leg["arrival_airport"]) ?></td>
                                <td><?= escape($leg["departure_datetime"]) ?></td>
                                <td><?= escape($leg["arrival_datetime"]) ?></td>
                                <td>
                                    <form method="POST"
                                          onsubmit="return confirm('Unlink this flight leg? The flight leg itself will not be deleted.');">
                                        <input type="hidden" name="action" value="unlink_leg">
                                        <input type="hidden" name="leg_id"
                                               value="<?= escape($leg["leg_id"]) ?>">
                                        <button type="submit" class="delete-button">
                                            Unlink
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No flight legs are linked to this reservation yet.</p>
        <?php endif; ?>

        <h3>Link an Existing Flight Leg</h3>

        <?php if (count($availableLegs) > 0): ?>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="link_leg">

                <select name="leg_id" required>
                    <option value="">Choose a flight leg</option>
                    <?php foreach ($availableLegs as $leg): ?>
                        <option value="<?= escape($leg["leg_id"]) ?>">
                            <?= escape($leg["flight_number"]) ?>
                            — Leg <?= escape($leg["leg_number"]) ?>:
                            <?= escape($leg["departure_airport"]) ?>
                            to <?= escape($leg["arrival_airport"]) ?>
                            (ID <?= escape($leg["leg_id"]) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Link Flight Leg</button>
            </form>
        <?php else: ?>
            <p>No unlinked flight legs are available.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Linked Payments</h3>

        <?php if (count($linkedPayments) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Transaction Reference</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linkedPayments as $payment): ?>
                            <tr>
                                <td><?= escape($payment["payment_id"]) ?></td>
                                <td><?= escape($payment["payment_date"]) ?></td>
                                <td><?= escape($payment["payment_method"]) ?></td>
                                <td><?= escape($payment["payment_amount"]) ?></td>
                                <td><?= escape($payment["payment_status"]) ?></td>
                                <td><?= escape($payment["transaction_reference"]) ?></td>
                                <td>
                                    <form method="POST"
                                          onsubmit="return confirm('Unlink this payment? The payment itself will not be deleted.');">
                                        <input type="hidden" name="action" value="unlink_payment">
                                        <input type="hidden" name="payment_id"
                                               value="<?= escape($payment["payment_id"]) ?>">
                                        <button type="submit" class="delete-button">
                                            Unlink
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No payments are linked to this reservation yet.</p>
        <?php endif; ?>

        <h3>Link an Existing Payment</h3>

        <?php if (count($availablePayments) > 0): ?>
            <form method="POST" class="inline-form">
                <input type="hidden" name="action" value="link_payment">

                <select name="payment_id" required>
                    <option value="">Choose a payment</option>
                    <?php foreach ($availablePayments as $payment): ?>
                        <option value="<?= escape($payment["payment_id"]) ?>">
                            Payment #<?= escape($payment["payment_id"]) ?>
                            — <?= escape($payment["payment_date"]) ?>
                            — <?= escape($payment["payment_method"]) ?>
                            — <?= escape($payment["payment_amount"]) ?>
                            (<?= escape($payment["payment_status"]) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Link Payment</button>
            </form>
        <?php else: ?>
            <p>No unlinked payments are available.</p>
        <?php endif; ?>
    </section>
</main>

<footer>
    <p>HarborWing Air Database Management System</p>
</footer>

</body>
</html>
```
