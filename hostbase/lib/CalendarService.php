<?php
declare(strict_types=1);

final class HbCalendarService
{
    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   month_label: string,
     *   weekdays: list<string>,
     *   weeks: list<list<array{date: string, day: int, in_month: bool, is_today: bool, bookings: list<array<string, mixed>>}>>
     * }
     */
    public static function buildMonth(PDO $db, int $propertyId, int $subscriberId, int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        $year = max(1970, min(2100, $year));

        $firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDay->format('t');
        $startWeekday = (int) $firstDay->format('N'); // 1=Mon
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        $gridStart = $firstDay->modify('-' . ($startWeekday - 1) . ' days');
        $gridEnd = $gridStart->modify('+41 days');

        $bookings = HbBookingService::forPropertyInRange(
            $db,
            $propertyId,
            $subscriberId,
            $gridStart->format('Y-m-d'),
            $gridEnd->format('Y-m-d')
        );

        $weekdayLabels = explode(',', hb_t('calendar.weekdays'));
        $weeks = [];
        $cursor = $gridStart;

        for ($week = 0; $week < 6; $week++) {
            $days = [];
            for ($d = 0; $d < 7; $d++) {
                $date = $cursor->format('Y-m-d');
                $dayBookings = [];
                foreach ($bookings as $booking) {
                    if (HbBookingService::isNightOccupied($date, (string) $booking['check_in'], (string) $booking['check_out'])) {
                        $dayBookings[] = $booking;
                    }
                }

                $days[] = [
                    'date' => $date,
                    'day' => (int) $cursor->format('j'),
                    'in_month' => (int) $cursor->format('n') === $month,
                    'is_today' => $date === $today,
                    'bookings' => $dayBookings,
                ];
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $days;
        }

        $monthLabel = self::monthLabel($year, $month);

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => $monthLabel,
            'weekdays' => $weekdayLabels,
            'weeks' => $weeks,
        ];
    }

    private static function monthLabel(int $year, int $month): string
    {
        $monthsHu = [
            1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
            5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
            9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
        ];
        $monthsEn = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        if (hb_current_locale() === 'en') {
            return ($monthsEn[$month] ?? (string) $month) . ' ' . $year;
        }

        return $year . '. ' . ($monthsHu[$month] ?? (string) $month);
    }
}
