<?php
declare(strict_types=1);

/**
 * Minimal iCalendar (RFC 5545) reader for importing external feeds / files.
 * Dependency-free. Handles the common subset: VEVENT with SUMMARY, DESCRIPTION,
 * LOCATION, DTSTART/DTEND (timed with TZID or UTC "Z", and all-day VALUE=DATE),
 * DURATION, RRULE, UID. All times are normalised to UTC SQL strings.
 *
 * Deliberately NOT handled yet: EXDATE, per-occurrence overrides (RECURRENCE-ID),
 * VALARM, attendees. RRULE is stored verbatim (the client renderer speaks RRULE).
 */

/** Unfold RFC 5545 folded lines (a line beginning with space/tab continues the previous). */
function ical_unfold(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    $out = [];
    foreach ($lines as $line) {
        if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
            if ($out) { $out[count($out) - 1] .= substr($line, 1); }
        } else {
            $out[] = $line;
        }
    }
    return $out;
}

/** Parse one content line into [NAME, params(upper-keyed), value], or null. */
function ical_parse_line(string $line): ?array {
    $colon = strpos($line, ':');
    if ($colon === false) { return null; }
    $head  = substr($line, 0, $colon);
    $value = substr($line, $colon + 1);
    $parts = explode(';', $head);
    $name  = strtoupper((string) array_shift($parts));
    $params = [];
    foreach ($parts as $p) {
        $eq = strpos($p, '=');
        if ($eq === false) { continue; }
        $params[strtoupper(substr($p, 0, $eq))] = substr($p, $eq + 1);
    }
    return [$name, $params, $value];
}

