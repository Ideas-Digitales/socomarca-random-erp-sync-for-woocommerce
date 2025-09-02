<?php

namespace Socomarca\RandomERP\Services;

class EntityService extends BaseApiService {
    
    public function getEntities() {
        $company_code = get_option('sm_company_code', '01');
        $company_rut = get_option('sm_company_rut', '134549696');
        $modalidad = get_option('sm_modalidad', 'SUC01');
        
        $endpoint = "/web32/entidades?empresa={$company_code}&rut={$company_rut}&modalidad={$modalidad}";
        $entities = $this->makeApiRequest($endpoint);
        
        if ($entities !== false && is_array($entities)) {
            return [
                'quantity' => count($entities),
                'items' => $entities
            ];
        }
        
        return false;
    }
    
    public function createUsersFromEntities() {
        $entities = $this->getEntities();
        
        if (!$entities || !is_array($entities['items'])) {
            return [
                'success' => false,
                'message' => 'No se pudieron obtener las entidades'
            ];
        }
        
        update_option('sm_entities_cache', $entities['items']);
        
        return [
            'success' => true,
            'total' => $entities['quantity'],
            'message' => $entities['quantity'] . ' entidades obtenidas. Iniciando creación de usuarios...'
        ];
    }
    
    public function processBatchUsers($offset = 0, $batch_size = 10) {
        $cached_entities = get_option('sm_entities_cache', []);
        
        if (empty($cached_entities)) {
            return [
                'success' => false,
                'message' => 'No hay entidades en cache'
            ];
        }
        
        $batch = array_slice($cached_entities, $offset, $batch_size);
        $batch_count = count($batch);
        $created_users = 0;
        $updated_users = 0;
        $errors = [];
        
        
        $total_created = intval(get_option('sm_total_created_users', 0));
        $total_updated = intval(get_option('sm_total_updated_users', 0));
        
        foreach ($batch as $entidad) {
            try {
                $rut = isset($entidad['KOEN']) ? $entidad['KOEN'] : null;
                
                if (empty($rut)) {
                    continue;
                }
                
                
                $existing_user = get_users([
                    'meta_key' => 'rut',
                    'meta_value' => $rut,
                    'number' => 1
                ]);
                
                $user_data = [
                    'user_login' => $rut,
                    'user_email' => $rut . '@temp.com',
                    'display_name' => isset($entidad['NOKOEN']) ? $entidad['NOKOEN'] : '',
                    'first_name' => isset($entidad['NOKOEN']) ? $entidad['NOKOEN'] : '',
                    'role' => 'customer'
                ];
                
                if (!empty($existing_user)) {
                    
                    $user_id = $existing_user[0]->ID;
                    $user_data['ID'] = $user_id;
                    $result = wp_update_user($user_data);

                    // Llamada segura para asignación de grupo B2B King
                    if (isset($entidad['KOLTVEN']) && is_array($entidad['KOLTVEN'])) {
                        $this->assing_user_to_b2bking_group($user_id, $entidad['KOLTVEN']);
                    }
                    $this->update_woocommerce_data($user_id, $entidad, $user_data);
                    
                    if (is_wp_error($result)) {
                        $errors[] = 'Error actualizando usuario ' . $rut . ': ' . $result->get_error_message();
                        continue;
                    }
                    
                    $updated_users++;
                } else {
                    
                    $user_data['user_pass'] = wp_generate_password();
                    $user_id = wp_insert_user($user_data);

                    if (is_wp_error($user_id)) {
                        $errors[] = 'Error creando usuario ' . $rut . ': ' . $user_id->get_error_message();
                        continue;
                    }

                    // Llamada segura para asignación de grupo B2B King
                    if (isset($entidad['KOLTVEN']) && is_array($entidad['KOLTVEN'])) {
                        $this->assing_user_to_b2bking_group($user_id, $entidad['KOLTVEN']);
                    }
                    $this->update_woocommerce_data($user_id, $entidad, $user_data);

                    //Enviar correo para que el usuario cambie su contraseña
                    if($batch_count == 10) {  //Esto es temporal, para no saturar mi cuenta de mailtrap, asi solo envua un solo email por request
                        $this->send_password_change_email($user_id);
                    }
                    
                    $created_users++;
                }
                
                
                update_user_meta($user_id, 'rut', $rut);
                update_user_meta($user_id, 'business_name', isset($entidad['SIEN']) ? $entidad['SIEN'] : '');
                update_user_meta($user_id, 'phone', isset($entidad['FOEN']) ? $entidad['FOEN'] : '');
                update_user_meta($user_id, 'is_active', true);
                
            } catch (Exception $e) {
                $errors[] = 'Error procesando entidad: ' . $e->getMessage();
            }
        }
        
        $processed = $offset + count($batch);
        $total = count($cached_entities);
        $is_complete = $processed >= $total;
        
        
        $total_created += $created_users;
        $total_updated += $updated_users;
        update_option('sm_total_created_users', $total_created);
        update_option('sm_total_updated_users', $total_updated);
        
        
        if ($is_complete) {
            delete_option('sm_entities_cache');
            delete_option('sm_total_created_users');
            delete_option('sm_total_updated_users');
        }
        
        return [
            'success' => true,
            'created' => $created_users,
            'updated' => $updated_users,
            'total_created' => $total_created,
            'total_updated' => $total_updated,
            'errors' => $errors,
            'processed' => $processed,
            'total' => $total,
            'is_complete' => $is_complete,
            'message' => "Lote procesado: $created_users creados, $updated_users actualizados"
        ];
    }
    
