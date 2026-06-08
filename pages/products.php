<?php
/**
 * Ürün listeleme sayfası — orchestrator.
 *  - Controller: core/controllers/products_listing.php
 *  - View:       pages/views/products-listing.view.php
 *
 * Controller AJAX isteğinde erkenden exit eder; bu durumda view yüklenmez.
 */
require __DIR__ . '/../core/controllers/products_listing.php';
require __DIR__ . '/views/products-listing.view.php';
