<?php

function app_table_has_column(PDO $db, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    $cache[$key] = ((int)$stmt->fetchColumn() > 0);
    return $cache[$key];
}

function app_mail_enabled(): bool {
    return getenv('APP_EMAIL_ENABLED') !== '0';
}

function app_mail_from(): string {
    return getenv('APP_EMAIL_FROM') ?: 'no-reply@localhost';
}

function app_mail_from_name(): string {
    return getenv('APP_EMAIL_FROM_NAME') ?: 'Event Pass';
}

function app_slug_local_part(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '.', $value);
    $value = trim((string)$value, '.');
    return $value !== '' ? $value : 'user';
}

function app_get_student_email(PDO $collegeDB, int $studid): ?string {
    $stmt = $collegeDB->prepare("SELECT * FROM students WHERE studid = ? LIMIT 1");
    $stmt->execute([$studid]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return null;
    }

    if (isset($student['email']) && filter_var($student['email'], FILTER_VALIDATE_EMAIL)) {
        return $student['email'];
    }

    $domain = getenv('STUDENT_EMAIL_DOMAIN') ?: '';
    if ($domain && !empty($student['usn'])) {
        return strtolower($student['usn'] . '@' . $domain);
    }

    return null;
}

function app_get_faculty_email(PDO $collegeDB, int $facultyCode): ?string {
    $stmt = $collegeDB->prepare("SELECT * FROM faculty WHERE faculty_code = ? LIMIT 1");
    $stmt->execute([$facultyCode]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$faculty) {
        return null;
    }

    if (isset($faculty['email']) && filter_var($faculty['email'], FILTER_VALIDATE_EMAIL)) {
        return $faculty['email'];
    }

    $domain = getenv('FACULTY_EMAIL_DOMAIN') ?: '';
    if ($domain && !empty($faculty['name'])) {
        return app_slug_local_part($faculty['name']) . '@' . $domain;
    }

    return null;
}

function app_build_email_html(string $headline, array $paragraphs, array $details = [], ?array $cta = null): string {
    $detailRows = '';
    foreach ($details as $label => $value) {
        $detailRows .= '
            <tr>
                <td style="padding:8px 0;color:#475569;font-weight:600;width:160px;vertical-align:top;">' . htmlspecialchars((string)$label) . '</td>
                <td style="padding:8px 0;color:#0f172a;">' . nl2br(htmlspecialchars((string)$value)) . '</td>
            </tr>';
    }

    $ctaHtml = '';
    if ($cta && !empty($cta['text']) && !empty($cta['url'])) {
        $ctaHtml = '
            <div style="margin-top:24px;">
                <a href="' . htmlspecialchars($cta['url']) . '" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:600;">' . htmlspecialchars($cta['text']) . '</a>
            </div>';
    }

    $paragraphHtml = '';
    foreach ($paragraphs as $paragraph) {
        $paragraphHtml .= '<p style="margin:0 0 12px 0;color:#334155;line-height:1.6;">' . nl2br(htmlspecialchars((string)$paragraph)) . '</p>';
    }

    return '
    <div style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;">
      <div style="max-width:640px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #dbeafe;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">
          <div style="background:linear-gradient(135deg,#1d4ed8,#0f766e);color:#fff;padding:24px;">
            <h2 style="margin:0;font-size:22px;line-height:1.3;">' . htmlspecialchars($headline) . '</h2>
          </div>
          <div style="padding:24px;">
            ' . $paragraphHtml . '
            <table style="width:100%;border-collapse:collapse;margin-top:18px;">' . $detailRows . '</table>
            ' . $ctaHtml . '
            <p style="margin-top:24px;color:#64748b;font-size:12px;">This is an automated message from Event Pass.</p>
          </div>
        </div>
      </div>
    </div>';
}

function app_build_email_text(string $headline, array $paragraphs, array $details = []): string {
    $lines = [$headline, ''];
    foreach ($paragraphs as $paragraph) {
        $lines[] = (string)$paragraph;
        $lines[] = '';
    }
    foreach ($details as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }
    $lines[] = '';
    $lines[] = 'This is an automated message from Event Pass.';
    return implode(PHP_EOL, $lines);
}

function app_send_email(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool {
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if (!app_mail_enabled()) {
        error_log("[Event-Pass] Email disabled. Skipped send to {$to} with subject {$subject}");
        return false;
    }

    $from = app_mail_from();
    $fromName = app_mail_from_name();
    $boundary = md5((string)microtime(true));
    $textBody = $textBody ?: trim(strip_tags($htmlBody));

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $fromName . ' <' . $from . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
    ];

    $body = '';
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $textBody . "\r\n";
    $body .= '--' . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n";
    $body .= '--' . $boundary . "--";

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function app_insert_notification(PDO $eventDB, ?int $facultyCode, int $eventId, string $title, string $message, string $type): void {
    if (!$facultyCode) {
        return;
    }

    $check = $eventDB->prepare("
        SELECT 1 FROM notifications
        WHERE faculty_code = ? AND event_id = ? AND type = ? AND title = ?
        LIMIT 1
    ");
    $check->execute([$facultyCode, $eventId, $type, $title]);
    if ($check->fetchColumn()) {
        return;
    }

    $stmt = $eventDB->prepare("
        INSERT INTO notifications (faculty_code, event_id, title, message, type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$facultyCode, $eventId, $title, $message, $type]);
}

function app_ensure_completion_attachment_columns(PDO $eventDB): void {
    $columns = [
        'certificate_files' => "TEXT NULL",
        'photo_files' => "TEXT NULL",
    ];

    foreach ($columns as $column => $definition) {
        if (!app_table_has_column($eventDB, 'event_completions', $column)) {
            $eventDB->exec("ALTER TABLE event_completions ADD COLUMN {$column} {$definition}");
        }
    }
}

function app_collect_file_uploads(array $fileSpec, string $targetDir, array $allowedExtensions, int $maxFiles): array {
    if (empty($fileSpec['name'])) {
        return [];
    }

    if (!is_array($fileSpec['name'])) {
        $fileSpec = [
            'name' => [$fileSpec['name']],
            'type' => isset($fileSpec['type']) ? [$fileSpec['type']] : [],
            'tmp_name' => isset($fileSpec['tmp_name']) ? [$fileSpec['tmp_name']] : [],
            'error' => isset($fileSpec['error']) ? [$fileSpec['error']] : [],
            'size' => isset($fileSpec['size']) ? [$fileSpec['size']] : [],
        ];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $saved = [];
    $total = count($fileSpec['name']);

    for ($i = 0; $i < $total; $i++) {
        if (!isset($fileSpec['error'][$i]) || (int)$fileSpec['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        if (count($saved) >= $maxFiles) {
            break;
        }

        $originalName = $fileSpec['name'][$i] ?? 'upload';
        $tmpName = $fileSpec['tmp_name'][$i] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            continue;
        }

        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $storedName = time() . '_' . $i . '_' . $baseName . '.' . $ext;
        $targetPath = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $saved[] = $storedName;
        }
    }

    return $saved;
}
