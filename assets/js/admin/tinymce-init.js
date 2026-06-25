/* TinyMCE init — admin blog/post + newsletter campaigns */
(function () {
    'use strict';
    if (typeof tinymce === 'undefined') return;
    if (!document.querySelector('textarea#content')) return;

    tinymce.init({
        selector: 'textarea#content',
        height: 560,
        menubar: 'edit view insert format table',
        plugins: 'advlist autolink lists link image media table code preview wordcount autoresize searchreplace anchor codesample emoticons',
        toolbar: 'undo redo | blocks fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image youtube media table | blockquote hr codesample emoticons | removeformat | code preview fullscreen',
        toolbar_mode: 'sliding',
        content_style: "body{font-family:Inter,Arial,sans-serif;font-size:15px;line-height:1.7;background:#fff;color:#111}h1,h2,h3,h4{font-family:'Playfair Display',Georgia,serif}img,iframe{max-width:100%}",
        language: 'tr',
        language_url: 'https://cdn.jsdelivr.net/npm/tinymce-i18n@latest/langs7/tr.js',
        branding: false,
        promotion: false,
        image_caption: true,
        image_advtab: true,
        image_title: true,
        paste_data_images: true,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: true,
        images_upload_url: '../media/upload.php',
        automatic_uploads: true,
        images_reuse_filename: false,
        images_upload_credentials: true,

        // Video / iframe gömme
        media_live_embeds: true,
        media_alt_source: false,
        media_poster: true,
        extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen|loading|referrerpolicy|title]',
        media_url_resolver: function (data, resolve) {
            var html = '';
            var url = data.url;
            var ytMatch = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([\w-]{11})/);
            if (ytMatch) {
                var id = ytMatch[1];
                html = '<iframe width="640" height="360" src="https://www.youtube-nocookie.com/embed/' + id +
                    '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
                resolve({ html: html });
                return;
            }
            var vmMatch = url.match(/vimeo\.com\/(\d+)/);
            if (vmMatch) {
                html = '<iframe src="https://player.vimeo.com/video/' + vmMatch[1] +
                    '" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>';
                resolve({ html: html });
                return;
            }
            resolve({ html: '' });
        },

        // Hızlı YouTube butonu
        setup: function (editor) {
            editor.ui.registry.addButton('youtube', {
                text: 'YouTube',
                icon: 'embed',
                tooltip: 'YouTube videosu ekle',
                onAction: function () {
                    editor.windowManager.open({
                        title: 'YouTube Videosu Ekle',
                        body: {
                            type: 'panel', items: [
                                { type: 'input', name: 'url', label: 'YouTube URL veya Video ID' },
                                { type: 'input', name: 'caption', label: 'Açıklama (opsiyonel)' }
                            ]
                        },
                        buttons: [
                            { type: 'cancel', text: 'Vazgeç' },
                            { type: 'submit', text: 'Ekle', primary: true }
                        ],
                        onSubmit: function (api) {
                            var data = api.getData();
                            var v = (data.url || '').trim();
                            var m = v.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/))([\w-]{11})/);
                            var id = m ? m[1] : (v.length === 11 ? v : '');
                            if (!id) { editor.notificationManager.open({ text: 'Geçerli bir YouTube linki giriniz.', type: 'error' }); return; }
                            var iframe = '<figure class="video-embed"><iframe width="640" height="360" src="https://www.youtube-nocookie.com/embed/' + id +
                                '" title="YouTube video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>' +
                                (data.caption ? '<figcaption>' + data.caption + '</figcaption>' : '') + '</figure><p></p>';
                            editor.insertContent(iframe);
                            api.close();
                        }
                    });
                }
            });
        }
    });
})();
