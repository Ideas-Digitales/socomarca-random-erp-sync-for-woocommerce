<?php

namespace Socomarca\RandomERP\Services;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Servicio de notificación de nuevos pedidos a las sucursales.
 *
 * Cuando se crea o completa un pedido, detecta la sucursal asociada
 * (vía meta sm_pickup_store_id) y envía un email de aviso al correo
 * configurado en la taxonomy "locations" (campo cmlim_email).
 *
 * El contenido del email usa el post ID configurado en la opción
 * 'sm_location_notification_email_post_id'. Si no está configurado,
 * se genera un email genérico con el resumen del pedido.
 */
class LocationOrderNotificationService {

    public function __construct() {
        // Se dispara cuando WooCommerce cambia el estado a "pending" (pedido nuevo)
        add_action('woocommerce_checkout_order_processed', [$this, 'onOrderCreated'], 20, 3);
        // También al pagar (por si el pago es directo, ej: transferencia)
        add_action('woocommerce_payment_complete', [$this, 'onPaymentComplete'], 20, 1);
    }

    /**
     * Se ejecuta cuando se procesa el checkout.
     *
     * @param int   $order_id  ID del pedido recién creado.
     * @param array $posted_data Datos del formulario de checkout.
     * @param WC_Order $order  Objeto del pedido.
     */
    public function onOrderCreated(int $order_id, array $posted_data, $order): void {
        $this->sendLocationNotification($order_id);
    }

    /**
     * Se ejecuta cuando se completa el pago (Webpay, transferencia, etc.).
     *
     * @param int $order_id ID del pedido pagado.
     */
    public function onPaymentComplete(int $order_id): void {
        // Evitar doble envío si ya se notificó al crear
        $already_sent = get_post_meta($order_id, '_sm_location_notification_sent', true);
        if ($already_sent) {
            return;
        }
        $this->sendLocationNotification($order_id);
    }

    /**
     * Lógica central: obtiene la location del pedido, busca el email
     * configurado en la taxonomy y envía la notificación.
     *
     * @param int $order_id ID del pedido.
     */
    public function sendLocationNotification(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }

        // 1. Obtener el term_id de la location asociada al pedido
        $store_id = (int) get_post_meta($order_id, 'sm_pickup_store_id', true);

        if (empty($store_id)) {
            // Intentar resolver por ciudad de envío si no hay retiro en tienda
            $store_id = $this->resolveStoreIdByOrder($order);
        }

        if (empty($store_id)) {
            $this->log("Pedido #{$order_id}: No se encontró sucursal asociada. No se envía notificación.");
            return;
        }

        // 2. Obtener el email del term meta (campo cmlim_email del plugin WC Multi-location)
        $location_email = get_term_meta($store_id, 'cmlim_email', true);

        if (empty($location_email)) {
            $this->log("Pedido #{$order_id}: Sucursal ID {$store_id} no tiene email configurado (cmlim_email).");
            return;
        }

        // Validar que sea un email válido
        if (!is_email($location_email)) {
            $this->log("Pedido #{$order_id}: El email '{$location_email}' de la sucursal ID {$store_id} no es válido.");
            return;
        }

        // Evitar doble envío
        $already_sent = get_post_meta($order_id, '_sm_location_notification_sent', true);
        if ($already_sent) {
            $this->log("Pedido #{$order_id}: Notificación ya enviada previamente. Se omite.");
            return;
        }

        // 3. Obtener nombre de la location
        $term = get_term($store_id, 'locations');
        $location_name = ($term && !is_wp_error($term)) ? $term->name : "Sucursal #{$store_id}";

