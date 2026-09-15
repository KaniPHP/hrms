# HRMS Pro - Attendance and Leave Management

Colorful PHP/MySQL admin panel for production and manufacturing teams. It includes a protected admin login, employee directory, shift setup, daily attendance view, leave priority view, and monthly processing workflow.

## Project structure

- `index.php` - role-aware admin and employee login
- `employee/` - employee dashboard, leave self-service, attendance and logout
- `admin/dashboard.php` - KPI dashboard
- `admin/employees.php` - employee roster
- `admin/shifts.php` - day, rotating, and overnight shift setup
- `admin/attendance.php` - daily attendance review
- `admin/punches.php` - manual punch-in/punch-out register and missing-punch source data
- `admin/leaves.php` - leave applications and priority order
- `admin/processing.php` - monthly processing history
- `includes/Database.php` - OOP MySQL connection service
- `includes/AuthService.php` - OOP authentication service
- `includes/layout.php` - shared sidebar, header, and footer
- `database/schema.sql` - complete schema and sample data
- `assets/css/style.css` - colorful responsive design
- `TEST_CASES.md` - manual test cases for all HRMS modules

All five admin modules now provide server-side CRUD forms. Create, edit, and
delete actions use POST requests, prepared statements, CSRF tokens, validation,
redirects, and session flash messages. Processing runs calculate their
employee/attendance totals from the existing schema. Only one processing run
can exist for each month/year; duplicate create and update requests are
rejected, and the database has a unique constraint as a final safeguard.

CRUD forms are progressively submitted through the shared AJAX handler in
`assets/js/app.js`. Each admin page also loads its own page JavaScript file,
while shared behavior remains centralized. Leave applications support full-day
and half-day requests. A half-day request stores `0.5`, requires the same
from/to date, and is recalculated in the browser and verified again on the
server.

AJAX create, update, and delete requests return JSON success/error responses
instead of redirecting to a PHP URL. The shared JavaScript refreshes only the
admin content area after a successful operation, so the page does not perform
a full navigation and validation errors stay on the same page.

Leave balance controls are available in the Leave Balances and Leave History
menus. Applications are checked against the employee's monthly
`closing_balance`; pending and approved applications reserve overlapping dates.
Approved applications debit the balance and create a `leave_transactions`
record. Rejected and cancelled applications do not consume balance. The leave
type display and processing order is always `WO -> EL -> FL -> CPL -> CL`.
CL can only be used when the employee has a configured positive balance, and
carry-forward and utilization are visible in the employee history screens.

## XAMPP setup

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/phpmyadmin`.
3. Import `database/schema.sql`. It creates the `hrms_db` database, tables, indexes, and sample data.
4. Import `database/migration_attendance_punches.sql` to add indexed punch storage, attendance correction history, and the `audit_logs` table.
5. Place this project at `C:\xampp\htdocs\HRMS`.
6. Open `http://localhost/HRMS/`.

Employee login credentials:

| Employee | Username | Password |
|---|---|---|
| Ravi Kumar | `ravi2026` | `Ravi2026` |
| Ananya Verma | `anan2026` | `Anan2026` |
| Suresh Nair | `sure2026` | `Sure2026` |
| Meera Shah | `meer2026` | `Meer2026` |

The username uses the first four letters of the employee name in lowercase
plus the credential year. The password uses the same four letters with an
initial capital plus the year. Run `database/migration_employee_auth.sql` for
an existing database, or use the credentials already included in the fresh
schema.

When an employee is created from the admin panel, `login_username`,
`password_hash`, and `credential_year` are generated automatically. The
temporary password is shown once in the creation success message. If the
generated username already exists, a numeric suffix is added to keep it
unique.

Employee full names must contain at least four letters. Letters, spaces,
apostrophes, hyphens, and periods are allowed.

Optional demo leave data:

Import `database/dummy_leave_data_2026.sql` after the main schema. The
date-aware script creates the two previous months, the current month with no
dummy usage, and the next month's balance row. On 2026-09-13 this means July
and August usage, September with all leave unused, and October with CL carried
from September. Re-running it removes and rebuilds only the date-based dummy
months, including future dummy rows. Each month calculates earned, utilized,
closing, and CL carry-forward values.

Employee portal modules:

- `/employee/dashboard.php` - employee summary
- `/employee/leave.php` - monthly balances, apply leave, and cancel pending leave
- `/employee/attendance.php` - monthly attendance history
- `/employee/logout.php` - employee logout

Administrators approve or reject pending employee requests from the Leave
Management menu. Approved requests debit the employee balance and create a
leave transaction; rejected requests do not consume balance.

Default login:

- Email: `admin@hrms.com`
- Password: `admin123`

Change the seeded password before production use. Update database credentials in `includes/config.php` when the MySQL server is not using the XAMPP defaults.

## Implementation plan

### 1. Shift-based punch handling

- Store employee punches in `attendance_punches` and match them to the assigned shift.
- Use shift grace, late threshold, and overtime settings to calculate late, early-out, missing-punch, and overtime minutes.
- For overnight shifts, when end time is earlier than start time, use the next calendar day for the shift end.

### 2. Punch-to-attendance calculation

- Pair the first valid punch-in and last valid punch-out per employee and shift date.
- Create one unique `attendance_records` row per employee/date.
- Generate Present, Absent, Half Day, Late, Early Out, Weekly Off, Leave, and manual adjustment statuses.
- Authorized corrections should retain the original punch and record a correction/audit transaction.

### 3. Leave calculation

- Apply leave strictly in this order: `WO -> EL -> FL -> CPL -> CL`.
- Deduct only available balances inside a database transaction.
- Reject negative closing balances unless a future company policy explicitly permits them.
- Record every credit, debit, adjustment, and carry-forward in `leave_transactions`.
- Support partial days with decimal leave amounts.

### 4. CL carry-forward

- At month close, carry only unused CL from the current month to the immediately following month.
- Never chain a previous carry-forward into another future carry-forward.
- Store opening, earned, utilized, carry-forward, and closing values in `employee_leave_balances`.

### 5. Monthly attendance processing

- Lock the month, read punches, calculate attendance, apply leave, and update balances in one transaction.
- Save processor, status, employee count, attendance count, and completion time in `monthly_attendance_processing`.
- Keep review and approval separate from calculation so HR can inspect exceptions.

## Next production tasks

1. Add POST forms and CSRF validation for employee, shift, punch, leave, and correction actions.
2. Add OOP services for punch pairing, attendance calculation, leave priority deduction, and CL carry-forward.
3. Add role permissions and CSV/PDF exports.
4. Add automated tests for overnight shifts, missing punches, half days, partial leave, and month-end carry-forward.
