<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");

require "../../config/events_db.php";
require "../../config/college_db.php";
require_once "../../helpers/notification_service.php";

try {
    $stmt = $eventDB->prepare("
        SELECT event_id, studid, activity_name, tracking_id, application_type, date_to
        FROM events
        WHERE status = 'approved'
          AND COALESCE(completion_reminder_sent, 0) = 0
          AND date_to <= CURDATE()
        ORDER BY date_to ASC, event_id ASC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = [];

    foreach ($events as $event) {
        $recipientIds = [(int)$event['studid']];
        if (($event['application_type'] ?? '') === 'team') {
            $teamStmt = $eventDB->prepare("SELECT studid FROM team_members WHERE event_id = ?");
            $teamStmt->execute([$event['event_id']]);
            $recipientIds = array_map('intval', $teamStmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $subject = "Completion reminder: " . ($event['activity_name'] ?? 'Event') . " (" . ($event['tracking_id'] ?? '-') . ")";
        $details = [
            "Event Name" => $event['activity_name'] ?? '-',
            "Tracking ID" => $event['tracking_id'] ?? '-',
            "Event ID" => $event['event_id'],
            "Reminder" => "Please fill the completion form as soon as possible."
        ];
        $html = app_build_email_html(
            "Completion form reminder",
            [
                "The completion form is now due for this event.",
                "Please open the dashboard and submit the completion details."
            ],
            $details,
            [
                "text" => "Open Dashboard",
                "url" => "http://127.0.0.1:5501/dashboard.html"
            ]
        );
        $text = app_build_email_text(
            "Completion form reminder",
            [
                "The completion form is now due for this event.",
                "Please open the dashboard and submit the completion details."
            ],
            $details
        );

        foreach (array_unique($recipientIds) as $studid) {
            $email = app_get_student_email($collegeDB, (int)$studid);
            if ($email) {
                app_send_email($email, $subject, $html, $text);
            }
        }

        $update = $eventDB->prepare("UPDATE events SET completion_reminder_sent = 1 WHERE event_id = ?");
        $update->execute([$event['event_id']]);
        $sent[] = $event['event_id'];
    }

    echo json_encode([
        "success" => true,
        "sent_count" => count($sent),
        "event_ids" => $sent
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

