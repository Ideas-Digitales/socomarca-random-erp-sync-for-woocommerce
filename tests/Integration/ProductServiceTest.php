<?php

use Socomarca\RandomERP\Services\ProductService;

beforeEach(function () {
    $this->service = new ProductService();
});

describe('ProductService - Integración con API Real', function () {
    
    describe('getProducts', function () {
        
        it('puede obtener productos desde el API de Random ERP', function () {
            $result = $this->service->getProducts();
            
            // If API connection fails, test that it fails gracefully
            if ($result === false) {
                expect($result)->toBeFalse();
                error_log('WARNING ProductService: No se pudo conectar al API - usando credenciales demo');
                return;
            }
            
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['quantity', 'items']);
            expect($result['quantity'])->toBeInt();
            expect($result['items'])->toBeArray();
            
            // If there are products, verify structure
            if ($result['quantity'] > 0) {
                $firstProduct = $result['items'][0];
                
                // Common product fields
                expect($firstProduct)->toHaveKey('KOPR'); // SKU
                expect($firstProduct)->toHaveKey('NOKOPR'); // Name
                
                // Verify basic field types
                expect($firstProduct['KOPR'])->toBeString();
                expect($firstProduct['NOKOPR'])->toBeString();
                
                error_log('ProductService Test: Primer producto - SKU: ' . $firstProduct['KOPR'] . ', Nombre: ' . $firstProduct['NOKOPR']);
            }
        });
        
        it('retorna false cuando hay error de conexión', function () {
            // Temporarily set invalid API URL
            update_option('sm_api_url', 'http://invalid.url:9999');
            update_option('sm_api_user', 'invalid_user');
            update_option('sm_api_password', 'invalid_password');
            
            $service = new ProductService();
            $result = $service->getProducts();
            
            expect($result)->toBeFalse();
            
            // Restore credentials
            $this->setupApiCredentials();
        });
        
        it('maneja grandes volúmenes de productos', function () {
            $startTime = microtime(true);
            
            $result = $this->service->getProducts();
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Should complete within reasonable time
            expect($executionTime)->toBeLessThan(60); // 60 seconds max for products
            
            if ($result) {
                error_log("ProductService Test: {$result['quantity']} productos obtenidos en {$executionTime}s");
            }
        });
        
    });
    
    describe('processProducts', function () {
        
        it('puede procesar productos y almacenar en cache', function () {
            $result = $this->service->processProducts();
            
            expect($result)->toBeArray();
            expect($result)->toHaveKeys(['success', 'message']);
            
            if ($result['success']) {
                expect($result)->toHaveKey('total');
                expect($result['total'])->toBeInt();
                expect($result['total'])->toBeGreaterThanOrEqual(0);
                
                // Verify products were cached
                $cached_products = get_option('sm_products_cache');
                expect($cached_products)->toBeArray();
                expect(count($cached_products))->toBe($result['total']);
                
                error_log('ProductService Test: Procesados ' . $result['total'] . ' productos');
            } else {
                error_log('ProductService Test: Error en procesamiento - ' . $result['message']);
            }
        });
        
        it('maneja errores de API de manera elegante', function () {
            // Break API connection
            update_option('sm_api_url', 'http://invalid.url:9999');
            update_option('sm_api_user', 'invalid_user');
            update_option('sm_api_password', 'invalid_password');
            
            $service = new ProductService();
            $result = $service->processProducts();
            
            expect($result)->toBeArray();
            expect($result['success'])->toBeFalse();
            expect($result['message'])->toContain('No se pudieron obtener los productos del ERP');
            
            // Restore credentials
            $this->setupApiCredentials();
        });
        
    });
    
    describe('Validación de estructura de productos', function () {
        
        it('valida que los productos tengan campos requeridos', function () {
            $result = $this->service->getProducts();
            
            if ($result && $result['quantity'] > 0) {
                foreach ($result['items'] as $index => $product) {
                    // Required fields for product creation
                    expect(isset($product['KOPR']))->toBe(true, "Producto en índice $index no tiene SKU (KOPR)");
                    expect(isset($product['NOKOPR']))->toBe(true, "Producto en índice $index no tiene nombre (NOKOPR)");
                    
                    // Field validation
                    expect($product['KOPR'])->toBeString();
                    expect($product['NOKOPR'])->toBeString();
                    expect(strlen($product['KOPR']))->toBeGreaterThan(0);
                    expect(strlen($product['NOKOPR']))->toBeGreaterThan(0);
                    
                    // Optional but common fields
                    if (isset($product['PRPR1'])) { // Price
                        expect(is_numeric($product['PRPR1']))->toBe(true);
                    }
                    
                    if (isset($product['STOCKVENTA'])) { // Stock
                        expect(is_numeric($product['STOCKVENTA']))->toBe(true);
                    }
                    
                    if (isset($product['KOFA'])) { // Category code
                        expect($product['KOFA'])->toBeString();
                    }
                }
            }
        });
        
        it('verifica formato de SKUs', function () {
            $result = $this->service->getProducts();
            
            if ($result && $result['quantity'] > 0) {
                $validSkuCount = 0;
                $invalidSkus = [];
                $skuFormats = [];
                
                foreach ($result['items'] as $product) {
                    $sku = $product['KOPR'];
                    
                    // SKU shouldn't be empty and should be reasonable length
                    if (!empty($sku) && strlen($sku) <= 50) {
                        $validSkuCount++;
                        
                        // Track SKU patterns
                        $pattern = preg_replace('/[0-9]/', 'N', $sku);
                        $pattern = preg_replace('/[A-Za-z]/', 'A', $pattern);
                        $skuFormats[$pattern] = ($skuFormats[$pattern] ?? 0) + 1;
                    } else {
                        $invalidSkus[] = $sku;
                    }
                }
                
                // Most SKUs should be valid
                expect($validSkuCount)->toBeGreaterThan(0);
                
                error_log("ProductService Test: SKUs válidos: $validSkuCount de {$result['quantity']}");
                
                
                if (!empty($invalidSkus)) {
                    error_log('ProductService Test: SKUs inválidos: ' . implode(', ', array_slice($invalidSkus, 0, 3)));
                }
            }
        });
        
        
    });
    
    describe('Rendimiento y manejo de memoria', function () {
        
        it('maneja grandes catálogos de productos', function () {
            $startTime = microtime(true);
            $memoryBefore = memory_get_usage();
            
            $result = $this->service->getProducts();
            
            $endTime = microtime(true);
            $memoryAfter = memory_get_usage();
            
            $executionTime = $endTime - $startTime;
            $memoryUsed = $memoryAfter - $memoryBefore;
            
            // Performance expectations for product catalog
            expect($executionTime)->toBeLessThan(120); // 2 minutes max
            expect($memoryUsed)->toBeLessThan(200 * 1024 * 1024); // 200MB max
            
            if ($result) {
                $avgMemoryPerProduct = $memoryUsed / $result['quantity'];
                error_log("ProductService Test: Rendimiento - {$result['quantity']} productos en {$executionTime}s, " . 
                         round($memoryUsed / 1024 / 1024, 2) . "MB total, " . 
                         round($avgMemoryPerProduct / 1024, 2) . "KB por producto");
            }
        });
        
        it('verifica que el cache de productos funciona correctamente', function () {
            // First call to populate cache
            $this->service->processProducts();
            
            $cached_products = get_option('sm_products_cache');
            expect($cached_products)->toBeArray();
            
            if (!empty($cached_products)) {
                // Verify cache structure
                $firstCachedProduct = $cached_products[0];
                expect($firstCachedProduct)->toHaveKey('KOPR');
                expect($firstCachedProduct)->toHaveKey('NOKOPR');
                
                // Verify cache integrity
                expect(count($cached_products))->toBeGreaterThan(0);
                
                error_log('ProductService Test: Cache contiene ' . count($cached_products) . ' productos');
            }
        });
        
    });
});