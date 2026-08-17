<?php

declare(strict_types=1);

namespace Xestify\database\seeders\support;

use Xestify\exceptions\SeederException;

/**
 * Generates realistic Spanish person identity data (names, DNI with real
 * check letter, unique emails) for STORY 10.6's business data seeder.
 */
final class PersonDataGenerator
{
    private const DNI_LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';
    private const MAX_UNIQUE_ATTEMPTS = 5000;

    /**
     * Mapa caracter-a-caracter (no byte-a-byte): strtr() en su forma de dos
     * cadenas empareja bytes, y los acentos UTF-8 ocupan mas de un byte, lo
     * que corrompe el resultado (p.ej. "García" -> "garcuna"). La forma de
     * array de strtr() si es segura con multibyte.
     */
    private const ACCENT_MAP = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
    ];

    private const FIRST_NAMES_FEMALE = [
        'Lucía', 'María', 'Sofía', 'Martina', 'Paula', 'Julia', 'Valeria', 'Daniela',
        'Carmen', 'Laura', 'Elena', 'Marta', 'Cristina', 'Ana', 'Isabel', 'Alba',
        'Nuria', 'Sara', 'Rocío', 'Beatriz', 'Irene', 'Claudia', 'Andrea', 'Raquel',
        'Silvia', 'Teresa', 'Patricia', 'Mónica', 'Eva', 'Gloria',
    ];

    private const FIRST_NAMES_MALE = [
        'Hugo', 'Martín', 'Pablo', 'Alejandro', 'Daniel', 'Mateo', 'David', 'Adrián',
        'Álvaro', 'Diego', 'Javier', 'Carlos', 'Manuel', 'José', 'Antonio', 'Francisco',
        'Miguel', 'Rafael', 'Ángel', 'Sergio', 'Jorge', 'Iván', 'Rubén', 'Óscar',
        'Fernando', 'Ignacio', 'Enrique', 'Andrés', 'Gonzalo', 'Víctor',
    ];

    private const SURNAMES = [
        'García', 'Rodríguez', 'González', 'Fernández', 'López', 'Martínez', 'Sánchez',
        'Pérez', 'Gómez', 'Martín', 'Jiménez', 'Ruiz', 'Hernández', 'Díaz', 'Moreno',
        'Álvarez', 'Muñoz', 'Romero', 'Alonso', 'Gutiérrez', 'Navarro', 'Torres',
        'Domínguez', 'Vázquez', 'Ramos', 'Gil', 'Ramírez', 'Serrano', 'Blanco', 'Suárez',
        'Molina', 'Morales', 'Ortega', 'Delgado', 'Castro', 'Ortiz', 'Rubio', 'Marín',
        'Sanz', 'Iglesias',
    ];

    private function __construct()
    {
        // Clase estatica, instanciacion no permitida.
    }

    public static function firstName(): string
    {
        return RandomPrimitives::boolWithProbability(50)
            ? RandomPrimitives::pick(self::FIRST_NAMES_FEMALE)
            : RandomPrimitives::pick(self::FIRST_NAMES_MALE);
    }

    public static function singleSurname(): string
    {
        return RandomPrimitives::pick(self::SURNAMES);
    }

    /**
     * Devuelve un par de apellidos ("Apellido1 Apellido2") no usado antes
     * dentro del set $used pasado por referencia (scope por grupo llamador).
     *
     * @param array<string, bool> $used
     */
    public static function uniqueSurnames(array &$used): string
    {
        $attempts = 0;

        do {
            $candidate = self::singleSurname() . ' ' . self::singleSurname();
            $attempts++;
            if ($attempts > self::MAX_UNIQUE_ATTEMPTS) {
                throw new SeederException(
                    'No se pudo generar un par de apellidos unico tras ' . self::MAX_UNIQUE_ATTEMPTS . ' intentos.'
                );
            }
        } while (isset($used[$candidate]));

        $used[$candidate] = true;

        return $candidate;
    }

    public static function dniCheckLetter(int $number): string
    {
        return self::DNI_LETTERS[$number % 23];
    }

    public static function randomDni(): string
    {
        $number = RandomPrimitives::intBetween(0, 99999999);

        return sprintf('%08d%s', $number, self::dniCheckLetter($number));
    }

    /**
     * @param array<string, bool> $used
     */
    public static function uniqueEmail(string $firstName, string $surnames, string $domain, array &$used): string
    {
        $firstSurname = explode(' ', $surnames, 2)[0];
        $localPart = self::slugify($firstName) . '.' . self::slugify($firstSurname);

        return self::uniqueEmailFromLocalPart($localPart, $domain, $used);
    }

    /**
     * @param array<string, bool> $used
     */
    public static function uniqueEmailFromCompany(string $companyName, string $domain, array &$used): string
    {
        return self::uniqueEmailFromLocalPart('info.' . self::slugify($companyName), $domain, $used);
    }

    public static function slugify(string $value): string
    {
        $transliterated = strtr($value, self::ACCENT_MAP);
        $lower = strtolower($transliterated);
        $slug = preg_replace('/[^a-z]/', '', $lower);

        return $slug ?? '';
    }

    /**
     * @param array<string, bool> $used
     */
    private static function uniqueEmailFromLocalPart(string $localPart, string $domain, array &$used): string
    {
        $candidate = $localPart . '@' . $domain;
        $suffix = 1;

        while (isset($used[$candidate])) {
            $suffix++;
            $candidate = $localPart . $suffix . '@' . $domain;
        }

        $used[$candidate] = true;

        return $candidate;
    }
}
