<?php
/** Autolex Portal rendering component. */
if (!defined('ABSPATH')) { exit; }

trait Autolex_Portal_Utilities_Trait
{
    private function source_status_label($name)
    {
        $labels = array(
            'active'           => 'AKTÍV IMPORT',
            'planned'          => 'KÖVETKEZŐ ADATRÉTEG',
            'reference_only'   => 'REFERENCIA',
            'active_reference' => 'AKTÍV SZABÁLY',
        );
        return $labels[$name] ?? strtoupper($name);
    }

    /** @param string $status Verification status. @return string */
    private function verification_label($status)
    {
        $labels = array(
            'verified'     => 'Több forrással ellenőrzött',
            'reviewed'     => 'Felülvizsgált',
            'vin_required' => 'VIN-ellenőrzés szükséges',
            'conflict'     => 'Forrásellentmondás',
            'proposed'     => 'Forrásalapú javaslat',
            'provisional'  => 'Előzetes hivatalos adat',
            'unverified'   => 'Ellenőrzésre vár',
            'imported'     => 'Importált alaprekord',
        );
        return $labels[$status] ?? 'Importált alaprekord';
    }

    /** @param string $name Make name. @return string */
    private function make_initials($name)
    {
        $words = preg_split('/[\s\-]+/u', trim($name), 3, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return 'AL';
        }
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $letters .= function_exists('mb_substr') ? mb_substr($word, 0, 1) : substr($word, 0, 1);
        }
        return strtoupper($letters);
    }

    /** @param string $table Raw table. @return string */
    private function safe_table($table)
    {
        return preg_match('/^[A-Za-z0-9_]+$/', (string) $table) ? '`' . $table . '`' : '';
    }

    /** @param string $name Icon name. @return string */
    private function icon($name)
    {
        $paths = array(
            'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
            'shield'   => '<path d="M12 3 4.5 6v5.5c0 4.8 3.2 7.9 7.5 9.5 4.3-1.6 7.5-4.7 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-5"/>',
            'free'     => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5c-.8-.8-1.8-1.2-3-1.2-2.5 0-4.5 2.1-4.5 4.7s2 4.7 4.5 4.7c1.2 0 2.2-.4 3-1.2"/><path d="M6.5 10.5h6M6.5 13.5h6"/>',
            'arrow'    => '<path d="M5 12h14M14 7l5 5-5 5"/>',
            'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 13v6H5V6h6"/>',
            'engine'   => '<path d="M5 9h11l3 3v5H7l-2-2V9Z"/><path d="M9 9V6h5v3M3 12h2M19 13h2M9 17v2M15 17v2"/>',
            'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'source'   => '<path d="M7 3h10v18H7z"/><path d="M10 7h4M10 11h4M10 15h4"/>',
            'warning'  => '<path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/>',
            'market'   => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>',
            'recall'   => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 5v6h-6"/>',
        );
        $path = $paths[$name] ?? $paths['source'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
    }
}
