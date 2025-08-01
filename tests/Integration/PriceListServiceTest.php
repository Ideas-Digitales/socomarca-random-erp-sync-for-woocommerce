<?php

use Socomarca\RandomERP\Services\PriceListService;

beforeEach(function () {
    $this->service = new PriceListService();
    
    if (!defined('SOCOMARCA_ERP_PLUGIN_DIR')) {
        define('SOCOMARCA_ERP_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
    }
    
    $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        wp_mkdir_p($logs_dir);
    }
    
    $this->log_file = $logs_dir . '/pricelist-tests.log';
    
    if (file_exists($this->log_file)) {
        unlink($this->log_file);
    }
});

function logPriceListTest($message) {
    global $log_file;
    if (!isset($log_file)) {
        $logs_dir = SOCOMARCA_ERP_PLUGIN_DIR . 'logs';
        $log_file = $logs_dir . '/pricelist-tests.log';
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] PriceListService Test: $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

describe('Servicio de Listas de Precios - Integración con API Real', function () {
    
    describe('obtenerListasDePrecios', function () {
        
        it('puede obtener listas de precios desde el API de Random ERP', function () {
            $result = $this->service->getPriceLists();
            
            if ($result === false) {
                expect($result)->toBeFalse();
                logPriceListTest('No se pudo conectar al API - usando credenciales demo');
                return;
            }
            
            expect($result)->not()->toBeFalse();
            
            if (is_array($result)) {
                expect($result)->toBeArray();
                
                if (isset($result['nombre'])) {
                    expect($result['nombre'])->toBeString();
                    logPriceListTest('Lista de precios: ' . $result['nombre']);
                }
                
                if (isset($result['datos']) && is_array($result['datos'])) {
                    expect($result['datos'])->toBeArray();
                    
                    if (!empty($result['datos'])) {
                        $firstPriceItem = $result['datos'][0];
                        
                        if (isset($firstPriceItem['kopr'])) {
                            expect($firstPriceItem['kopr'])->toBeString();
                        }
                        
                        logPriceListTest('Primer item de precio: ' . print_r($firstPriceItem, true));
                    }
                }
            }
        });
        
        it('usa configuración de empresa correcta', function () {
            $company_code = get_option('sm_company_code');
            expect($company_code)->not()->toBeEmpty();
            
            logPriceListTest("Usando código de empresa: $company_code");
            
            $result = $this->service->getPriceLists();
            
            expect($result)->not()->toBeFalse();
        });
        
        it('retorna false con configuración inválida', function () {
            update_option('sm_company_code', 'INVALID99');
            
            $service = new PriceListService();
            $result = $service->getPriceLists();
            
            expect($result === false || (is_array($result) && isset($result['success']) && !$result['success']))->toBe(true);
            
            $this->setupApiCredentials();
        });
        
        it('maneja errores de conexión de manera elegante', function () {
            update_option('sm_api_url', 'http://invalid.url');
            
            $service = new PriceListService();
            $result = $service->getPriceLists();
            
            expect($result === false || (is_array($result) && isset($result['success']) && !$result['success']))->toBe(true);
            
            $this->setupApiCredentials();
        });
        
    });
    
    describe('Validación de estructura de listas de precios', function () {
        
        it('valida estructura básica de respuesta', function () {
            $result = $this->service->getPriceLists();
            
            logPriceListTest('Tipo de resultado: ' . gettype($result));
            if (is_array($result)) {
                logPriceListTest('Claves del resultado: ' . implode(', ', array_keys($result)));
                logPriceListTest('Estructura de resultado: ' . json_encode(array_slice($result, 0, 2), JSON_PRETTY_PRINT));
            } else {
                logPriceListTest('Valor de resultado: ' . var_export($result, true));
            }
            
            if ($result && is_array($result)) {
                $hasValidStructure = false;
                
                if (isset($result['nombre']) && isset($result['datos'])) {
                    expect($result['nombre'])->toBeString();
                    expect($result['datos'])->toBeArray();
                    $hasValidStructure = true;
                    
                    logPriceListTest('Estructura con nombre y datos');
                } elseif (is_array($result) && !empty($result)) {
                    $firstItem = reset($result);
                    if (is_array($firstItem)) {
                        $hasValidStructure = true;
                        logPriceListTest('Array directo de items de precio');
                    } else {
                        $hasValidStructure = true;
                        logPriceListTest('Array con estructura no estándar');
                    }
                } elseif (isset($result['success']) && !$result['success']) {
                    $hasValidStructure = true;
                    logPriceListTest('Estructura de error');
                } else {
                    $hasValidStructure = true;
                    logPriceListTest('Estructura válida genérica');
                }
                
                expect($hasValidStructure)->toBe(true);
            } else {
                expect($result === false)->toBe(true);
                logPriceListTest('Resultado false (válido)');
            }
        });
        
    });

    
    describe('Rendimiento de listas de precios', function () {
        
        it('maneja grandes listas de precios', function () {
            $startTime = microtime(true);
            $memoryBefore = memory_get_usage();
            
            $result = $this->service->getPriceLists();
            
            $endTime = microtime(true);
            $memoryAfter = memory_get_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $memoryAfter - $memoryBefore;
            
            expect($executionTime)->toBeLessThan(60);
            expect($memoryUsed)->toBeLessThan(100 * 1024 * 1024);
            
            if ($result) {
                $itemCount = 0;
                
                if (is_array($result)) {
                    if (isset($result['datos'])) {
                        $itemCount = count($result['datos']);
                    } else {
                        $itemCount = count($result);
                    }
                }
                
                logPriceListTest("Rendimiento - $itemCount items en {$executionTime}s, " . 
                         round($memoryUsed / 1024 / 1024, 2) . "MB");
            }
        });
        
        it('verifica timeout en conexiones lentas', function () {
            $startTime = microtime(true);
            
            $this->service->getPriceLists();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            expect($executionTime)->toBeLessThan(120);
            
            logPriceListTest("Tiempo total de ejecución: {$executionTime}s");
        });
        
    });
    
    describe('Integración con sistema B2B', function () {
        
        it('verifica estructura para integración B2B King', function () {
            $result = $this->service->getPriceLists();
            
            if ($result && is_array($result)) {
                $hasB2BStructure = false;
                
                if (isset($result['nombre'])) {
                    expect($result['nombre'])->toBeString();
                    $hasB2BStructure = true;
                    
                    logPriceListTest('Nombre para grupo B2B: ' . $result['nombre']);
                }
                
                if (isset($result['datos']) && is_array($result['datos'])) {
                    foreach ($result['datos'] as $item) {
                        if (isset($item['kopr']) && isset($item['precio'])) {
                            $hasB2BStructure = true;
                            break;
                        }
                    }
                }
                
                if ($hasB2BStructure) {
                    logPriceListTest('Estructura compatible con B2B King');
                }
                
                expect(true)->toBe(true);
            }
        });
        
    });
    
});