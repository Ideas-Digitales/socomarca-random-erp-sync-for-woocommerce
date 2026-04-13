<?php

namespace Socomarca\RandomERP\Services;

class WarehouseService extends BaseApiService {

    private $log_file;

    public function __construct() {
        parent::__construct();
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        if (!file_exists($logs_dir)) {
            wp_mkdir_p($logs_dir);
        }
        $this->log_file = $logs_dir . '/warehouses.log';
    }

    protected function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] WarehouseService: $message" . PHP_EOL;
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    public function syncWarehouses() {
        if (!taxonomy_exists('locations')) {
            throw new \Exception('La taxonomía "locations" no existe. Revise la configuración de ubicaciones.');
        }

        $warehouses = $this->makeApiRequest('/bodegas');
        if (!is_array($warehouses)) {
            throw new \Exception('No se pudieron obtener bodegas desde Random ERP');
        }

        $created = 0;
        $updated = 0;
        $errors = 0;
        $processed = [];

        foreach ($warehouses as $warehouse) {
            try {
                $code = sanitize_text_field($warehouse['KOBO'] ?? '');
                $name = sanitize_text_field($warehouse['NOKOBO'] ?? '');

                if (empty($code)) {
                    continue;
                }

                if (isset($processed[$code])) {
                    continue;
                }
                $processed[$code] = true;

                if (empty($name)) {
                    $name = $code;
                }

                $existing_term = $this->findLocationByCode($code);

                if ($existing_term) {
                    $result = wp_update_term($existing_term->term_id, 'locations', [
                        'name' => $name,
                        'slug' => sanitize_title($name),
                    ]);

                    if (is_wp_error($result)) {
                        $errors++;
                        $this->log("Error actualizando bodega {$code}: " . $result->get_error_message());
                        continue;
                    }

                    $term_id = (int) $existing_term->term_id;
                    $updated++;
                } else {
                    $result = wp_insert_term($name, 'locations', [
                        'slug' => sanitize_title($name),
                    ]);

                    if (is_wp_error($result)) {
                        $errors++;
                        $this->log("Error creando bodega {$code}: " . $result->get_error_message());
                        continue;
                    }

                    $term_id = (int) $result['term_id'];
                    $created++;
                }

                update_term_meta($term_id, 'random_erp_warehouse_code', $code);
                update_term_meta($term_id, 'random_erp_kosu', sanitize_text_field($warehouse['KOSU'] ?? ''));
                update_term_meta($term_id, 'random_erp_empresa', sanitize_text_field($warehouse['EMPRESA'] ?? ''));
            } catch (\Exception $e) {
                $errors++;
                $this->log('Error procesando bodega: ' . $e->getMessage());
            }
        }

        $total = count($processed);
        $this->log("Sincronización de bodegas completada: {$created} creadas, {$updated} actualizadas, {$errors} errores");

        return [
            'success' => true,
            'message' => "Sincronización completada: {$created} creadas, {$updated} actualizadas",
            'stats' => [
                'total' => $total,
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors,
            ],
        ];
    }

    private function findLocationByCode($code) {
        $terms = get_terms([
            'taxonomy' => 'locations',
            'meta_query' => [
                [
                    'key' => 'random_erp_warehouse_code',
                    'value' => $code,
                    'compare' => '=',
                ],
            ],
            'hide_empty' => false,
        ]);

        return (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : null;
    }

    public function deleteAllWarehouses() {
        if (!taxonomy_exists('locations')) {
            throw new \Exception('La taxonomía "locations" no existe.');
        }

        $terms = get_terms([
            'taxonomy' => 'locations',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            throw new \Exception('Error obteniendo bodegas: ' . $terms->get_error_message());
        }

        $deleted = 0;
        $errors = 0;

        foreach ($terms as $term) {
            $result = wp_delete_term($term->term_id, 'locations');

            if (!is_wp_error($result) && $result !== false) {
                $deleted++;
            } else {
                $errors++;
                $message = is_wp_error($result) ? $result->get_error_message() : 'Error desconocido';
                $this->log("Error eliminando bodega {$term->name}: {$message}");
            }
        }

        return [
            'success' => true,
            'message' => "Eliminación completada: {$deleted} bodegas eliminadas",
            'stats' => [
                'deleted' => $deleted,
                'errors' => $errors,
            ],
        ];
    }
}
