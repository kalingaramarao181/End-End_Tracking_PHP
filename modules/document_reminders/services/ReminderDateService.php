<?php

class ReminderDateService
{
    public static function normalizeDate($value)
    {
        if (!$value || !is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    public static function subtractMonths($date, $months)
    {
        $source = new DateTimeImmutable($date);
        $day = (int)$source->format('d');
        $target = $source->modify('first day of this month')->modify('-' . (int)$months . ' months');
        $lastDay = (int)$target->format('t');
        return $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min($day, $lastDay));
    }

    public static function addMonth($date)
    {
        $source = new DateTimeImmutable($date);
        $day = (int)$source->format('d');
        $target = $source->modify('first day of next month');
        return $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min($day, (int)$target->format('t')));
    }

    public static function addMonths($date, $months)
    {
        $source = new DateTimeImmutable($date);
        $day = (int)$source->format('d');
        $target = $source->modify('first day of this month')->modify('+' . (int)$months . ' months');
        return $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min($day, (int)$target->format('t')));
    }

    public static function addDays($date, $days)
    {
        return (new DateTimeImmutable($date))->modify('+' . (int)$days . ' days');
    }

    public static function initialReminderDate($expiryDate, $today = null)
    {
        $expiry = new DateTimeImmutable($expiryDate);
        $today = new DateTimeImmutable($today ?: 'today');
        $first = self::subtractMonths($expiryDate, 6);

        if ($today >= $expiry) {
            return null;
        }

        while ($first < $today && $first < $expiry) {
            $first = self::addMonth($first->format('Y-m-d'));
        }

        return $first < $expiry ? $first->format('Y-m-d') : $today->format('Y-m-d');
    }
}
