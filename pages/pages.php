<?php

function add_socomarca_menu() {
    add_menu_page(
        'Socomarca', // Título de la página
        'Socomarca', // Texto del menú
        'manage_options', // Capacidad requerida
        'socomarca', // Slug del menú
        'render_socomarca_page', // Función que renderiza la página
        'dashicons-admin-generic', // Icono
        30 // Posición en el menú
    );

    add_submenu_page(
        'socomarca', // Slug del menú padre
        'Configuración', // Título de la página
        'Configuración', // Texto del menú
        'manage_options', // Capacidad requerida
        'socomarca-configuration', // Slug del submenú
        'render_configuration_page' // Función que renderiza la página
    );
}

function render_socomarca_page() {
    echo '<div class="wrap">';
    echo '<h1>Socomarca</h1>';
    echo '<p>Bienvenido a la sección de Socomarca.</p>';
    echo '</div>';
}