        // 4. Construir y enviar el email
        $subject = $this->buildSubject($order, $location_name);
        $message = $this->buildMessage($order, $location_name, $store_id);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];

        $sent = wp_mail($location_email, $subject, $message, $headers);

        if ($sent) {
            // Marcar como enviado para evitar duplicados
            update_post_meta($order_id, '_sm_location_notification_sent', '1');
            update_post_meta($order_id, '_sm_location_notification_email', $location_email);
            $order->add_order_note(
                sprintf('Notificación de nuevo pedido enviada a la sucursal "%s" (%s).', esc_html($location_name), esc_html($location_email))
            );
            $this->log("Pedido #{$order_id}: Notificación enviada a {$location_email} (Sucursal: {$location_name}).");
        } else {
            $this->log("Pedido #{$order_id}: Falló el envío del email a {$location_email} (Sucursal: {$location_name}).");
        }
    }

    /**
     * Intenta resolver el store_id desde la ciudad de despacho/facturación
     * usando el mapeo de comunas configurado en el plugin.
     *
     * @param WC_Order $order
     * @return int|null
     */
    private function resolveStoreIdByOrder(WC_Order $order): ?int {
        $commune = $order->get_shipping_city() ?: $order->get_billing_city();

        if (empty($commune)) {
            return null;
        }

        $mapping = get_option('sm_location_mapping', []);

        if (empty($mapping) || !is_array($mapping)) {
            return null;
        }

        $communeLower = strtolower(trim($commune));

        foreach ($mapping as $region) {
            if (!isset($region['comunas']) || !is_array($region['comunas'])) {
                continue;
            }
            foreach ($region['comunas'] as $comunaData) {
                if (!isset($comunaData['name']) || !isset($comunaData['warehouse_id'])) {
                    continue;
                }
                if (strtolower(trim($comunaData['name'])) === $communeLower) {
                    return (int) $comunaData['warehouse_id'];
                }
            }
        }

        return null;
    }

    /**
     * Construye el asunto del email.
     *
     * @param WC_Order $order
     * @param string   $location_name
     * @return string
     */
    private function buildSubject(WC_Order $order, string $location_name): string {
        return sprintf(
            '[%s] Nuevo pedido #%s — %s',
            get_bloginfo('name'),
            $order->get_order_number(),
            $location_name
        );
    }

    /**
     * Construye el cuerpo HTML del email.
     * Si existe un post configurado como template (opción sm_location_notification_email_post_id),
     * lo usa como base reemplazando placeholders.
     * Si no, genera un email estándar con el resumen del pedido.
     *
     * @param WC_Order $order
     * @param string   $location_name
     * @param int      $store_id
     * @return string
     */
    private function buildMessage(WC_Order $order, string $location_name, int $store_id): string {
        $template_post_id = (int) get_option('sm_location_notification_email_post_id', 0);

        if ($template_post_id > 0) {
            $post = get_post($template_post_id);
            if ($post && $post->post_status === 'publish') {
                return $this->renderTemplatePost($post, $order, $location_name, $store_id);
            }
        }

        return $this->renderDefaultMessage($order, $location_name, $store_id);
    }

    /**
     * Renderiza el email usando un post de WordPress como template.
     * Reemplaza los siguientes placeholders:
     *
     *   {{order_number}}     → número del pedido
     *   {{order_date}}       → fecha del pedido
     *   {{order_total}}      → total formateado
     *   {{order_status}}     → estado del pedido
     *   {{customer_name}}    → nombre completo del cliente
     *   {{customer_email}}   → email del cliente
     *   {{customer_phone}}   → teléfono del cliente
     *   {{location_name}}    → nombre de la sucursal
     *   {{order_items}}      → tabla HTML con los productos
     *   {{shipping_address}} → dirección de envío
     *   {{order_url}}        → URL del pedido en el admin
     *
     * @param \WP_Post $post
     * @param WC_Order $order
     * @param string   $location_name
     * @param int      $store_id
     * @return string
     */
    private function renderTemplatePost(\WP_Post $post, WC_Order $order, string $location_name, int $store_id): string {
        $content = $post->post_content;

        // Aplicar filtros de WordPress para respetar shortcodes, etc.
        $content = apply_filters('the_content', $content);

        $placeholders = $this->buildPlaceholders($order, $location_name, $store_id);

        foreach ($placeholders as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $this->wrapInEmailLayout($content, $location_name, $order);
    }

    /**
     * Genera un email por defecto sin template personalizado.
     *
     * @param WC_Order $order
     * @param string   $location_name
     * @param int      $store_id
     * @return string
     */
    private function renderDefaultMessage(WC_Order $order, string $location_name, int $store_id): string {
        $placeholders = $this->buildPlaceholders($order, $location_name, $store_id);

        $items_html = $placeholders['order_items'];
        $order_number = $placeholders['order_number'];
        $order_date = $placeholders['order_date'];
        $order_total = $placeholders['order_total'];
        $order_status = $placeholders['order_status'];
        $customer_name = $placeholders['customer_name'];
        $customer_email = $placeholders['customer_email'];
        $customer_phone = $placeholders['customer_phone'];
        $shipping_address = $placeholders['shipping_address'];
        $order_url = $placeholders['order_url'];
        $site_name = esc_html(get_bloginfo('name'));

        $content = "
            <h2 style='color:#2c3e50; border-bottom:2px solid #3498db; padding-bottom:10px;'>
                Nuevo pedido en {$site_name}
            </h2>
            <p>Se ha recibido un nuevo pedido asignado a tu sucursal <strong>{$location_name}</strong>.</p>

            <table style='width:100%; border-collapse:collapse; margin:15px 0;'>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; width:40%; border:1px solid #dee2e6;'>Número de pedido</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>#{$order_number}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Fecha</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$order_date}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Estado</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$order_status}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Total</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'><strong>{$order_total}</strong></td>
                </tr>
            </table>

            <h3 style='color:#2c3e50;'>Datos del cliente</h3>
            <table style='width:100%; border-collapse:collapse; margin:15px 0;'>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; width:40%; border:1px solid #dee2e6;'>Nombre</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$customer_name}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Email</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$customer_email}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Teléfono</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$customer_phone}</td>
                </tr>
                <tr>
                    <td style='padding:8px; background:#f8f9fa; font-weight:bold; border:1px solid #dee2e6;'>Dirección de envío</td>
                    <td style='padding:8px; border:1px solid #dee2e6;'>{$shipping_address}</td>
                </tr>
            </table>

            <h3 style='color:#2c3e50;'>Productos del pedido</h3>
            {$items_html}

            <p style='margin-top:20px;'>
                <a href='{$order_url}' style='background:#3498db; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; display:inline-block;'>
                    Ver pedido en el panel
                </a>
            </p>
        ";

        return $this->wrapInEmailLayout($content, $location_name, $order);
    }

    /**
     * Construye el array de placeholders con los datos del pedido.
     *
     * @param WC_Order $order
     * @param string   $location_name
     * @param int      $store_id
     * @return array<string, string>
     */
    private function buildPlaceholders(WC_Order $order, string $location_name, int $store_id): array {
        // Tabla de items
        $items_html = $this->buildItemsTable($order);

        // Dirección de envío
        $shipping_parts = array_filter([
            $order->get_shipping_address_1(),
            $order->get_shipping_address_2(),
            $order->get_shipping_city(),
            $order->get_shipping_state(),
            $order->get_shipping_postcode(),
            $order->get_shipping_country(),
        ]);
        $shipping_address = implode(', ', $shipping_parts) ?: 'Retiro en tienda';

        return [
            'order_number'     => $order->get_order_number(),
            'order_date'       => wc_format_datetime($order->get_date_created()),
            'order_total'      => wp_strip_all_tags(wc_price($order->get_total())),
            'order_status'     => wc_get_order_status_name($order->get_status()),
            'customer_name'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'customer_email'   => $order->get_billing_email(),
            'customer_phone'   => $order->get_billing_phone() ?: 'No indicado',
            'location_name'    => esc_html($location_name),
            'order_items'      => $items_html,
            'shipping_address' => esc_html($shipping_address),
            'order_url'        => esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')),
            'site_name'        => esc_html(get_bloginfo('name')),
            'site_url'         => esc_url(get_bloginfo('url')),
        ];
    }

    /**
     * Construye la tabla HTML de productos del pedido.
     *
     * @param WC_Order $order
     * @return string
     */
    private function buildItemsTable(WC_Order $order): string {
        $html = "
            <table style='width:100%; border-collapse:collapse;'>
                <thead>
                    <tr style='background:#3498db; color:#fff;'>
                        <th style='padding:10px; text-align:left;'>Producto</th>
                        <th style='padding:10px; text-align:center;'>SKU</th>
                        <th style='padding:10px; text-align:center;'>Cantidad</th>
                        <th style='padding:10px; text-align:right;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
        ";

        $row_index = 0;
        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $bg = ($row_index % 2 === 0) ? '#ffffff' : '#f8f9fa';
            $product = $item->get_product();
            $sku = ($product && $product->get_sku()) ? esc_html($product->get_sku()) : '—';

            $html .= "
                <tr style='background:{$bg};'>
                    <td style='padding:8px; border-bottom:1px solid #dee2e6;'>" . esc_html($item->get_name()) . "</td>
                    <td style='padding:8px; border-bottom:1px solid #dee2e6; text-align:center;'>{$sku}</td>
                    <td style='padding:8px; border-bottom:1px solid #dee2e6; text-align:center;'>" . esc_html($item->get_quantity()) . "</td>
                    <td style='padding:8px; border-bottom:1px solid #dee2e6; text-align:right;'>" . wp_strip_all_tags(wc_price($item->get_total())) . "</td>
                </tr>
            ";
            $row_index++;
        }

        // Totales
        $html .= "
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='3' style='padding:8px; text-align:right; font-weight:bold;'>Subtotal:</td>
                        <td style='padding:8px; text-align:right;'>" . wp_strip_all_tags(wc_price($order->get_subtotal())) . "</td>
                    </tr>
        ";

        // Envío
        if ($order->get_shipping_total() > 0) {
            $html .= "
                    <tr>
                        <td colspan='3' style='padding:8px; text-align:right; font-weight:bold;'>Envío:</td>
                        <td style='padding:8px; text-align:right;'>" . wp_strip_all_tags(wc_price($order->get_shipping_total())) . "</td>
                    </tr>
            ";
        }

        $html .= "
                    <tr style='background:#2c3e50; color:#fff;'>
                        <td colspan='3' style='padding:10px; text-align:right; font-weight:bold;'>TOTAL:</td>
                        <td style='padding:10px; text-align:right; font-weight:bold;'>" . wp_strip_all_tags(wc_price($order->get_total())) . "</td>
                    </tr>
                </tfoot>
            </table>
        ";

        return $html;
    }

    /**
     * Envuelve el contenido en el layout de email estándar de WooCommerce.
     *
     * @param string   $content
     * @param string   $location_name
     * @param WC_Order $order
     * @return string
     */
    private function wrapInEmailLayout(string $content, string $location_name, WC_Order $order): string {
        $site_name = esc_html(get_bloginfo('name'));
        $site_url  = esc_url(get_bloginfo('url'));

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo pedido #<?php echo esc_html($order->get_order_number()); ?></title>
</head>
<body style="font-family: Arial, sans-serif; font-size:14px; color:#333; background:#f0f0f0; margin:0; padding:0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0; padding:20px 0;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:6px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background:#2c3e50; padding:24px 32px; text-align:center;">
                            <h1 style="margin:0; color:#fff; font-size:22px;"><?php echo $site_name; ?></h1>
                            <p style="margin:6px 0 0; color:#bdc3c7; font-size:13px;">Notificación de nuevo pedido — <?php echo esc_html($location_name); ?></p>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style="padding:28px 32px;">
                            <?php echo $content; ?>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style="background:#f8f9fa; padding:16px 32px; text-align:center; border-top:1px solid #dee2e6;">
                            <p style="margin:0; font-size:12px; color:#999;">
                                Este correo fue enviado automáticamente por <a href="<?php echo $site_url; ?>" style="color:#3498db;"><?php echo $site_name; ?></a>.
                                Por favor no respondas a este mensaje.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Escribe en el log del plugin.
     *
     * @param string $message
     */
    private function log(string $message): void {
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $log_file  = $logs_dir . '/location-notifications.log';
        $timestamp = gmdate('Y-m-d H:i:s');
        file_put_contents($log_file, "[{$timestamp}] LocationNotification: {$message}" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
