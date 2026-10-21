# HarborWing Air CRUD System

## Project Description

HarborWing Air is a PHP and MySQL database management system for managing flight reservation records. The application connects to the `harborwing_air` database and provides a web interface for performing Create, Read, Update, and Delete (CRUD) operations.

## Technologies Used

- PHP
- MySQL
- MySQL Workbench
- XAMPP
- HTML
- CSS
- Git and GitHub

## Database Tables

The application manages the following tables:

1. `passengers`
2. `booking_agents`
3. `reservations`
4. `payments`
5. `airports`
6. `flight_schedules`
7. `flight_legs`
8. `flight_costs`

The system also uses `reservation_legs` and `reservation_payments` to manage relationships between reservations, flight legs, and payments.

## Features

- Add new records.
- View existing records.
- Update existing records.
- Delete records when database relationships allow deletion.
- Search records by keyword.
- View a reservation with its passenger and booking agent.
- Link existing flight legs to a reservation.
- Unlink flight legs without deleting the original flight leg records.
- Link existing payments to a reservation.
- Unlink payments without deleting the original payment records.
- Validate selected user inputs.
- Use prepared SQL statements for database operations.
- Display helpful error messages for invalid or restricted operations.

## Installation and Setup

1. Install and open XAMPP.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open MySQL Workbench and confirm that the `harborwing_air` schema and its tables exist.
4. Copy the project folder into the XAMPP `htdocs` directory.
5. Open `db.php` and confirm the database host, port, username, password, and database name.
6. If your database has a password, use your local database password in `db.php`. Do not commit passwords to GitHub.
7. Open a browser and visit:

   `http://localhost/HarborWing_Air_CRUD/`

8. Test adding, viewing, editing, deleting, searching, and managing reservation relationships.

## Project Files

- `db.php` — database connection.
- `index.php` — CRUD pages for the eight main tables.
- `reservation_view.php` — reservation details and payment/flight-leg relationships.
- `style.css` — website styling.
- `TESTING_GUIDE.md` — manual testing checklist.
- `.gitignore` — files excluded from version control.

## Database Safety

Deleting a linked record may be blocked by database foreign-key constraints. This helps protect related data. To remove a payment or flight leg from a reservation, use the Unlink function on the reservation details page instead of deleting the original record.

## Limitations

This is an educational project. Before deployment in a real environment, it should be enhanced with authentication, authorization, CSRF protection, stronger validation, and production-safe error logging.


