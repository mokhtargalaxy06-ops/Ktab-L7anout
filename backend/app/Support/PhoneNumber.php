<?php

namespace App\Support;

class PhoneNumber
{
    public const RULE = 'regex:/^\+[1-9][0-9]{6,14}$/D';

    public static function whatsappUrl(?string $phone): ?string
    {
        $number = self::normalize($phone);

        return is_string($number) && preg_match(substr(self::RULE, 6), $number) === 1
            ? 'https://wa.me/'.substr($number, 1)
            : null;
    }

    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value) || strlen($value) > 160) {
            return $value;
        }

        $value = strtr($value, array_combine(preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $number = preg_replace('/[\s().-]+/u', '', $value);

        return match (true) {
            $number === null, $number === '' => null,
            str_starts_with($number, '00') => '+'.substr($number, 2),
            preg_match('/^0[5-8][0-9]{8}$/D', $number) === 1 => '+212'.substr($number, 1),
            default => $number,
        };
    }
}
