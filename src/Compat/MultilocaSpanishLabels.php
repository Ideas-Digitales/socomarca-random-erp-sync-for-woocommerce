<?php

namespace Socomarca\RandomERP\Compat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pone en español los textos de la tabla "Location Availability" que imprime
 * multiloca-lite en la página de producto (multiloca-lite-inventory / clase
 * multiloca-lite-table), la misma tabla que se ve en, por ejemplo,
 * esponja-de-acero-gruesa-manlac-1-unid. Todo el resto del sitio esta en
 * español, asi que esta tabla en ingles ("Location Availability", "In Stock",
 * "Out of Stock", "Location", "Stock") desentonaba con el resto del diseño
 * de disponibilidad de stock.
 *
 * Encabezado y textos de los badges: son opciones de WordPress
 * (wcmlim_txt_in_fdiv, wcmlim_txt_in_btn_instock, wcmlim_txt_in_btn_outofstock)
 * que multiloca-lite deja disponibles para personalizar desde su propio panel
 * de ajustes, pero que nunca se configuraron (no existen en wp_options), por
 * lo que caian en sus defaults en ingles. Se fijan aqui con add_option() --
 * solo escribe la primera vez; si un admin las cambia luego desde el panel de
 * multiloca-lite, esas ediciones se respetan y no se pisan en cada carga.
 *
 * "Location" / "Stock" (encabezados de columna) y el aviso de "select a
 * variation": no son opciones, son strings de i18n fijos en las vistas de
 * multiloca-lite (public/controller/shop/views/*.php), por lo que se
 * traducen via el filtro `gettext` scopeado a su textdomain.
 */
class MultilocaSpanishLabels {

    private const TEXT_OPTIONS = [
        'wcmlim_txt_in_fdiv'         => 'Disponibilidad por ubicación',
        'wcmlim_txt_in_btn_instock'  => 'disponibles',
        'wcmlim_txt_in_btn_outofstock' => 'Sin stock',
    ];

    private const GETTEXT_STRINGS = [
        'Location'                                                  => 'Ubicación',
        'Stock'                                                     => 'Stock',
        'Please select a variation to see location availability.'   => 'Selecciona una variación para ver la disponibilidad por ubicación.',
    ];

    public function __construct() {
        add_action('init', [$this, 'setDefaultSpanishOptions']);
        add_filter('gettext', [$this, 'translateFixedStrings'], 10, 3);
    }

    public function setDefaultSpanishOptions(): void {
        foreach (self::TEXT_OPTIONS as $option => $spanish_text) {
            add_option($option, $spanish_text);
        }
    }

    public function translateFixedStrings($translation, $text, $domain) {
        if ($domain !== 'multiloca-lite-multi-location-inventory') {
            return $translation;
        }

        return self::GETTEXT_STRINGS[$text] ?? $translation;
    }
}
