# Event Pass Backend

This folder contains the PHP and MySQL backend for the Event Pass system.
It serves student, faculty, and completion workflows through JSON APIs.

## What this backend does

- Handles student event submission
- Stores approvals across the TG, Coordinator, HOD, Dean, and Principal flow
- Supports team events, team consent, attendance, and completion submission
- Powers the faculty dashboard report and approval screens
- Sends notifications and email reminders through helper services

## Database Layout

The backend uses two MySQL databases:

- `college_db`
- `event_db`

Connection settings are loaded from environment variables when available:

- `COLLEGE_DB_URL`
- `EVENT_DB_URL`
- `COLLEGE_DB_SSL_CA`
- `EVENT_DB_SSL_CA`

If no environment variables are set, the backend falls back to local MySQL defaults:

- `mysql://root:@localhost:3306/college_db`
- `mysql://root:@localhost:3306/event_db`

## Folder Structure

```text
Event-Pass-Backend/
  api/
    faculty/          Faculty login, approvals, report, and completion APIs
    cron/             Scheduled jobs such as completion reminders
    *.php             Student and shared API endpoints
  config/             Database connection files
  helpers/            Shared utility functions
  college_db.sql      Schema for the college database
  event_db.sql        Schema for the event database
```

## Main API Areas

### Student APIs

- `api/get_student_details.php`
- `api/search_students.php`
- `api/submit_event.php`
- `api/get_event_details.php`
- `api/get_event_for_completion.php`
- `api/submit_attendance.php`
- `api/submit_completion.php`
- `api/complete_event.php`

### Faculty APIs

- `api/faculty/login.php`
- `api/faculty/logout.php`
- `api/faculty/me.php`
- `api/faculty/get_events.php`
- `api/faculty/approve_event.php`
- `api/faculty/get_attendance_approvals.php`
- `api/faculty/approve_attendance.php`
- `api/faculty/get_completions.php`
- `api/faculty/get_report_events.php`
- `api/faculty/get_notifications.php`

### Supporting APIs

- `api/mock_login.php`
- `api/test_student_login.php`
- `api/respond_team_consent.php`
- `api/attendance.php`
- `api/confirm_attendance.php`
- `api/cron/send_completion_reminders.php`

## Local Setup

1. Install XAMPP or another PHP + MySQL stack.
2. Place this backend inside your web root so it is reachable as:
   - `http://localhost/Event-Pass-Backend`
3. Start Apache and MySQL.
4. Import the SQL files:
   - `college_db.sql`
   - `event_db.sql`
5. Make sure the frontend is allowed by CORS.
   - The current backend headers allow `http://localhost:5501`.

## Optional Environment Variables

Create a `.env` file inside `Event-Pass-Backend/` if you want to override the defaults.

Example:

```env
COLLEGE_DB_URL=mysql://root:@localhost:3306/college_db
EVENT_DB_URL=mysql://root:@localhost:3306/event_db
```

For hosted MySQL providers, you can also set SSL-related values:

```env
COLLEGE_DB_SSL_CA=/path/to/ca.pem
EVENT_DB_SSL_CA=/path/to/ca.pem
```

## How the Data Flow Works

### 1. Student submits an event

- The form sends data to `api/submit_event.php`
- The backend saves the event in `events`
- Team members are saved in `team_members`
- Consent rows are created in `team_member_consents`
- Approval flow rows are created for the relevant role chain

### 2. Faculty reviews the event

- Faculty login uses `api/faculty/login.php`
- Dashboard cards load from `api/faculty/get_events.php`
- Report exports load from `api/faculty/get_report_events.php`
- Completions load from `api/faculty/get_completions.php`

### 3. Attendance and completion

- Students check eligibility through `api/get_event_for_completion.php`
- Attendance and completion are submitted through the related student APIs
- Faculty can review attendance through the attendance approval endpoints

## Key Tables

- `events`
- `team_members`
- `team_member_consents`
- `event_approvals`
- `team_member_approvals`
- `attendance`
- `attendance_approvals`
- `event_completions`

## Development Notes

- All APIs return JSON.
- Most endpoints require a valid session.
- Faculty endpoints expect the faculty session variables set by the login flow.
- Team events store the leader in `team_members` with `is_leader = 1`.
- Report and completion APIs intentionally exclude the leader from the non-leader member list to avoid duplicates in the UI.

## Troubleshooting

- If requests fail with `Faculty not logged in` or `Not logged in`, check the session cookies and login flow.
- If the frontend cannot call the backend, confirm the CORS origin matches the port you are using.
- If database calls fail, verify both databases were imported and the MySQL credentials in the connection URL are correct.

