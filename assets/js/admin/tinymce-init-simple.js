/* TinyMCE init — newsletter campaigns (simplified toolbar, textarea#body) */
(function () {
    'use strict';
    if (typeof tinymce === 'undefined') return;
    if (!document.querySelector('textarea#body')) return;

    tinymce.init({
        selector: 'textarea#body',
        height: 420,
        menubar: false,
        plugins: 'advlist autolink lists link image table code preview',
        toolbar: 'undo redo | bold italic underline | bullist numlist | alignleft aligncenter alignright | link image | code preview',
        language: 'tr',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@latest/langs7/tr.js',
        branding: false,
        promotion: false,
        relative_urls: false
    });
})();
