<?php

namespace Socomarca\RandomERP\Compat;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registra la cookie `sm_selected_location` (bodega/comuna seleccionada) en
 * el sistema de "vary" de LiteSpeed Cache.
 *
 * El precio, el badge de stock (StockBadgeCustomizer) y el boton de
 * "Añadir al carrito" (via _stock_status filtrado por LocationStockFilter y
 * deshabilitado por ProductStockValidator) dependen todos de esta cookie,
 * pero LiteSpeed no lo sabe: cachea la pagina de producto una sola vez (TTL
 * de hasta 7 dias, sin exclusion de cookies configurada) y sirve esa misma
 * copia a todos los visitantes sin importar que bodega tengan seleccionada.
 * Esto provoca que un visitante vea stock/boton de una bodega distinta a la
 * que realmente tiene elegida, o un estado desactualizado tras un sync ERP.
 *
 * Se registra en los dos filtros publicos de LiteSpeed Cache, igual que hace
 * su propia integracion con Aelia CurrencySwitcher
 * (litespeed-cache/thirdparty/aelia-currencyswitcher.cls.php), porque
 * cumplen roles distintos:
 *   - `litespeed_vary_curr_cookies`: el que realmente entra en el hash usado
 *     como clave de cache en cada request (vary.cls.php::_finalize_curr_vary_cookies).
 *     Sin esto, LiteSpeed sigue sirviendo una unica copia cacheada
 *     independientemente del valor de la cookie.
 *   - `litespeed_vary_cookies`: la lista "siempre presente" usada para las
 *     reglas de rewrite/edge (htaccess.cls.php).
 */
class LiteSpeedCacheVary {

    private const COOKIE_NAME = 'sm_selected_location';

    public function __construct() {
        add_filter('litespeed_vary_curr_cookies', [$this, 'maybeRegisterCookie']);
        add_filter('litespeed_vary_cookies', [$this, 'registerCookie']);
    }

    /**
     * Solo suma la cookie en paginas de WooCommerce (producto, tienda,
     * categoria), que son las que muestran precio/stock/boton dependientes
     * de la bodega seleccionada.
     */
    public function maybeRegisterCookie(array $cookies): array {
        if (!function_exists('is_woocommerce') || !is_woocommerce()) {
            return $cookies;
        }

        return $this->registerCookie($cookies);
    }

    public function registerCookie(array $cookies): array {
        if (!in_array(self::COOKIE_NAME, $cookies, true)) {
            $cookies[] = self::COOKIE_NAME;
        }

        return $cookies;
    }
}
