# HRMS Test Cases

These manual test cases cover the admin and employee modules of the HRMS
Attendance and Leave Management system. Execute them against a test database
with Apache and MySQL running. Record the actual result and mark each case
`Pass` or `Fail`.

## Test data and conventions

- Admin: `admin@hrms.com` / `admin123`
- Employee examples are documented in `README.md`.
- Use dates in the UI as `DD/MM/YYYY`.
- Store and verify database dates as `YYYY-MM-DD`.
- The examples below assume:
  - General shift: `09:00 - 18:00`
  - Rotating shift: `08:00 - 17:00`
  - Night shift: `20:00 - 05:00`
- Replace employee IDs and dates with records in the test database.
- For every failed case, verify that no partial database record was created.

## 1. Authentication and access control

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| AUTH-001 | Admin login succeeds | Open the login page, select Administrator, enter valid admin credentials, submit. | Admin dashboard opens and admin navigation is visible. |
| AUTH-002 | Employee login succeeds | Select Employee, enter valid employee credentials, submit. | Employee dashboard opens and admin pages are not shown. |
| AUTH-003 | Invalid credentials | Submit an incorrect password or username. | Login fails with a clear error and no session is created. |
| AUTH-004 | Required login fields | Submit the login form with an empty username or password. | Browser/server validation prevents login. |
| AUTH-005 | Admin page without login | Open `/admin/dashboard.php` in a new logged-out browser session. | User is redirected to the login page. |
| AUTH-006 | Employee page without login | Open `/employee/attendance.php` in a new logged-out browser session. | User is redirected to the login page. |
| AUTH-007 | Role separation | Log in as an employee and request an admin URL. | Access is denied or redirected; admin data is not displayed. |
| AUTH-008 | Logout | Log in, click logout, then use browser Back and open a protected URL. | Session is destroyed and protected content is unavailable. |
| AUTH-009 | CSRF protection | Remove or alter the CSRF token on a POST form. | Request is rejected with an invalid-token message. |

## 2. Employee management

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| EMP-001 | Create employee | Enter valid employee code, name, contact, status, and shift; submit. | Employee is created once and appears in the employee list. |
| EMP-002 | Required employee fields | Submit with a required field empty. | Validation error is shown and no employee is created. |
| EMP-003 | Duplicate employee code | Create an employee using an existing employee code. | Duplicate is rejected with a clear error. |
| EMP-004 | Invalid employee data | Enter malformed email, invalid status, or invalid shift ID. | Invalid values are rejected server-side. |
| EMP-005 | Edit employee | Open an employee, change name/status/shift, and save. | Existing record is updated without creating a second record. |
| EMP-006 | Delete employee | Delete an employee with no dependent records. | Employee is removed and list refreshes correctly. |
| EMP-007 | Employee with attendance history | Attempt to delete an employee that has attendance, punch, or leave history. | Operation is safely rejected or handled according to configured foreign-key policy; history is not orphaned. |
| EMP-008 | Employee pagination | Create more than one page of employees and navigate first, middle, last, and invalid pages. | Correct records display, page links work, and invalid pages are clamped safely. |

## 3. Shift management

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| SHIFT-001 | Create regular shift | Create `09:00 - 18:00` with grace, late, and overtime settings. | Shift is saved and displayed with configured values. |
| SHIFT-002 | Create overnight shift | Create `20:00 - 05:00` and enable night shift. | Shift is accepted as an overnight shift. |
| SHIFT-003 | Invalid shift time | Submit a missing time, malformed time, or invalid grace/threshold value. | Validation rejects the request. |
| SHIFT-004 | Edit shift | Change shift timing or thresholds. | Changes are saved and used by later punch calculations. |
| SHIFT-005 | Delete assigned shift | Attempt to delete a shift assigned to an employee. | Delete is prevented or safely handled without breaking employee records. |
| SHIFT-006 | Shift pagination | Create enough shifts for multiple pages and navigate pages. | Correct page results and stable pagination are shown. |

