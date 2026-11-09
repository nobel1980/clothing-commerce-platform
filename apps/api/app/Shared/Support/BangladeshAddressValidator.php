<?php

namespace App\Shared\Support;

use Illuminate\Validation\Rule;

final class BangladeshAddressValidator
{
    public const CURRENCY = 'BDT';

    public const PHONE_REGEX = '/^(?:\+?8801|01)[3-9]\d{8}$/';

    public const POSTAL_CODE_REGEX = '/^\d{4}$/';

    public const DIVISIONS = [
        'Dhaka',
        'Chattogram',
        'Rajshahi',
        'Khulna',
        'Barishal',
        'Sylhet',
        'Rangpur',
        'Mymensingh',
    ];

    /**
     * Validation rules for an inline Bangladesh shipping/billing address array.
     * Supports both canonical backend keys (name, division, district, upazila_thana, street_address, postal_code)
     * and storefront aliases (firstName/lastName, upazila, street, postalCode).
     */
    public static function rules(string $prefix = 'shipping_address'): array
    {
        return [
            "{$prefix}"                       => ['required', 'array'],
            "{$prefix}.name"                  => ['required_without_all:' . "{$prefix}.firstName,{$prefix}.fullName", 'nullable', 'string', 'max:120'],
            "{$prefix}.fullName"              => ['required_without_all:' . "{$prefix}.name,{$prefix}.firstName", 'nullable', 'string', 'max:120'],
            "{$prefix}.firstName"             => ['required_without_all:' . "{$prefix}.name,{$prefix}.fullName", 'nullable', 'string', 'max:60'],
            "{$prefix}.lastName"              => ['nullable', 'string', 'max:60'],
            "{$prefix}.email"                 => ['nullable', 'email', 'max:191'],
            "{$prefix}.phone"                 => ['required', 'string', 'regex:' . self::PHONE_REGEX],
            "{$prefix}.division"              => ['required', 'string', Rule::in(self::DIVISIONS)],
            "{$prefix}.district"              => ['required', 'string', 'max:100'],
            "{$prefix}.upazila_thana"         => ['required_without:' . "{$prefix}.upazila", 'nullable', 'string', 'max:100'],
            "{$prefix}.upazila"               => ['required_without:' . "{$prefix}.upazila_thana", 'nullable', 'string', 'max:100'],
            "{$prefix}.street_address"        => ['required_without_all:' . "{$prefix}.street,{$prefix}.addressLine1", 'nullable', 'string', 'max:500'],
            "{$prefix}.street"                => ['required_without_all:' . "{$prefix}.street_address,{$prefix}.addressLine1", 'nullable', 'string', 'max:500'],
            "{$prefix}.addressLine1"          => ['required_without_all:' . "{$prefix}.street_address,{$prefix}.street", 'nullable', 'string', 'max:500'],
            "{$prefix}.postal_code"           => ['nullable', 'string', 'regex:' . self::POSTAL_CODE_REGEX],
            "{$prefix}.postalCode"            => ['nullable', 'string', 'regex:' . self::POSTAL_CODE_REGEX],
            "{$prefix}.delivery_instructions" => ['nullable', 'string', 'max:500'],
        ];
    }

    public static function messages(string $prefix = 'shipping_address'): array
    {
        return [
            "{$prefix}.phone.regex"          => 'Please provide a valid Bangladesh mobile number (e.g. 017XXXXXXXX or +88017XXXXXXXX).',
            "{$prefix}.division.in"          => 'Division must be a valid Bangladesh division: ' . implode(', ', self::DIVISIONS) . '.',
            "{$prefix}.postal_code.regex"    => 'Bangladesh postal code must be a 4-digit number (e.g. 1212).',
            "{$prefix}.postalCode.regex"     => 'Bangladesh postal code must be a 4-digit number (e.g. 1212).',
        ];
    }

    /**
     * Normalize an inline address array or an Identity Address model into a canonical Bangladesh address snapshot.
     */
    public static function normalizeSnapshot(array $raw): array
    {
        $name = trim((string) ($raw['name'] ?? $raw['fullName'] ?? trim(($raw['firstName'] ?? '') . ' ' . ($raw['lastName'] ?? ''))));
        $phone = self::normalizePhone((string) ($raw['phone'] ?? ''));
        $division = trim((string) ($raw['division'] ?? 'Dhaka'));
        $district = trim((string) ($raw['district'] ?? $raw['city'] ?? 'Dhaka'));
        $upazila = trim((string) ($raw['upazila_thana'] ?? $raw['upazila'] ?? ''));
        $street = trim((string) ($raw['street_address'] ?? $raw['street'] ?? $raw['addressLine1'] ?? ''));
        $postalCode = isset($raw['postal_code']) || isset($raw['postalCode'])
            ? trim((string) ($raw['postal_code'] ?? $raw['postalCode']))
            : null;

        return [
            'name'                  => $name,
            'email'                 => isset($raw['email']) ? trim((string) $raw['email']) : null,
            'phone'                 => $phone,
            'division'              => $division,
            'district'              => $district,
            'upazila_thana'         => $upazila,
            'street_address'        => $street,
            'postal_code'           => $postalCode !== '' ? $postalCode : null,
            'country'               => 'BD',
            'country_name'          => 'Bangladesh',
            'delivery_instructions' => isset($raw['delivery_instructions'])
                ? trim((string) $raw['delivery_instructions'])
                : null,
        ];
    }

    public static function normalizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[\s\-]/', '', trim($phone)) ?? '';
        if (str_starts_with($cleaned, '+8801')) {
            return '0' . substr($cleaned, 4);
        }
        if (str_starts_with($cleaned, '8801')) {
            return '0' . substr($cleaned, 3);
        }

        return $cleaned;
    }

    public static function isValidPhone(string $phone): bool
    {
        $cleaned = preg_replace('/[\s\-]/', '', trim($phone)) ?? '';

        return (bool) preg_match(self::PHONE_REGEX, $cleaned);
    }
}