    public function deleteAllUsersExceptAdmin() {
        $deleted_count = 0;
        $errors = [];
        
        try {
            
            $users = get_users([
                'role__not_in' => ['administrator', 'super_admin'],
                'fields' => ['ID', 'user_login']
            ]);
            
            foreach ($users as $user) {
                
                if (!user_can($user->ID, 'manage_options')) {
                    $result = wp_delete_user($user->ID);
                    if ($result) {
                        $deleted_count++;
                    } else {
                        $errors[] = "Error eliminando usuario: {$user->user_login}";
                    }
                }
            }
            
            return [
                'success' => true,
                'deleted' => $deleted_count,
                'errors' => $errors,
                'message' => "Se eliminaron $deleted_count usuarios exitosamente"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error eliminando usuarios: ' . $e->getMessage()
            ];
        }
    }

    public function assing_user_to_b2bking_group($user_id, $groups) {

        $groups_ids = [];
        foreach ($groups as $group) {
            $group = get_page_by_title($group, OBJECT, 'b2bking_group');
            if (!$group) {
                return false;
            }
            $groups_ids[] = $group->ID;
        }


        // B2B King solo soporta un grupo por usuario, si se agrega otro grupo se reemplaza el anterior
        foreach ($groups_ids as $group_id) {
            $user_group = get_user_meta($user_id, 'b2bking_customergroup', $group_id);
            if(!$user_group) {
                update_user_meta($user_id, 'b2bking_b2buser', 'yes');
                update_user_meta($user_id, 'b2bking_customergroup', $group_id);
            }
        }
    }

    public function update_woocommerce_data($user_id, $erp_data, $user_data) {

        $billing_data = [
            'first_name'   => $user_data['first_name'],
            'last_name'    => $user_data['last_name'],
            'address_1'    => $erp_data['DIEN'],
            'city'         => $erp_data['NOKOCM'],
            'postcode'     => 4950000,
            'country'      => $erp_data['PAEN'],
            'state'        => $erp_data['NOKOCI'],
            'email'        => $user_data['user_email'],
            'phone'        => $erp_data['FOEN'],
        ];
        
        $shipping_data = [
            'first_name'   => $user_data['first_name'],
            'last_name'    => $user_data['last_name'],
            'address_1'    => $erp_data['DIEN'],
            'city'         => $erp_data['NOKOCM'],
            'postcode'     => 4950000,
            'country'      => $erp_data['PAEN'],
            'state'        => $erp_data['NOKOCI'],
        ];

        // Actualizar facturación
        foreach ( $billing_data as $key => $value ) {
            update_user_meta( $user_id, 'billing_' . $key, $value );
        }

        // Actualizar envío
        foreach ( $shipping_data as $key => $value ) {
            update_user_meta( $user_id, 'shipping_' . $key, $value );
        }
    }

    public function send_password_change_email($user_id) {
        $user = get_user_by('ID', $user_id);

        
        if (!$user) {
            return false;
        }
        
        // Usar la función nativa de WordPress para enviar el enlace de reseteo
        $result = retrieve_password($user->user_login);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        return true;
    }
}