## 4. Punch-in and punch-out

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| PUNCH-001 | Valid regular punch-in | Assign General shift; record IN on `15/09/2026 09:00`. | One IN punch is saved with the employee shift. |
| PUNCH-002 | Valid regular punch-out | Record OUT on `15/09/2026 18:00` after PUNCH-001. | OUT is saved and attendance is recalculated. |
| PUNCH-003 | Duplicate punch-in | Record another IN for the same employee and shift date. | Request is rejected; only one IN exists. |
| PUNCH-004 | Duplicate punch-out | Record another OUT for the same employee and shift date. | Request is rejected; only one OUT exists. |
| PUNCH-005 | OUT before IN | Submit OUT before any matching IN. | Request is rejected with a punch-in-required message. |
| PUNCH-006 | OUT before punch time | Submit OUT earlier than the matching IN. | Request is rejected; no attendance change occurs. |
| PUNCH-007 | Missing assigned shift | Remove the employee shift and submit a punch. | Request is rejected and the user is told to assign a shift. |
| PUNCH-008 | Regular-shift date boundary | Record General-shift IN on `15/09/2026`, then attempt OUT on `16/09/2026`. | Cross-day OUT is rejected for a regular shift. |
| PUNCH-009 | Overnight punch pairing | Record Night-shift IN at `15/09/2026 20:00` and OUT at `16/09/2026 05:00`. | OUT pairs with the previous day’s IN and no duplicate IN is allowed in between. |
| PUNCH-010 | Overnight duplicate IN | With the overnight IN still open, submit another IN on `16/09/2026`. | Request is rejected until the open shift is closed. |
| PUNCH-011 | Overnight early OUT | Use IN `15/09/2026 20:00`, OUT `16/09/2026 04:30`. | Attendance contains 30 early-out minutes. |
| PUNCH-012 | Overtime | Use Night-shift OUT after the configured overtime threshold. | Overtime minutes equal actual OUT after shift end minus the configured threshold. |
| PUNCH-013 | Late arrival | Use a shift with 10-minute grace and late threshold; punch after the configured threshold. | Late minutes are calculated from shift start and grace according to the configured rule. |
| PUNCH-014 | Punch date format | Submit valid `DD/MM/YYYY` input and an invalid calendar date. | Valid date is accepted; invalid date is rejected clearly. |
| PUNCH-015 | Punch transaction failure | Force a database failure during attendance recalculation. | Punch and attendance changes roll back together; no partial record remains. |
| PUNCH-016 | Punch pagination and filters | Use date and IN/OUT filters, then navigate pages. | Results match filters and pagination preserves filter parameters. |

## 5. Attendance calculation and review

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| ATT-001 | Present attendance | Record valid IN and OUT at shift boundaries. | One attendance row is created with Present status and correct work minutes. |
| ATT-002 | Late attendance | Record a late IN and valid OUT. | Status is Late and late minutes are correct. |
| ATT-003 | Early-out attendance | Record valid IN and OUT before shift end. | Status is Early Out and early-out minutes are correct. |
| ATT-004 | Overtime attendance | Record OUT after shift end plus threshold. | Overtime minutes are correct and work minutes equal OUT minus IN. |
| ATT-005 | Missing IN | Record only OUT where allowed for test data. | Attendance is Missing Punch and missing values are not treated as Present. |
| ATT-006 | Missing OUT | Record only IN. | Attendance is Missing Punch and dashboard missing-punch count includes it. |
| ATT-007 | Overnight attendance | Use Night-shift IN on one date and OUT on the next date. | One attendance row uses the shift start date and correct cross-day work minutes. |
| ATT-008 | Rejected leave without punch | Reject a leave request for a date with no punches. | Attendance is created/updated as Absent with zero work minutes. |
| ATT-009 | Rejected leave with valid punch | Reject leave for a date with valid punch-derived attendance. | Existing Present/Late/Early Out/Manual Adjustment status is not overwritten. |
| ATT-010 | Manual correction | Authorized admin changes attendance status or minutes and submits. | Correction is saved and an audit/correction record is created. |
| ATT-011 | Invalid correction | Submit an invalid status, negative minutes, or invalid date. | Request is rejected and original attendance remains unchanged. |
| ATT-012 | Attendance date filter | Select a valid date in the attendance page. | Only records for that date are listed. |
| ATT-013 | Attendance pagination | Create more than one page of records and navigate pages. | Correct records are shown and date/filter parameters are preserved. |

