<?php
/**
 * Plugin Name: RMA Governança Upload Helper
 * Description: Detalha o upload de documentos por tipo na página de governança do dashboard.
 * Version: 1.0.0
 * Author: RMA
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $dashboard_path = (string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH);

    if ($request_path === '' || $dashboard_path === '' || strpos(untrailingslashit($request_path), untrailingslashit($dashboard_path)) !== 0) {
        return;
    }
    ?>
    <script>
    (function () {
        try {
            var params = new URLSearchParams(window.location.search || '');
            if ((params.get('ext') || '') !== 'rma-governanca-upload') return;

            var docMap = [
                ['ficha_inscricao', 'Ficha de inscrição cadastral'],
                ['comprovante_cnpj', 'Comprovante de CNPJ'],
                ['ata_fundacao', 'Ata de fundação'],
                ['ata_diretoria', 'Ata da diretoria atual'],
                ['estatuto', 'Estatuto e alterações'],
                ['relatorio_atividades', 'Relatório de atividades'],
                ['cartas_recomendacao', '2 cartas de recomendação']
            ];

            function ensureLegend() {
                if (document.getElementById('rma-governanca-legend')) return;
                var host = document.querySelector('.rma-gov-entity-wrap form, form.rma-gov-entity-table-wrap, .rma-gov-entity-wrap form.rma-gov-entity-table-wrap, form');
                if (!host) return;

                var box = document.createElement('div');
                box.id = 'rma-governanca-legend';
                box.style.border = '1px solid #dbe3ea';
                box.style.borderRadius = '10px';
                box.style.padding = '10px 12px';
                box.style.margin = '0 0 12px';
                box.style.background = '#f8fbff';
                box.innerHTML = '<strong style="display:block;margin-bottom:6px;">Tipos de documentos para upload</strong><ol style="margin:0 0 0 18px;padding:0;">'
                    + docMap.map(function (d) { return '<li style="margin:2px 0;">' + d[1] + '</li>'; }).join('')
                    + '</ol>';
                host.insertBefore(box, host.firstChild);
            }

            function normalizeDocumentTypeInput() {
                var textInput = document.querySelector('input[name="document_type"][type="text"]');
                if (!textInput || textInput.dataset.rmaDocTypeNormalized === '1') return;

                var select = document.createElement('select');
                select.name = 'document_type';
                select.required = true;
                select.style.width = '100%';
                select.style.maxWidth = '480px';
                select.style.border = '1px solid rgba(15,23,42,.2)';
                select.style.borderRadius = '10px';
                select.style.padding = '8px 10px';

                var first = document.createElement('option');
                first.value = '';
                first.textContent = 'Selecione o tipo de documento';
                select.appendChild(first);

                docMap.forEach(function (doc) {
                    var opt = document.createElement('option');
                    opt.value = doc[0];
                    opt.textContent = doc[1];
                    select.appendChild(opt);
                });

                var current = (textInput.value || '').trim().toLowerCase();
                if (current) {
                    var found = docMap.find(function (doc) {
                        return doc[0] === current || doc[1].toLowerCase() === current;
                    });
                    if (found) select.value = found[0];
                }

                textInput.parentNode.insertBefore(select, textInput);
                textInput.name = 'document_type_legacy';
                textInput.style.display = 'none';
                textInput.dataset.rmaDocTypeNormalized = '1';
            }

            function applyUploadHints() {
                var fileInput = document.querySelector('input[type="file"][name="entity_document_file"]');
                if (!fileInput || fileInput.dataset.rmaDocHintApplied === '1') return;

                var wrapper = fileInput.closest('p, .form-group, .rma-drop-item, .rma-dropzone, .upload-btn-wrapper, div') || fileInput.parentElement;
                if (!wrapper) return;

                var hint = document.createElement('p');
                hint.className = 'rma-doc-type-hint';
                hint.style.margin = '0 0 6px';
                hint.style.fontWeight = '600';
                hint.style.color = '#334155';
                hint.textContent = 'Escolha o tipo de documento na lista antes de enviar o arquivo.';
                wrapper.insertBefore(hint, wrapper.firstChild);

                fileInput.dataset.rmaDocHintApplied = '1';
            }

            function run() {
                ensureLegend();
                normalizeDocumentTypeInput();
                applyUploadHints();
            }

            run();
            var observer = new MutationObserver(run);
            observer.observe(document.body, {childList: true, subtree: true});
        } catch (e) {}
    })();
    </script>
    <?php
}, 999);
