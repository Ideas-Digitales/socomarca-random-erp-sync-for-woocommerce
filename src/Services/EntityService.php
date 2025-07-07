<?php

namespace Socomarca\RandomERP\Services;

class EntityService extends BaseApiService {
    
    public function getEntities() {
        error_log('EntityService: Obteniendo entidades...');
        
        $company_code = get_option('sm_company_code', '01');
        $company_rut = get_option('sm_company_rut', '134549696');
        
        $endpoint = "/web32/entidades?empresa={$company_code}&rut={$company_rut}";
        $entities = $this->makeApiRequest($endpoint);
        
        if ($entities !== false && is_array($entities)) {
            error_log('EntityService: ' . count($entities) . ' entidades obtenidas');
            return [
                'quantity' => count($entities),
                'items' => $entities
            ];
        }
        
        error_log('EntityService: Error - No se pudieron obtener entidades válidas');
        return false;
    }
    
    public function createUsersFromEntities() {
        error_log('EntityService: Iniciando creación de usuarios...');
        
        $entities = $this->getEntities();
        
        if (!$entities || !is_array($entities['items'])) {
            error_log('EntityService: Error - No se pudieron obtener las entidades');
            return [
                'success' => false,
                'message' => 'No se pudieron obtener las entidades'
            ];
        }
        
        // Guardar las entidades en cache para procesamiento por lotes
        update_option('sm_entities_cache', $entities['items']);
        
        error_log('EntityService: Éxito - ' . $entities['quantity'] . ' entidades guardadas en cache');
        
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
        $created_users = 0;
        $updated_users = 0;
        $errors = [];
        
        // Obtener contadores acumulativos
        $total_created = intval(get_option('sm_total_created_users', 0));
        $total_updated = intval(get_option('sm_total_updated_users', 0));
        
        foreach ($batch as $entidad) {
            try {
                $rut = isset($entidad['KOEN']) ? $entidad['KOEN'] : null;
                
                if (empty($rut)) {
                    continue;
                }
                
                // Buscar usuario existente por RUT en meta_value
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
                    // Actualizar usuario existente
                    $user_id = $existing_user[0]->ID;
                    $user_data['ID'] = $user_id;
                    $result = wp_update_user($user_data);
                    
                    if (is_wp_error($result)) {
                        $errors[] = 'Error actualizando usuario ' . $rut . ': ' . $result->get_error_message();
                        continue;
                    }
                    
                    $updated_users++;
                } else {
                    // Crear nuevo usuario
                    $user_data['user_pass'] = wp_generate_password();
                    $user_id = wp_insert_user($user_data);
                    
                    if (is_wp_error($user_id)) {
                        $errors[] = 'Error creando usuario ' . $rut . ': ' . $user_id->get_error_message();
                        continue;
                    }
                    
                    $created_users++;
                }
                
                // Actualizar meta campos
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
        
        // Actualizar contadores acumulativos
        $total_created += $created_users;
        $total_updated += $updated_users;
        update_option('sm_total_created_users', $total_created);
        update_option('sm_total_updated_users', $total_updated);
        
        // Limpiar cache y contadores si terminamos
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
            // Obtener todos los usuarios excepto administradores
            $users = get_users([
                'role__not_in' => ['administrator', 'super_admin'],
                'fields' => ['ID', 'user_login']
            ]);
            
            foreach ($users as $user) {
                // Doble verificación: no borrar usuarios con capacidad de administrador
                if (!user_can($user->ID, 'manage_options')) {
                    $result = wp_delete_user($user->ID);
                    if ($result) {
                        $deleted_count++;
                        error_log("Usuario eliminado: {$user->user_login} (ID: {$user->ID})");
                    } else {
                        $errors[] = "Error eliminando usuario: {$user->user_login}";
                    }
                } else {
                    error_log("Usuario admin protegido: {$user->user_login} (ID: {$user->ID})");
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
}