## 6. Leave application and approval

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| LEAVE-001 | Employee full-day application | Apply one full day using valid dates and available balance. | Request is created as Pending with the correct number of days. |
| LEAVE-002 | Employee half-day application | Select one date, half day, and Forenoon/Afternoon. | Request stores `0.5` day and session in the reason/details. |
| LEAVE-003 | Invalid half-day range | Select half day with different From and To dates. | Request is rejected. |
| LEAVE-004 | Invalid date range | Set To before From or use an invalid date. | Request is rejected with a clear validation message. |
| LEAVE-005 | Insufficient balance | Request more days than the available closing balance. | Request is rejected and balance is unchanged. |
| LEAVE-006 | Duplicate same-date leave | Submit another pending/approved leave covering an existing date. | Request is rejected as a duplicate. |
| LEAVE-007 | Duplicate overlapping range | Existing leave is `15-17`; apply `16-18`. | Request is rejected because the ranges overlap. |
| LEAVE-008 | Cancel pending leave | Employee cancels a pending request. | Status becomes Cancelled and the request no longer reserves balance. |
| LEAVE-009 | Cancel reviewed leave | Attempt to cancel approved or rejected leave. | Operation is rejected. |
| LEAVE-010 | Admin approve | Admin approves a pending request. | Status becomes Approved, balance is debited once, and a debit transaction is recorded. |
| LEAVE-011 | Admin reject | Admin rejects a pending request with no punch. | Status becomes Rejected and attendance is marked Absent for uncovered dates. |
| LEAVE-012 | Review twice | Approve/reject the same request twice. | Second review is rejected and no duplicate debit/attendance transaction occurs. |
| LEAVE-013 | Admin duplicate create | Admin creates leave overlapping pending or approved leave. | Duplicate is rejected for the selected employee. |
| LEAVE-014 | Admin edit overlap | Edit a leave to overlap another request. | Edit is rejected; editing the same record without overlap remains allowed. |
| LEAVE-015 | Leave history pagination/filter | Use month/date filters and navigate application/transaction pages. | Results, totals, and pagination match the selected filters. |

## 7. Leave balances, priority, and carry-forward

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| BAL-001 | Balance display | Open employee and admin balance views for a configured month. | Opening, earned, utilized, closing, and carry-forward values match the database. |
| BAL-002 | Priority order | Process an absence with balances in all types. | Leave is applied strictly in `WO -> EL -> FL -> CPL -> CL` order. |
| BAL-003 | Priority skips empty type | Set WO/EL balance to zero and process leave. | Processing skips empty types and uses the next available type. |
| BAL-004 | No negative balance | Process leave exceeding all available balances. | Negative closing balances are not created unless policy explicitly enables them. |
| BAL-005 | Partial-day balance | Process a `0.5` day leave. | Exactly `0.5` is debited and transaction history records the decimal amount. |
| BAL-006 | CL carry-forward one month | Close a month with unused CL and process the next month. | Eligible unused CL is carried only to the immediately following month. |
| BAL-007 | CL carry-forward does not chain | Leave carried CL unused in the next month and process another month. | The old carried amount is not carried beyond the next month. |
| BAL-008 | Transaction history | Review credit, debit, utilization, and carry-forward records. | Each balance change has a clear transaction type, amount, month, and reference. |