/** Unescape an iCalendar TEXT value. */
function ical_unescape(string $v): string {
    return str_replace(['\\N', '\\n', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $v);
}

/** Resolve a TZID to a DateTimeZone, mapping common Windows zone names to IANA. */
function ical_timezone(string $tzid): ?DateTimeZone {
    $tzid = trim($tzid, '"');
    static $win = [
        'Eastern Standard Time'          => 'America/New_York',
        'Central Standard Time'          => 'America/Chicago',
        'Mountain Standard Time'         => 'America/Denver',
        'Pacific Standard Time'          => 'America/Los_Angeles',
        'Alaskan Standard Time'          => 'America/Anchorage',
        'Hawaiian Standard Time'         => 'Pacific/Honolulu',
        'GMT Standard Time'              => 'Europe/London',
        'W. Europe Standard Time'        => 'Europe/Berlin',
        'Central Europe Standard Time'   => 'Europe/Budapest',
        'Central European Standard Time' => 'Europe/Warsaw',
        'Romance Standard Time'          => 'Europe/Paris',
        'E. Europe Standard Time'        => 'Europe/Bucharest',
        'GTB Standard Time'              => 'Europe/Bucharest',
        'Russian Standard Time'          => 'Europe/Moscow',
        'Tokyo Standard Time'            => 'Asia/Tokyo',
        'China Standard Time'            => 'Asia/Shanghai',
        'India Standard Time'            => 'Asia/Kolkata',
        'AUS Eastern Standard Time'      => 'Australia/Sydney',
        'UTC'                            => 'UTC',
    ];
    if (isset($win[$tzid])) { $tzid = $win[$tzid]; }
    try { return new DateTimeZone($tzid); } catch (Exception $e) { return null; }
}

/**
 * Convert a DTSTART/DTEND value+params to ['utc' => 'Y-m-d H:i:s', 'all_day' => bool], or null.
 */
function ical_datetime(string $value, array $params): ?array {
    $value = trim($value);
    $isDate = (isset($params['VALUE']) && strtoupper($params['VALUE']) === 'DATE')
              || (bool) preg_match('/^\d{8}$/', $value);
    if ($isDate) {
        $d = DateTime::createFromFormat('!Ymd', substr($value, 0, 8), new DateTimeZone('UTC'));
        return $d ? ['utc' => $d->format('Y-m-d 00:00:00'), 'all_day' => true] : null;
    }
    $utcZ  = (substr($value, -1) === 'Z');
    $clean = rtrim($value, 'Z');
    $tz    = new DateTimeZone('UTC');
    if (!$utcZ && isset($params['TZID'])) {
        $z = ical_timezone($params['TZID']);
        if ($z) { $tz = $z; }
    }
    $d = DateTime::createFromFormat('!Ymd\THis', $clean, $tz)
         ?: DateTime::createFromFormat('!Ymd\THi', $clean, $tz);
    if (!$d) { return null; }
    $d->setTimezone(new DateTimeZone('UTC'));
    return ['utc' => $d->format('Y-m-d H:i:s'), 'all_day' => false];
}

/** Add an iCalendar DURATION (e.g. PT2H, P1D) to a UTC SQL start; returns UTC SQL or null. */
function ical_apply_duration(string $startUtc, string $dur): ?string {
    $dur = trim($dur);
    if ($dur === '' || $dur[0] !== 'P') {
        // negative durations (rare for DTEND) — bail
        if (strncmp($dur, '-P', 2) !== 0) { return null; }
    }
    try {
        $neg = ($dur[0] === '-');
        $d = new DateTime($startUtc, new DateTimeZone('UTC'));
        $iv = new DateInterval(ltrim($dur, '+-'));
        if ($neg) { $iv->invert = 1; }
        $d->add($iv);
        return $d->format('Y-m-d H:i:s');
    } catch (Exception $e) { return null; }
}

/** Validate/normalise an RRULE body (no "RRULE:" prefix). Returns body or null. */
function ical_clean_rrule(string $rrule): ?string {
    $r = preg_replace('/^RRULE:/i', '', trim($rrule));
    $r = strtoupper((string) $r);
    if ($r === '' || strpos($r, 'FREQ=') === false) { return null; }
    if (!preg_match('#^[A-Z0-9;=,:/+\-]+$#', $r)) { return null; }
    return $r;
}

/**
 * Parse iCalendar text into normalised events.
 * @return array<int,array{uid:?string,title:string,description:?string,location:?string,
 *                          starts_at:?string,ends_at:?string,all_day:int,rrule:?string}>
 */
function ical_parse(string $text): array {
    $lines  = ical_unfold($text);
    $events = [];
    $cur    = null;
    $depth  = 0; // ignore nested components (e.g. VALARM inside VEVENT)
    foreach ($lines as $line) {
        if ($line === 'BEGIN:VEVENT') { $cur = []; $depth = 0; continue; }
        if ($cur !== null && strncmp($line, 'BEGIN:', 6) === 0) { $depth++; continue; }
        if ($cur !== null && strncmp($line, 'END:', 4) === 0 && $line !== 'END:VEVENT') {
            if ($depth > 0) { $depth--; }
            continue;
        }
        if ($line === 'END:VEVENT') {
            if ($cur !== null) { $e = ical_finalize_event($cur); if ($e) { $events[] = $e; } }
            $cur = null; continue;
        }
        if ($cur === null || $depth > 0) { continue; }
        $parsed = ical_parse_line($line);
        if ($parsed) { $cur[] = $parsed; }
    }
    return $events;
}

/** Reduce a VEVENT's properties to a normalised event row, or null if unusable. */
function ical_finalize_event(array $props): ?array {
    $out = ['uid'=>null,'title'=>'(untitled)','description'=>null,'location'=>null,
            'starts_at'=>null,'ends_at'=>null,'all_day'=>0,'rrule'=>null];
    $startInfo = null; $endInfo = null; $duration = null;
    foreach ($props as [$name, $params, $value]) {
        switch ($name) {
            case 'UID':         $out['uid']         = trim($value); break;
            case 'SUMMARY':     $out['title']       = ical_unescape($value); break;
            case 'DESCRIPTION': $out['description'] = ical_unescape($value); break;
            case 'LOCATION':    $out['location']    = ical_unescape($value); break;
            case 'DTSTART':     $startInfo = ical_datetime($value, $params); break;
            case 'DTEND':       $endInfo   = ical_datetime($value, $params); break;
            case 'DURATION':    $duration  = trim($value); break;
            case 'RRULE':       $out['rrule'] = ical_clean_rrule($value); break;
        }
    }
    if (!$startInfo) { return null; }
    $out['starts_at'] = $startInfo['utc'];
    $out['all_day']   = $startInfo['all_day'] ? 1 : 0;
    if ($endInfo) {
        $out['ends_at'] = $endInfo['utc'];
    } elseif ($duration) {
        $out['ends_at'] = ical_apply_duration($startInfo['utc'], $duration);
    }
    if (!$out['ends_at'] || strtotime($out['ends_at']) <= strtotime($out['starts_at'])) {
        $d = new DateTime($startInfo['utc'], new DateTimeZone('UTC'));
        $d->modify($startInfo['all_day'] ? '+1 day' : '+1 hour');
        $out['ends_at'] = $d->format('Y-m-d H:i:s');
    }
    $title = trim((string) $out['title']);
    $out['title'] = mb_substr($title !== '' ? $title : '(untitled)', 0, 500);
    return $out;
}

/**
 * Store parsed events into a calendar. When $replace is true (feed re-sync), the
 * calendar's existing events are cleared first. Returns the number inserted.
 * Caps at 5000 events to bound a hostile feed. Uses our own UIDs (avoids collisions).
 */
function ical_store_events(PDO $pdo, int $calId, array $events, bool $replace): int {
    if ($replace) {
        $pdo->prepare('DELETE FROM events WHERE calendar_id = ?')->execute([$calId]);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO events (calendar_id, uid, title, description, location, starts_at, ends_at, all_day, rrule, timezone)
         VALUES (:cal,:uid,:title,:desc,:loc,:starts,:ends,:allday,:rrule,"UTC")'
    );
    $n = 0;
    foreach ($events as $e) {
        if ($n >= 5000) { break; }
        if (empty($e['starts_at']) || empty($e['ends_at'])) { continue; }
        $stmt->execute([
            ':cal'    => $calId,
            ':uid'    => make_event_uid(),
            ':title'  => mb_substr((string) ($e['title'] !== '' ? $e['title'] : '(untitled)'), 0, 500),
            ':desc'   => ($e['description'] ?? null) ?: null,
            ':loc'    => ($e['location'] ?? null) ?: null,
            ':starts' => $e['starts_at'],
            ':ends'   => $e['ends_at'],
            ':allday' => !empty($e['all_day']) ? 1 : 0,
            ':rrule'  => ($e['rrule'] ?? null) ?: null,
        ]);
        $n++;
    }
    return $n;
}

/* ============================ EXPORT (generate .ics) ============================ */

/** Escape a TEXT value per RFC 5545 (backslash, newline, comma, semicolon). */
function ical_escape_text(string $v): string {
    $v = str_replace('\\', '\\\\', $v);
    $v = str_replace(["\r\n", "\n", "\r"], '\\n', $v);
    $v = str_replace([',', ';'], ['\\,', '\\;'], $v);
    return $v;
}

/** Fold a content line to <=75 octets, continuation lines prefixed with a space (UTF-8 safe). */
function ical_fold(string $line): string {
    if (strlen($line) <= 75) { return $line; }
    $out = ''; $i = 0; $len = strlen($line); $first = true;
    while ($i < $len) {
        $take = min($first ? 75 : 74, $len - $i);
        // don't split a UTF-8 multibyte sequence: back off while the next byte is a continuation byte
        while ($take > 0 && ($i + $take) < $len && (ord($line[$i + $take]) & 0xC0) === 0x80) { $take--; }
        if ($take <= 0) { $take = min($first ? 75 : 74, $len - $i); } // safety
        $out .= ($first ? '' : "\r\n ") . substr($line, $i, $take);
        $i += $take; $first = false;
    }
    return $out;
}

/**
 * Build an iCalendar (VCALENDAR) document from event rows for one calendar.
 * $cal needs ['name']; each $events row: id, uid, title, description, location,
 * starts_at, ends_at (UTC SQL), all_day, rrule.
 */
function ical_export(array $cal, array $events): string {
    $tz    = new DateTimeZone('UTC');
    $stamp = gmdate('Ymd\THis\Z');
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Nexus Calendar//cal.stamih.com//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
    ];
    if (!empty($cal['name'])) { $lines[] = 'X-WR-CALNAME:' . ical_escape_text((string) $cal['name']); }
    $lines[] = 'X-WR-TIMEZONE:UTC';

    foreach ($events as $e) {
        $allDay = (int) ($e['all_day'] ?? 0) === 1;
        try {
            $s  = new DateTime((string) $e['starts_at'], $tz);
            $en = new DateTime((string) $e['ends_at'], $tz);
        } catch (Exception $ex) { continue; }
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . (!empty($e['uid']) ? $e['uid'] : ('nc-' . ($e['id'] ?? bin2hex(random_bytes(4))) . '@cal.stamih.com'));
        $lines[] = 'DTSTAMP:' . $stamp;
        if ($allDay) {
            $lines[] = 'DTSTART;VALUE=DATE:' . $s->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $en->format('Ymd');   // storage end is already exclusive
        } else {
            $lines[] = 'DTSTART:' . $s->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $en->format('Ymd\THis\Z');
        }
        if (!empty($e['rrule']))       { $lines[] = 'RRULE:' . $e['rrule']; }
        $lines[] = 'SUMMARY:' . ical_escape_text((string) ($e['title'] ?? ''));
        if (!empty($e['location']))    { $lines[] = 'LOCATION:' . ical_escape_text((string) $e['location']); }
        if (!empty($e['description'])) { $lines[] = 'DESCRIPTION:' . ical_escape_text((string) $e['description']); }
        $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", array_map('ical_fold', $lines)) . "\r\n";
}

/** A random, unguessable feed token (40 hex chars). */
function ical_gen_feed_token(): string {
    return bin2hex(random_bytes(20));
}

/** True if a host resolves to a private/loopback/reserved address (SSRF guard). */
function ical_host_is_private(string $host): bool {
    $host = trim($host, '[]');
    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $recs = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($recs as $r) {
            if (!empty($r['ip']))   { $ips[] = $r['ip']; }
            if (!empty($r['ipv6'])) { $ips[] = $r['ipv6']; }
        }
        $g = @gethostbyname($host);
        if ($g && $g !== $host) { $ips[] = $g; }
    }
    if (!$ips) { return false; } // unresolvable; let the fetch fail naturally
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
    }
    return false;
}

