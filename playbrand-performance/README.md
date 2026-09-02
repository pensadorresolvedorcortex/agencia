# PlayBrand Performance

Plugin instalável para WordPress 6.3+/PHP 8.5+, desenhado para cPanel/LiteSpeed com WP Rocket. **Fail-safe, reversível e sem dependências externas.**

## Auditoria de entrada
O PHP-base `playbrand-performance-core.php` foi localizado e lido integralmente; trata-se de um MU-plugin monolítico (7.0.0-rc8) com cache próprio, reescrita HTML e várias sobreposições ao Rocket. `01.har` (280.945 bytes) e `01.png` também foram inspecionados. Esta implementação substitui a abordagem monolítica por módulos instaláveis; nenhum arquivo do tema, WP Rocket ou servidor é alterado.

Baseline observado no HAR: FCP ~1,74 s, LCP ~2,93 s, HTML ~205 KB, `play-brand.webp` ~3,69 MB, `agencia.mp4` ~5,62 MB, `refresh-her.webp` ~863 KB, fontes TreeThemes/Google e chamada `ipinfo.io` malsucedida.

## Checklist de entrega
Implementação e fail-safe do plugin: concluídos. Permanecem condicionados a staging: confirmar o elemento LCP, selecionar handles seguros para denylist, validar equivalência das fontes e medir as métricas Core Web Vitals antes/depois. Nenhuma dessas ações é aplicada automaticamente sem evidência para evitar regressões.

## Instalação e rollback
1. Compacte `playbrand-performance/` em ZIP e instale em **Plugins > Adicionar novo > Enviar plugin**.
2. Ative e abra **Ferramentas > PlayBrand Performance**. Mantenha **Dry-run** durante a validação; desative-o somente após testes.
3. Para rollback, marque “Ativo” off ou desative o plugin. Desinstalação remove apenas `pbpf_options` (`uninstall.php`). Não há cache próprio nem alterações em `.htaccess`/`wp-config.php`.

WP Rocket é detectado pela constante `WP_ROCKET_VERSION`; nenhuma função de cache, minificação, combinação, RUCSS, Brotli, lazy background ou fontes do Rocket é duplicada.

## Funcionalidades e limites
- Asset Manager usa filas/registries e denylist configurável; não remove dependências críticas e oferece dry-run.
- Fontes locais WOFF2 em `uploads/playbrand-performance/fonts/`, `font-display: swap`; não converte binários nem remove fontes remotas automaticamente.
- O módulo de vídeo está desativado por padrão nesta versão porque o vídeo foi removido da página; nenhuma rotina de `src`/`srcset` é registrada. Imagens recebem lazy/async. Arquivos >300 KB devem ser tratados manualmente com variantes WebP/AVIF e dimensões.
- Gate de terceiros sinaliza interação (`pbpf:interaction`); a chamada `ipinfo.io` deve ser removida/corrigida no script de origem (não há regex global HTML).
- Resource hints removem preconnect de origens não críticas (`fonts.googleapis.com`, `ipinfo.io`).
- Limpeza segura de emojis, oEmbed discovery, RSD e WLW apenas no front-end público.

## Matriz de riscos e validação
| Achado | Correção | Arquivo/hook | Risco | Validação |
|---|---|---|---|---|
| MP4 5,62 MB abaixo da dobra | `data-src`, sem autoplay | `Media::video`, `wp_video_shortcode` | widget customizado não usa shortcode | DevTools Network, mobile/desktop |
| Imagens 3,69/0,86 MB | lazy/async; gerar variantes manualmente | `Media::img` | LCP pode ser lazy indevidamente | confirmar LCP e `fetchpriority` |
| Google/TreeThemes fonts | permitir WOFF2 local | `Fonts::css` | fallback tipográfico | comparar visual e WOFF2 carregado |
| ipinfo.io falha | gate por interação; corrigir origem | `ThirdParty::guard` | funcionalidade dependente de IP | formulário/console |
| metadados dispensáveis | remoção seletiva | `Plugin::cleanup` | integrações que exigem RSD/oEmbed | editor, REST, feeds |

## Fora de escopo / ações manuais
Não há cache de página, minificação/combine, Remove Unused CSS, Delay JS genérico, compressão Brotli/Gzip, browser cache, recodificação de mídia ou descarregamento heurístico agressivo. Gere `play-brand.webp` e `refresh-her.webp` responsivos (WebP/AVIF), comprima `agencia.mp4` em H.264/WebM com poster, hospede fontes licenciadas e substitua o ícone WhatsApp externo por SVG local. Meça Lighthouse/GTmetrix antes/depois (requests, bytes, FCP/LCP/CLS/INP/TBT/TTI); não presuma ganhos.

## Variante MU-plugin
Para ambientes onde o código deve ser sempre carregado, copie `playbrand-performance-mu/playbrand-performance.php` para `wp-content/mu-plugins/`. O loader inclui a mesma implementação instalada em `wp-content/plugins/playbrand-performance/`; portanto não existem duas cópias de lógica nem cache concorrente. O plugin normal continua necessário para a tela de configurações. Remover o loader faz rollback imediato.

### Modo seguro
Desmarque **Ativo** na tela administrativa (ou defina `pbpf_options[enabled]` como `0` via WP-CLI) para desligar todos os módulos sem desinstalar. Mantenha `dry_run=1` até validar cada rota.

Em uma emergência, defina `PBPF_SAFE_MODE` como `true` no `wp-config.php` antes do carregamento dos plugins. Isso mantém a tela administrativa disponível, mas impede todos os módulos front-end; remova a constante após a investigação.
