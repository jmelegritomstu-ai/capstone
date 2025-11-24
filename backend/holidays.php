<?php
// Simple holiday helper. Returns an array of holiday dates (YYYY-MM-DD) for a given year.
// This is intentionally minimal and can be extended later to fetch from DB or an external API.
function get_holidays_for_year($year){
    $year = (int)$year;
    // Prefer using an existing global DB connection when available.
    global $conn;
    // If a DB is available and a `holidays` table exists, prefer that as source of truth.
    if (file_exists(__DIR__ . '/employee_db.php')){
        try {
            if (!isset($conn)) {
                require_once __DIR__ . '/employee_db.php';
            }
            if (isset($conn)) {
                $res = $conn->query("SHOW TABLES LIKE 'holidays'");
            } else {
                $res = false;
            }
            if ($res && $res->num_rows > 0) {
                $stmt = $conn->prepare("SELECT holiday_date FROM holidays WHERE YEAR(holiday_date)=? ORDER BY holiday_date");
                if ($stmt) {
                    $stmt->bind_param('i', $year);
                    if ($stmt->execute()){
                        $r = $stmt->get_result();
                        $out = [];
                        while($row = $r->fetch_assoc()) $out[] = $row['holiday_date'];
                        $stmt->close();
                        return $out;
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) {
            // Fall back to static list on any DB errors
        }
    }

    // Static fallback (same as before)
    $holidays = [];
    // Fixed-date holidays (month-day)
    $fixed = [
        '01-01', // New Year's Day
        '05-01', // Labor Day
        '06-12', // Independence Day
        '11-01', // All Saints' Day
        '11-02', // All Souls Day
        '11-30', // Bonifacio Day
        '12-08', // Immaculate Conception
        '12-24', // Christmas Eve
        '12-25', // Christmas Day
        '12-30', // Rizal Day
        '12-31'  // New Year's Eve
    ];
    foreach($fixed as $mday){ $holidays[] = sprintf('%04d-%s', $year, $mday); }

    // Year-specific / movable holidays provided by user (include known 2024/2025 dates)
    if ($year === 2025){
        $movables = [
            '2025-01-29', // Chinese New Year (2025)
            '2025-04-17', // Maundy Thursday (2025)
            '2025-04-18', // Good Friday (2025)
            '2025-04-19', // Black Saturday (2025)
            '2025-04-01', // Eid'l Fitr (2025) as provided
            '2025-06-06', // Eid'l Adha (2025)
            '2025-08-25'  // National Heroes Day (2025 - last Monday of Aug)
        ];
        foreach($movables as $d) $holidays[] = $d;
    }
    if ($year === 2024){
        $movables = [
            '2024-04-09', // Day of Valor 2024 (example provided)
            '2024-08-23'  // Ninoy Aquino Day (2024 adjusted)
        ];
        foreach($movables as $d) $holidays[] = $d;
    }

    // Deduplicate and sort
    $holidays = array_values(array_unique($holidays));
    sort($holidays);
    return $holidays;
}

function is_holiday_date($date){
    // accepts YYYY-MM-DD or DateTime
    if ($date instanceof DateTime) $date = $date->format('Y-m-d');
    return in_array($date, get_holidays_for_year((int)substr($date,0,4)) );
}

// Get holidays between two dates (inclusive). Works with DB-backed table if present, else uses static lists for involved years.
function get_holidays_between($startDate, $endDate){
    $s = date('Y-m-d', strtotime($startDate));
    $e = date('Y-m-d', strtotime($endDate));
    if ($s > $e) return [];
    $sy = (int)substr($s,0,4); $ey = (int)substr($e,0,4);
    $out = [];
    for ($y = $sy; $y <= $ey; $y++){
        foreach(get_holidays_for_year($y) as $d){
            if ($d >= $s && $d <= $e) $out[] = $d;
        }
    }
    sort($out);
    return array_values(array_unique($out));
}