/**
 * Fetch an .ics feed over HTTP(S) with SSRF protection, size cap and timeouts.
 * @return array{ok:bool, body?:?string, etag?:?string, notModified?:bool, error?:string, status?:int}
 */
function ical_fetch_url(string $url, ?string $etag = null): array {
    $url = trim($url);
    if (stripos($url, 'webcal://') === 0) { $url = 'https://' . substr($url, 9); }
    $p = parse_url($url);
    if (!$p || empty($p['scheme']) || empty($p['host'])) {
        return ['ok' => false, 'error' => 'Invalid URL'];
    }
    $scheme = strtolower($p['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return ['ok' => false, 'error' => 'Only http(s)/webcal feeds are allowed'];
    }
    if (ical_host_is_private($p['host'])) {
        return ['ok' => false, 'error' => 'Refusing to fetch a private/internal address'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'No HTTP client available on server'];
    }
    $maxBytes = 5 * 1024 * 1024;
    $body = '';
    $respHeaders = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_USERAGENT      => 'NexusCalendar/1.0 (+https://cal.stamih.com)',
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER     => $etag ? ['If-None-Match: ' . $etag] : [],
        CURLOPT_WRITEFUNCTION  => function ($c, $chunk) use (&$body, $maxBytes) {
            $body .= $chunk;
            return strlen($body) > $maxBytes ? 0 : strlen($chunk);
        },
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$respHeaders) {
            $respHeaders[] = $h; return strlen($h);
        },
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($status === 304) { return ['ok' => true, 'notModified' => true, 'body' => null, 'etag' => $etag, 'status' => 304]; }
    if ($err !== '')               { return ['ok' => false, 'error' => 'Fetch failed: ' . $err]; }
    if ($status < 200 || $status >= 300) { return ['ok' => false, 'error' => 'Feed returned HTTP ' . $status, 'status' => $status]; }

    $newEtag = null;
    foreach ($respHeaders as $h) {
        if (stripos($h, 'etag:') === 0) { $newEtag = trim(substr($h, 5)); }
    }
    return ['ok' => true, 'body' => $body, 'etag' => $newEtag, 'status' => $status];
}