## 8. Monthly processing

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| PROCESS-001 | Process valid month | Select a month and run processing. | Punches, attendance, leave, balances, and summary totals are processed successfully. |
| PROCESS-002 | Review processing summary | Open the completed processing record. | Employee count, attendance count, processor, month, status, and completion time are accurate. |
| PROCESS-003 | Duplicate month processing | Run processing twice for the same month. | Second run is prevented or safely treated as already processed; no duplicate transactions occur. |
| PROCESS-004 | Processing rollback | Cause a validation/database failure during processing. | Related changes roll back and the processing record is not falsely marked completed. |
| PROCESS-005 | Missing/irregular punches | Process employees with missing IN, missing OUT, and overnight punches. | Attendance statuses and minutes follow the configured shift rules. |
| PROCESS-006 | Leave priority during processing | Process an absent employee with multiple leave balances. | Priority order and balance deductions are correct. |
| PROCESS-007 | Processing pagination | Create multiple processing history records and navigate pages. | Pagination shows the correct processing records. |

## 9. Dashboard and employee portal

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| DASH-001 | Dashboard KPI counts | Compare Employees, Present, Absent, On Leave, Pending, Punch In, Punch Out, and Missing Punch cards with SQL totals. | All cards show current and matching counts. |
| DASH-002 | Absent card navigation | Click Absent Today. | Today’s attendance page opens. |
| DASH-003 | Punch card navigation | Click Punch In, Punch Out, and Missing Punches cards. | Correct punch/attendance page and filters open. |
| DASH-004 | Overnight missing punch | Leave a previous-day Night-shift IN without current-day OUT. | Missing-punch count includes the employee. |
| DASH-005 | Dashboard recent attendance | Review recent attendance and change pages. | Records are sorted newest first and pagination works. |
| DASH-006 | Employee dashboard | Log in as an employee and open the dashboard. | Only that employee’s pending leave, balance, and attendance summary is displayed. |
| DASH-007 | Employee attendance | Open attendance history and change month. | Only the logged-in employee’s records for the selected month are shown. |
| DASH-008 | Employee leave history | Apply/cancel leave and reload the leave page. | History and balance cards show the latest status and values. |

## 10. UI, validation, and security regression

| ID | Test case | Steps | Expected result |
|---|---|---|---|
| UI-001 | Date calendar UI | Click every full-date input in admin and employee forms. | Calendar picker opens and visible value remains `DD/MM/YYYY`. |
| UI-002 | Date display consistency | Review dashboard, attendance, leave, and history dates. | User-facing dates are consistently formatted as `DD/MM/YYYY`. |
| UI-003 | Responsive layout | Test login, dashboard, tables, and forms on desktop and narrow mobile width. | Content remains usable without broken navigation or overlapping controls. |
| UI-004 | Flash/AJAX errors | Submit invalid forms through normal and AJAX requests. | Error is visible once, with no success message or silent failure. |
| UI-005 | HTML escaping | Enter special characters in names, reasons, and remarks. | Values display as text; HTML/script is not executed. |
| SEC-001 | SQL injection input | Use SQL metacharacters in login, search, names, and reasons. | Input is treated as data; no SQL error or unauthorized access occurs. |
| SEC-002 | Session isolation | Use two browser sessions for different employees. | Each session can view only its own employee data. |
| SEC-003 | Direct POST authorization | Send employee/admin POST requests without the correct session. | Request is rejected and no data changes. |
| SEC-004 | Duplicate submission | Double-click create/approve/submit buttons or replay the POST. | No duplicate leave, punch, approval debit, or processing record is created. |

## Suggested execution summary

Run the cases in this order:

1. `AUTH-*`
2. `EMP-*` and `SHIFT-*`
3. `PUNCH-*` and `ATT-*`
4. `LEAVE-*` and `BAL-*`
5. `PROCESS-*`
6. `DASH-*`, `UI-*`, and `SEC-*`

At release time, attach screenshots or database query results for failed cases,
especially duplicate prevention, overnight punches, attendance minutes, leave
priority, CL carry-forward, and rejected-leave absence handling.
