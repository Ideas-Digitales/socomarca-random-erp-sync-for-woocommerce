<?php

namespace Socomarca\RandomERP\Services;

class PriceListService extends BaseApiService {
    
    public function getPriceLists() {
        error_log('PriceListService: Obteniendo listas de precios...');
        
        $company_code = get_option('sm_company_code', '01');
        $endpoint = "/web32/precios/pidelistaprecio?empresa={$company_code}";
        
        error_log("PriceListService: Endpoint: {$endpoint}");
        
        $priceLists = $this->makeApiRequest($endpoint);
        
        error_log('PriceListService: Respuesta raw de API: ' . substr(print_r($priceLists, true), 0, 1000));


        
        if ($priceLists !== false && is_array($priceLists)) {
            error_log('PriceListService: ' . count($priceLists) . ' listas de precios obtenidas');

            

            $post_data = [
                'post_title'   => $priceLists['nombre'],
                'post_content' => '', // Puedes agregar contenido si es necesario
                'post_status'  => 'publish',
                'post_type'    => 'b2bking_group',
            ];

            
            $existing_post = get_page_by_title($post_data['post_title'], OBJECT, 'b2bking_group');
            if (!$existing_post) {
                $post_id = wp_insert_post($post_data);
            } else {
                $post_id = $existing_post->ID;
            }


            foreach ($priceLists as $priceList) {
                foreach ($priceLists['datos'] as $data) {

                    $products = wc_get_products( array( 'sku' => $data['kopr'] ) );
                    foreach ($products as $product) {
                        var_dump($product->get_id());
                        //Agrega meta a producto segun b2b king: https://woocommerce-b2b-plugin.com/docs/b2bking-tiered-pricing-setup-auto-generated-tiered-pricing-table/#2-toc-title
                        $meta_name = "b2bking_product_pricetiers_group_".$post_id;
                    }
                }
            }
            die();
           /*  return [
                'success' => true,
                'message' => count($priceLists) . ' listas de precios obtenidas exitosamente',
                'data' => $priceLists,
                'quantity' => count($priceLists)
            ]; */
        }
        
        error_log('PriceListService: Error - No se pudieron obtener listas de precios válidas');
        return [
            'success' => false,
            'message' => 'No se pudieron obtener las listas de precios del ERP'
        ];
    }
}