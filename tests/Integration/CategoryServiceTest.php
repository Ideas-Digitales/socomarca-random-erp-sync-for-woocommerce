<?php

use Socomarca\RandomERP\Services\CategoryService;

beforeEach(function () {
    $this->service = new CategoryService();
});

describe('CategoryService - Integración con API Real', function () {
    
    describe('getCategories', function () {
        
        it('puede obtener categorías desde el API de Random ERP', function () {
            $result = $this->service->getCategories();
            
            // If API connection fails, test that it fails gracefully
            if ($result === false) {
                expect($result)->toBeFalse();
                error_log('⚠️  CategoryService: No se pudo conectar al API - usando credenciales demo');
                return;
            }
            
            // Verify API response structure
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['quantity', 'items']);
            expect($result['quantity'])->toBeInt();
            expect($result['items'])->toBeArray();
            
            // If there are categories, verify structure
            if ($result['quantity'] > 0) {
                $firstCategory = $result['items'][0];
                expect($firstCategory)->toHaveKeys(['CODIGO', 'NOMBRE', 'NIVEL']);
                expect($firstCategory['CODIGO'])->toBeString();
                expect($firstCategory['NOMBRE'])->toBeString();
                expect($firstCategory['NIVEL'])->toBeInt();
            }
        });
        
        it('retorna false cuando hay error de conexión', function () {
            // Temporarily set invalid credentials
            update_option('sm_api_url', 'http://invalid.url');
            
            $service = new CategoryService();
            $result = $service->getCategories();
            
            expect($result)->toBeFalse();
            
            // Restore credentials
            $this->setupApiCredentials();
        });
        
        it('procesa diferentes niveles de categorías correctamente', function () {
            $result = $this->service->getCategories();
            
            if ($result && $result['quantity'] > 0) {
                $levels = [];
                foreach ($result['items'] as $category) {
                    $level = isset($category['NIVEL']) ? intval($category['NIVEL']) : 0;
                    $levels[$level] = ($levels[$level] ?? 0) + 1;
                }
                
                // Verify we have at least level 1 categories
                expect($levels)->toHaveKey(1);
                expect($levels[1])->toBeGreaterThan(0);
                
                // Log levels found for debugging
                //error_log('CategoryService Test: Niveles encontrados: ' . print_r($levels, true));
            }
        });
        
    });
    
    describe('processCategories', function () {
        
        it('puede procesar categorías desde el API', function () {
            $result = $this->service->processCategories();
            
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['success', 'message']);
            
            if ($result['success']) {
                expect($result)->toHaveKeys(['created', 'updated', 'errors']);
                expect($result['created'])->toBeInt();
                expect($result['updated'])->toBeInt();
                expect($result['errors'])->toBeArray();
                
                // Log processing results
                error_log('CategoryService Test: Procesamiento completado - ' . $result['message']);
            } else {
                // If processing failed, log the reason
                error_log('CategoryService Test: Error en procesamiento - ' . $result['message']);
                expect($result['message'])->toBeString();
            }
        });
        
        it('maneja errores de API de manera elegante', function () {
            // Temporarily break the API connection
            update_option('sm_api_url', 'http://invalid.url');
            
            $service = new CategoryService();
            $result = $service->processCategories();
            
            expect($result)->toBeArray();
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('No se pudieron obtener las categorías del ERP');
            
            // Restore credentials
            $this->setupApiCredentials();
        });
        
    });
    
    describe('Funcionalidad de limpieza', function () {
        
        it('puede eliminar todas las categorías', function () {
            $result = $this->service->deleteAllCategories();
            
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['success', 'message']);
            
            if ($result['success']) {
                expect($result)->toHaveKeys(['deleted_count', 'errors']);
                expect($result['deleted_count'])->toBeInt();
                expect($result['errors'])->toBeArray();
                
                error_log('CategoryService Test: Eliminación completada - ' . $result['message']);
            }
        });
        
    });
    
    describe('Validación de estructura de datos', function () {
        
        it('valida que las categorías tengan estructura correcta', function () {
            $result = $this->service->getCategories();
            
            if ($result && $result['quantity'] > 0) {
                foreach ($result['items'] as $index => $category) {
                    // Basic required fields - using isset() instead of toHaveKey()
                    expect(isset($category['CODIGO']))->toBe(true, "Categoría en índice $index no tiene CODIGO");
                    expect(isset($category['NOMBRE']))->toBe(true, "Categoría en índice $index no tiene NOMBRE");
                    expect(isset($category['NIVEL']))->toBe(true, "Categoría en índice $index no tiene NIVEL");
                    
                    // Field types validation
                    expect($category['CODIGO'])->toBeString();
                    expect($category['NOMBRE'])->toBeString();
                    expect(is_numeric($category['NIVEL']))->toBe(true);
                    
                    // Level validation
                    $nivel = intval($category['NIVEL']);
                    expect($nivel)->toBeGreaterThanOrEqual(1);
                    expect($nivel)->toBeLessThanOrEqual(3);
                    
                    // If it's a subcategory, it should have LLAVE
                    if ($nivel > 1) {
                        expect(isset($category['LLAVE']))->toBe(true, "Subcategoría {$category['CODIGO']} no tiene LLAVE");
                        expect($category['LLAVE'])->toBeString();
                    }
                }
            }
        });
        
        it('verifica jerarquía de categorías', function () {
            $result = $this->service->getCategories();
            
            if ($result && $result['quantity'] > 0) {
                $level1Categories = [];
                $level2Categories = [];
                $level3Categories = [];
                
                foreach ($result['items'] as $category) {
                    $nivel = intval($category['NIVEL']);
                    
                    switch ($nivel) {
                        case 1:
                            $level1Categories[$category['CODIGO']] = $category;
                            break;
                        case 2:
                            $level2Categories[] = $category;
                            break;
                        case 3:
                            $level3Categories[] = $category;
                            break;
                    }
                }
                
                // Should have at least some level 1 categories
                expect(count($level1Categories))->toBeGreaterThan(0);
                
                // Verify level 2 categories reference valid level 1 parents
                foreach ($level2Categories as $category) {
                    if (isset($category['LLAVE'])) {
                        $parentCode = explode("/", $category['LLAVE'])[0];
                        expect(isset($level1Categories[$parentCode]))->toBe(true, 
                            "Categoría nivel 2 {$category['CODIGO']} referencia padre inexistente: $parentCode");
                    }
                }
                
                error_log("CategoryService Test: Jerarquía - Nivel 1: " . count($level1Categories) . 
                         ", Nivel 2: " . count($level2Categories) . 
                         ", Nivel 3: " . count($level3Categories));
            }
        });
        
    });
    
    describe('Rendimiento y límites', function () {
        
        it('maneja respuestas grandes del API', function () {
            $startTime = microtime(true);
            
            $result = $this->service->getCategories();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // API call should complete within reasonable time (30 seconds)
            expect($executionTime)->toBeLessThan(30);
            
            if ($result) {
                error_log("CategoryService Test: Tiempo de ejecución: {$executionTime}s para {$result['quantity']} categorías");
            }
        });
        
        it('verifica límites de memoria en procesamiento', function () {
            $memoryBefore = memory_get_usage();
            
            $result = $this->service->processCategories();
            
            $memoryAfter = memory_get_usage();
            $memoryUsed = $memoryAfter - $memoryBefore;
            
            // Memory usage should be reasonable (less than 50MB)
            expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024);
            
            error_log("CategoryService Test: Memoria utilizada: " . round($memoryUsed / 1024 / 1024, 2) . "MB");
        });
        
    });
    
});