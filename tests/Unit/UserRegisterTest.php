<?php

use Socomarca\RandomERP\Ajax\UserRegisterAjaxHandler;

describe('UserRegisterAjaxHandler - Validación de RUT', function () {

    beforeEach(function () {
        $this->handler = new UserRegisterAjaxHandler();
        
        // Use reflection to make the private validateRut method accessible
        $this->reflection = new ReflectionClass(UserRegisterAjaxHandler::class);
        $this->method = $this->reflection->getMethod('validateRut');
        $this->method->setAccessible(true);
    });

    it('valida correctamente RUTs chilenos válidos', function () {
        $validRuts = [
            '19.123.456-0',
            '19123456-0',
            '191234560',
            '12.345.678-5',
            '12345678-5',
            '8.123.456-k',
            '8123456-K',
            '20.000.000-4',
            '9.999.999-3',
        ];

        foreach ($validRuts as $rut) {
            $isValid = $this->method->invokeArgs($this->handler, [$rut]);
            expect($isValid)->toBeTrue("El RUT {$rut} debería ser válido.");
        }
    });

    it('identifica correctamente RUTs chilenos inválidos', function () {
        $invalidRuts = [
            '19.123.456-9', // Wrong check digit
            '12.345.678-9', // Wrong check digit
            '1234567-89',   // Malformed
            'abcdefgh-k',   // Non-numeric
            '',             // Empty
            'k',            // Short
            '12.34-5',      // Short
        ];

        foreach ($invalidRuts as $rut) {
            $isValid = $this->method->invokeArgs($this->handler, [$rut]);
            expect($isValid)->toBeFalse("El RUT {$rut} debería ser inválido.");
        }
    });

});
