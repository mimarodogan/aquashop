(function () {
    'use strict';

    // FAQ ekleme/silme
    function initFaq() {
        var list = document.getElementById('faq-list');
        var tpl = document.getElementById('faq-template');
        var addBtn = document.getElementById('faq-add');
        if (!list || !tpl || !addBtn) return;

        function bindDel(scope) {
            scope.querySelectorAll('.faq-del').forEach(function (b) {
                b.onclick = function () { b.closest('.faq-row').remove(); };
            });
        }
        bindDel(list);
        addBtn.addEventListener('click', function () {
            var node = tpl.content.firstElementChild.cloneNode(true);
            list.appendChild(node);
            bindDel(node.parentNode);
        });
    }

    // Varyasyon yöneticisi
    function initVariations() {
        var toggle = document.getElementById('has-variations-toggle');
        var block = document.getElementById('variations-block');
        if (toggle && block) {
            toggle.addEventListener('change', function () {
                block.style.display = toggle.checked ? '' : 'none';
            });
        }

        var addBtn = document.getElementById('add-variation');
        var list = document.getElementById('variations-list');
        var tplEl = document.getElementById('variation-template');
        if (!addBtn || !list || !tplEl) return;

        var tpl = tplEl.innerHTML;
        var startIdx = parseInt(list.getAttribute('data-start-idx'), 10) || list.children.length;

        addBtn.addEventListener('click', function () {
            var html = tpl.replace(/__IDX__/g, startIdx);
            var div = document.createElement('div');
            div.innerHTML = html;
            list.appendChild(div.firstElementChild);
            startIdx++;
        });
    }

    // Quill rich text editor — açıklama
    function initQuill() {
        var holder = document.getElementById('desc-editor');
        var hidden = document.getElementById('desc-textarea');
        if (!holder || !hidden || typeof Quill === 'undefined') return;

        // Eski düz metin kayıtları için: HTML etiketi içermiyorsa <p> ile sarmala
        var initial = holder.innerHTML.trim();
        if (initial && initial.indexOf('<') === -1) {
            holder.innerHTML = '<p>' + initial.replace(/\n/g, '<br>') + '</p>';
        }

        var quill = window.__productQuill = new Quill(holder, {
            theme: 'snow',
            placeholder: 'Ürün hakkında detaylı bilgi yazın…',
            modules: {
                toolbar: [
                    [{ header: [2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'blockquote'],
                    ['clean']
                ]
            }
        });

        var form = holder.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                var html = quill.root.innerHTML;
                if (html === '<p><br></p>') html = '';
                hidden.value = html;
            });
        }
    }

    function init() {
        initFaq();
        initVariations();
        initQuill();
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
