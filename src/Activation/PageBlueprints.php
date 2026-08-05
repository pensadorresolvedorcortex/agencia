<?php
namespace PGW\Activation;

final class PageBlueprints {
    public function all(): array {
        return [
            'entrar'=>['title'=>'Entrar','content'=>'[pgw_entrar]'],
            'criar-conta'=>['title'=>'Criar Conta','content'=>'[pgw_criar_conta]'],
            'confirmar-codigo'=>['title'=>'Confirmar Código','content'=>'[pgw_confirmar_codigo]'],
            'recuperar-senha'=>['title'=>'Recuperar Senha','content'=>'[pgw_recuperar_senha]'],
            'minha-conta'=>['title'=>'Minha Conta','content'=>'[pgw_minha_conta]'],
            'meus-grupos'=>['title'=>'Meus Grupos','content'=>'[pgw_meus_grupos]'],
            'enviar-grupo'=>['title'=>'Enviar Grupo','content'=>'[pgw_enviar_grupo]'],
            'grupos'=>['title'=>'Grupos','content'=>'[pgw_mostrar_grupos]'],
            'categorias'=>['title'=>'Categorias','content'=>'[pgw_categorias]'],
            'buscar-grupos'=>['title'=>'Buscar Grupos','content'=>'[pgw_busca]'],
            'perfil-resumido'=>['title'=>'Perfil Resumido','content'=>'[pgw_perfil_resumido]'],
            'portal-grupos'=>['title'=>'Portal Grupos WhatsApp','content'=>"[pgw_header]\n[pgw_busca]\n[pgw_categorias limit=\"10\" show_more=\"1\"]\n[pgw_mostrar_grupos limit=\"30\" load_more=\"1\" load_amount=\"15\" columns=\"3\" featured_first=\"1\" showcase=\"1\"]\n[pgw_footer]"],
        ];
    }

    public function selected(array $slugs): array {
        return array_intersect_key($this->all(), array_fill_keys(array_map('strval', $slugs), true));
    }
}
