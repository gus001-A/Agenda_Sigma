<?php

namespace App\Console\Commands\Concerns;

/** Limpieza de los textos, teléfonos y correos que vienen de la base heredada de SIGMA. */
trait LimpiaDatosSigma
{
    /** Corrige textos guardados dos veces en UTF-8 (ej. "NIÃ‘O" → "NIÑO"). */
    private function texto(?string $s): string
    {
        $s = trim((string) $s);
        if ($s !== '' && preg_match('/[ÃÂ]/u', $s)) {
            $reparado = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if (mb_check_encoding($reparado, 'UTF-8')) {
                $s = $reparado;
            }
        }

        return preg_replace('/\s+/u', ' ', $s);
    }

    /** "MARIO ONOFRE" → "Mario Onofre". */
    private function propio(string $s): string
    {
        return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    /** Deja el teléfono a 10 dígitos con el formato de la agenda (777 123 4567). */
    private function telefono(?string $t): ?string
    {
        $d = preg_replace('/\D/', '', (string) $t);
        if (strlen($d) > 10 && str_starts_with($d, '52')) {
            $d = substr($d, 2);
        }
        if (strlen($d) !== 10 || preg_match('/^(\d)\1+$/', $d)) {
            return null;
        }

        return substr($d, 0, 3).' '.substr($d, 3, 3).' '.substr($d, 6);
    }

    private function correo(?string $e): ?string
    {
        $e = strtolower(trim((string) $e));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }
}
