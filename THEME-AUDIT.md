# Auditoria do tema Brandberry

Fonte auditada: `brandberry.zip` fornecido em 2026-09-01. O ZIP foi extraído apenas para inspeção; o tema não foi alterado, conforme a regra de não editar tema/plugins de terceiros em produção.

## Achados prioritários

1. **Google Fonts duplicadas.** `app/core/enqueue.class.php` carrega `brandberry-fonts` com DM Sans e PT Serif em muitos pesos. `app/core/googlefonts.class.php` ainda pode carregar `brandberry-google-fonts` dinamicamente e força os pesos 400, 700 e 900. Antes de self-host, inventariar as famílias efetivamente renderizadas e manter o preconnect enquanto a origem externa continuar ativa.
2. **CustomEase global.** `CustomEase.min.js` é enfileirado em todas as páginas com dependência de jQuery, embora `assets/js/script.js` não referencie `CustomEase`. Validar Elementor/Animation Addons e então condicionar o handle `CustomEase` somente às rotas/widgets que o utilizam.
3. **JavaScript global faz trabalho contínuo.** `script.min.js` inclui watchdog de Swiper com `setInterval(..., 300)`, observadores/eventos e rotinas GSAP globais. Condicionar o watchdog à existência do seletor `.wcf--brand-slider-wrapper .swiper`, pausar em `document.hidden` e desligar quando não houver sliders reduz main-thread sem remover Swiper/GSAP.
4. **CSS global.** `master.min.css` (~93 KB) e `custom-icons.min.css` são carregados em todo front-end; `woo.css` (~45 KB) já é condicional. Medir cobertura por template e extrair CSS crítico apenas via ferramenta validada; não fazer regex ou RUCSS paralelo ao WP Rocket.
5. **Fontes legadas.** O pacote contém Icomoon EOT/TTF/WOFF e Font Awesome TTF/WOFF2. Preferir WOFF2 e confirmar quais glifos/classes aparecem antes de remover formatos ou arquivos.
6. **`functions.php` monolítico.** Há lógica de SEO, preload do hero, resource hints, Elementor, GSAP e performance no tema. Isso duplica responsabilidades do plugin e dificulta rollback. Migrar gradualmente as rotinas PlayBrand para o plugin, com flags, sem alterar o tema ativo de uma só vez.
7. **Preload do hero.** O tema emite dois preloads por media query. Confirmar que as URLs correspondem exatamente às imagens renderizadas e que apenas uma é baixada por viewport; preload incorreto compete com CSS/fontes e piora LCP.
8. **Vídeo removido.** Ainda existem referências genéricas de post-format vídeo e configuração `disable_mobile_bg_video`; elas não baixam o antigo MP4 por si só. A otimização de vídeo do PlayBrand Performance permanece desativada.

## Plano seguro

- Fase 1: coletar handles e cobertura por rota no staging; confirmar LCP e fontes computadas.
- Fase 2: condicionar `CustomEase`, CSS de blog/ícones e watchdog do slider apenas onde usados.
- Fase 3: self-host de WOFF2 equivalentes, atualizar CSS e só então remover Google Fonts/preconnects.
- Fase 4: validar Lighthouse mobile/desktop e regressões de menu, sliders, GSAP, formulários e Elementor.

## Não fazer

- Não remover jQuery, GSAP, ScrollTrigger, Swiper ou assets Elementor por nome/heurística.
- Não manter otimizações duplicadas no tema, WP Rocket e PlayBrand Performance.
- Não declarar ganho sem medição antes/depois na mesma rota, dispositivo e condições.
