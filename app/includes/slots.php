<?php
// Time slots offered each day, and how many bookings one slot can take.
const SLOT_TIMES    = ['09:00', '11:00', '13:00', '15:00', '17:00'];
const SLOT_CAPACITY = 3;

function slot_label(string $time): string
{
    return date('g:i A', strtotime($time));
}

// A booking date must be a real date, from tomorrow up to 60 days ahead.
function valid_booking_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        return false;
    }
    $tomorrow = new DateTime('tomorrow');
    $limit    = new DateTime('today +60 days');
    $d->setTime(0, 0);
    return $d >= $tomorrow && $d <= $limit;
}

// Returns every slot for a date with how many places are still free.
function get_slots(mysqli $conn, string $date): array
{
    $stmt = $conn->prepare(
        "SELECT TIME_FORMAT(slot_start, '%H:%i') AS t, COUNT(*) AS taken
         FROM bookings
         WHERE booking_date = ? AND status <> 'cancelled'
         GROUP BY t"
    );
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $taken = [];
    foreach ($stmt->get_result() as $row) {
        $taken[$row['t']] = (int) $row['taken'];
    }
    $stmt->close();

    $slots = [];
    foreach (SLOT_TIMES as $time) {
        $slots[] = [
            'time'  => $time,
            'label' => slot_label($time),
            'free'  => max(0, SLOT_CAPACITY - ($taken[$time] ?? 0)),
        ];
    }
    return $slots;
}
