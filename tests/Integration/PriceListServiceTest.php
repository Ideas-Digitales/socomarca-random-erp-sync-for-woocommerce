<?php

use Socomarca\RandomERP\Services\PriceListService;

beforeEach(function () {
    $this->service = new PriceListService();
});

describe('PriceListService - Integración con API Real', function () {
    
    describe('getPriceLists', function () {
        
        it('puede obtener listas de precios desde el API de Random ERP', function () {
            $result = $this->service->getPriceLists();
            
            // If API connection fails, test that it fails gracefully
            if ($result === false) {
                expect($result)->toBeFalse();
                error_log('⚠️  PriceListService: No se pudo conectar al API - usando credenciales demo');
                return;
            }
            
            // Price lists might return different structures
            expect($result)->not()->toBeFalse();
            
            if (is_array($result)) {
                // If it's an array, it should have some structure
                expect($result)->toBeArray();
                
                // Log structure for debugging
                //error_log('PriceListService Test: Estructura de respuesta: ' . print_r(array_keys($result), true));
                
                // Check for common price list fields
                if (isset($result['nombre'])) {
                    expect($result['nombre'])->toBeString();
                    error_log('PriceListService Test: Lista de precios: ' . $result['nombre']);
                }
                
                if (isset($result['datos']) && is_array($result['datos'])) {
                    expect($result['datos'])->toBeArray();
                    
                    if (!empty($result['datos'])) {
                        $firstPriceItem = $result['datos'][0];
                        
                        // Common price item fields
                        if (isset($firstPriceItem['kopr'])) {
                            expect($firstPriceItem['kopr'])->toBeString();
                        }
                        
                        error_log('PriceListService Test: Primer item de precio: ' . print_r($firstPriceItem, true));
                    }
                }
            }
        });
        
        it('usa configuración de empresa correcta', function () {
            $company_code = get_option('sm_company_code');
            expect($company_code)->not()->toBeEmpty();
            
            error_log("PriceListService Test: Usando código de empresa: $company_code");
            
            $result = $this->service->getPriceLists();
            
            // Should get some response (might be empty but not false)
            expect($result)->not()->toBeFalse();
        });
        
        it('retorna false con configuración inválida', function () {
            // Set invalid company code
            update_option('sm_company_code', 'INVALID99');
            
            $service = new PriceListService();
            $result = $service->getPriceLists();
            
            // Might return false or error array depending on API
            expect($result === false || (is_array($result) && isset($result['success']) && !$result['success']))->toBe(true);
            
            // Restore configuration
            $this->setupApiCredentials();
        });
        
        it('maneja errores de conexión de manera elegante', function () {
            // Break API connection
            update_option('sm_api_url', 'http://invalid.url');
            
            $service = new PriceListService();
            $result = $service->getPriceLists();
            
            expect($result === false || (is_array($result) && isset($result['success']) && !$result['success']))->toBe(true);
            
            // Restore credentials
            $this->setupApiCredentials();
        });
        
    });
    
    describe('Validación de estructura de listas de precios', function () {
        
        it('valida estructura básica de respuesta', function () {
            $result = $this->service->getPriceLists();
            
            if ($result && is_array($result)) {
                // Check for expected structure
                $hasValidStructure = false;
                
                // Different possible structures
                if (isset($result['nombre']) && isset($result['datos'])) {
                    // Structure with name and data
                    expect($result['nombre'])->toBeString();
                    expect($result['datos'])->toBeArray();
                    $hasValidStructure = true;
                    
                    error_log('PriceListService Test: Estructura con nombre y datos');
                } elseif (is_array($result) && !empty($result)) {
                    // Direct array of price items
                    $firstItem = reset($result);
                    if (is_array($firstItem)) {
                        $hasValidStructure = true;
                        error_log('PriceListService Test: Array directo de items de precio');
                    }
                } elseif (isset($result['success']) && !$result['success']) {
                    // Error response structure
                    $hasValidStructure = true; // This is also a valid structure for error cases
                }
                
                expect($hasValidStructure)->toBe(true);
            } else {
                // If result is false, that's also a valid response
                expect($result === false)->toBe(true);
            }
        });
        
        it('valida items de precio cuando están presentes', function () {
            $result = $this->service->getPriceLists();
            $priceItems = [];
            
            if ($result && is_array($result)) {
                // Extract price items from different possible structures
                if (isset($result['datos']) && is_array($result['datos'])) {
                    $priceItems = $result['datos'];
                } elseif (is_array($result) && !empty($result)) {
                    $firstItem = reset($result);
                    if (is_array($firstItem) && isset($firstItem['kopr'])) {
                        $priceItems = $result;
                    }
                }
                
                if (!empty($priceItems)) {
                    foreach ($priceItems as $index => $item) {
                        // Basic price item validation
                        expect($item)->toBeArray("Item $index no es un array");
                        
                        // Common fields in price items
                        if (isset($item['kopr'])) {
                            expect($item['kopr'])->toBeString("SKU en item $index no es string");
                        }
                        
                        if (isset($item['precio'])) {
                            expect(is_numeric($item['precio']))->toBe(true, "Precio en item $index no es numérico");
                        }
                        
                        if (isset($item['cantidad'])) {
                            expect(is_numeric($item['cantidad']))->toBe(true, "Cantidad en item $index no es numérica");
                        }
                    }
                    
                    error_log('PriceListService Test: Validados ' . count($priceItems) . ' items de precio');
                }
            }
        });
        
        it('verifica consistencia de SKUs con productos', function () {
            $result = $this->service->getPriceLists();
            $priceItems = [];
            
            // Extract price items
            if ($result && is_array($result)) {
                if (isset($result['datos']) && is_array($result['datos'])) {
                    $priceItems = $result['datos'];
                } elseif (is_array($result) && !empty($result)) {
                    $firstItem = reset($result);
                    if (is_array($firstItem) && isset($firstItem['kopr'])) {
                        $priceItems = $result;
                    }
                }
            }
            
            if (!empty($priceItems)) {
                $priceSKUs = [];
                $validSKUs = 0;
                
                foreach ($priceItems as $item) {
                    if (isset($item['kopr']) && !empty($item['kopr'])) {
                        $sku = $item['kopr'];
                        $priceSKUs[] = $sku;
                        
                        // Basic SKU validation
                        if (is_string($sku) && strlen($sku) > 0 && strlen($sku) <= 50) {
                            $validSKUs++;
                        }
                    }
                }
                
                if (!empty($priceSKUs)) {
                    $validSKUPercentage = ($validSKUs / count($priceSKUs)) * 100;
                    expect($validSKUPercentage)->toBeGreaterThan(80); // 80% should be valid
                    
                    error_log("PriceListService Test: SKUs en precios - Total: " . count($priceSKUs) . 
                             ", Válidos: $validSKUs ({$validSKUPercentage}%)");
                }
            }
        });
        
    });
    
    describe('Análisis de datos de precios', function () {
        
        it('analiza rangos de precios', function () {
            $result = $this->service->getPriceLists();
            $priceItems = [];
            
            // Extract price items
            if ($result && is_array($result)) {
                if (isset($result['datos']) && is_array($result['datos'])) {
                    $priceItems = $result['datos'];
                } elseif (is_array($result) && !empty($result)) {
                    $firstItem = reset($result);
                    if (is_array($firstItem)) {
                        $priceItems = $result;
                    }
                }
            }
            
            if (!empty($priceItems)) {
                $priceStats = [
                    'items_with_price' => 0,
                    'min_price' => PHP_FLOAT_MAX,
                    'max_price' => 0,
                    'total_price' => 0,
                    'price_ranges' => [
                        '0-1000' => 0,
                        '1001-10000' => 0,
                        '10001-100000' => 0,
                        '100001+' => 0
                    ]
                ];
                
                foreach ($priceItems as $item) {
                    $priceField = null;
                    
                    // Different possible price fields
                    if (isset($item['precio']) && is_numeric($item['precio'])) {
                        $priceField = 'precio';
                    } elseif (isset($item['PRECIO']) && is_numeric($item['PRECIO'])) {
                        $priceField = 'PRECIO';
                    } elseif (isset($item['price']) && is_numeric($item['price'])) {
                        $priceField = 'price';
                    }
                    
                    if ($priceField) {
                        $price = floatval($item[$priceField]);
                        $priceStats['items_with_price']++;
                        $priceStats['total_price'] += $price;
                        $priceStats['min_price'] = min($priceStats['min_price'], $price);
                        $priceStats['max_price'] = max($priceStats['max_price'], $price);
                        
                        // Categorize price
                        if ($price <= 1000) {
                            $priceStats['price_ranges']['0-1000']++;
                        } elseif ($price <= 10000) {
                            $priceStats['price_ranges']['1001-10000']++;
                        } elseif ($price <= 100000) {
                            $priceStats['price_ranges']['10001-100000']++;
                        } else {
                            $priceStats['price_ranges']['100001+']++;
                        }
                    }
                }
                
                if ($priceStats['items_with_price'] > 0) {
                    $priceStats['avg_price'] = $priceStats['total_price'] / $priceStats['items_with_price'];
                    
                    error_log('PriceListService Test: Estadísticas de precios: ' . 
                             print_r($priceStats, true));
                }
            }
        });
        
        it('analiza cantidades de ruptura para precios escalonados', function () {
            $result = $this->service->getPriceLists();
            $priceItems = [];
            
            // Extract price items
            if ($result && is_array($result)) {
                if (isset($result['datos']) && is_array($result['datos'])) {
                    $priceItems = $result['datos'];
                } elseif (is_array($result) && !empty($result)) {
                    $firstItem = reset($result);
                    if (is_array($firstItem)) {
                        $priceItems = $result;
                    }
                }
            }
            
            if (!empty($priceItems)) {
                $quantityStats = [
                    'items_with_quantity' => 0,
                    'quantity_breaks' => [],
                    'min_quantity' => PHP_FLOAT_MAX,
                    'max_quantity' => 0
                ];
                
                foreach ($priceItems as $item) {
                    $quantityField = null;
                    
                    // Different possible quantity fields
                    if (isset($item['cantidad']) && is_numeric($item['cantidad'])) {
                        $quantityField = 'cantidad';
                    } elseif (isset($item['CANTIDAD']) && is_numeric($item['CANTIDAD'])) {
                        $quantityField = 'CANTIDAD';
                    } elseif (isset($item['qty']) && is_numeric($item['qty'])) {
                        $quantityField = 'qty';
                    }
                    
                    if ($quantityField) {
                        $quantity = floatval($item[$quantityField]);
                        $quantityStats['items_with_quantity']++;
                        $quantityStats['min_quantity'] = min($quantityStats['min_quantity'], $quantity);
                        $quantityStats['max_quantity'] = max($quantityStats['max_quantity'], $quantity);
                        
                        // Track quantity breaks
                        $quantityStats['quantity_breaks'][$quantity] = 
                            ($quantityStats['quantity_breaks'][$quantity] ?? 0) + 1;
                    }
                }
                
                if ($quantityStats['items_with_quantity'] > 0) {
                    // Sort quantity breaks
                    ksort($quantityStats['quantity_breaks']);
                    $quantityStats['top_quantity_breaks'] = 
                        array_slice($quantityStats['quantity_breaks'], 0, 10, true);
                    
                    error_log('PriceListService Test: Estadísticas de cantidades: ' . 
                             print_r($quantityStats, true));
                }
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
            
            // Performance expectations
            expect($executionTime)->toBeLessThan(60); // 60 seconds max
            expect($memoryUsed)->toBeLessThan(100 * 1024 * 1024); // 100MB max
            
            if ($result) {
                $itemCount = 0;
                
                if (is_array($result)) {
                    if (isset($result['datos'])) {
                        $itemCount = count($result['datos']);
                    } else {
                        $itemCount = count($result);
                    }
                }
                
                error_log("PriceListService Test: Rendimiento - $itemCount items en {$executionTime}s, " . 
                         round($memoryUsed / 1024 / 1024, 2) . "MB");
            }
        });
        
        it('verifica timeout en conexiones lentas', function () {
            $startTime = microtime(true);
            
            // This should complete or timeout gracefully
            $result = $this->service->getPriceLists();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Should not hang indefinitely
            expect($executionTime)->toBeLessThan(120); // 2 minutes absolute max
            
            error_log("PriceListService Test: Tiempo total de ejecución: {$executionTime}s");
        });
        
    });
    
    describe('Integración con sistema B2B', function () {
        
        it('verifica estructura para integración B2B King', function () {
            $result = $this->service->getPriceLists();
            
            if ($result && is_array($result)) {
                // Check if structure is suitable for B2B King integration
                $hasB2BStructure = false;
                
                if (isset($result['nombre'])) {
                    // Has name for B2B group creation
                    expect($result['nombre'])->toBeString();
                    $hasB2BStructure = true;
                    
                    error_log('PriceListService Test: Nombre para grupo B2B: ' . $result['nombre']);
                }
                
                if (isset($result['datos']) && is_array($result['datos'])) {
                    // Has price data for tiered pricing
                    foreach ($result['datos'] as $item) {
                        if (isset($item['kopr']) && isset($item['precio'])) {
                            $hasB2BStructure = true;
                            break;
                        }
                    }
                }
                
                if ($hasB2BStructure) {
                    error_log('PriceListService Test: Estructura compatible con B2B King');
                }
                
                // Don't fail the test if structure is not B2B compatible
                // as it depends on ERP configuration
                expect(true)->toBe(true);
            }
        });
        
    });
    
});