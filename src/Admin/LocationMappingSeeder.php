<?php

namespace Socomarca\RandomERP\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class LocationMappingSeeder {

    public function run(): void {
        $existing = get_option('sm_location_mapping', []);

        // Solo ejecutar si no hay datos previos
        if (!empty($existing)) {
            return;
        }

        $warehouses = $this->getFirstWarehouseId();

        $mapping = [
            [
                'id'      => 'region-metropolitana',
                'name'    => 'Region Metropolitana',
                'comunas' => $this->buildComunas($this->getComunasRM(), $warehouses),
            ],
            [
                'id'      => 'region-de-valparaiso',
                'name'    => 'Region de Valparaiso',
                'comunas' => $this->buildComunas($this->getComunasValparaiso(), $warehouses),
            ],
        ];

        update_option('sm_location_mapping', $mapping);
    }

    private function buildComunas(array $names, ?int $warehouseId): array {
        $comunas = [];
        foreach ($names as $name) {
            $comunas[] = [
                'id'           => sanitize_title($name),
                'name'         => $name,
                'warehouse_id' => $warehouseId,
            ];
        }
        return $comunas;
    }

    private function getFirstWarehouseId(): ?int {
        $terms = get_terms([
            'taxonomy'   => 'locations',
            'hide_empty' => false,
            'number'     => 1,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            return (int) $terms[0]->term_id;
        }

        return null;
    }

    private function getComunasRM(): array {
        return [
            'Cerrillos',
            'Cerro Navia',
            'Colina',
            'Conchali',
            'El Bosque',
            'El Monte',
            'Estacion Central',
            'Huechuraba',
            'Independencia',
            'Isla de Maipo',
            'La Florida',
            'La Granja',
            'La Pintana',
            'La Reina',
            'Lampa',
            'Las Condes',
            'Lo Barnechea',
            'Lo Espejo',
            'Lo Prado',
            'Macul',
            'Maipu',
            'Maria Pinto',
            'Melipilla',
            'Nunoa',
            'Padre Hurtado',
            'Paine',
            'Pedro Aguirre Cerda',
            'Penaflor',
            'Penalolen',
            'Pirque',
            'Providencia',
            'Pudahuel',
            'Quilicura',
            'Quinta Normal',
            'Recoleta',
            'Renca',
            'San Bernardo',
            'San Joaquin',
            'San Miguel',
            'San Pedro de Melipilla',
            'San Ramon',
            'Santiago',
            'Talagante',
            'Tiltil',
            'Vitacura',
        ];
    }

    private function getComunasValparaiso(): array {
        return [
            'Algarrobo',
            'Cabildo',
            'Calera',
            'Calle Larga',
            'Cartagena',
            'Casablanca',
            'Catemu',
            'Concon',
            'El Quisco',
            'El Tabo',
            'Hijuelas',
            'La Cruz',
            'La Ligua',
            'Limache',
            'Llay-Llay',
            'Los Andes',
            'Nogales',
            'Olmue',
            'Papudo',
            'Petorca',
            'Puchuncavi',
            'Putaendo',
            'Quillota',
            'Quilpue',
            'Quintero',
            'Rinconada',
            'San Antonio',
            'San Esteban',
            'San Felipe',
            'Santo Domingo',
            'Valparaiso',
            'Villa Alemana',
            'Vina del Mar',
            'Zapallar',
        ];
    }
}
