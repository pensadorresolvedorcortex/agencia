<?php
/** Curated, non-external demonstration records. */
declare(strict_types=1);

$items = [
    ['Tecnologia e Inovação Brasil', 'Tecnologia'], ['Vagas Remotas e Freelancers', 'Empregos'],
    ['Concursos Públicos em Foco', 'Concursos'], ['Inglês na Prática Diária', 'Educação'],
    ['Programadores Front-end BR', 'Tecnologia'], ['Empreendedores em Ação', 'Empreendedorismo'],
    ['Achadinhos e Ofertas Online', 'Compra e Venda'], ['Receitas Rápidas em Família', 'Receitas'],
    ['Viagens Econômicas Brasil', 'Viagem e Turismo'], ['Fotografia Mobile Criativa', 'Fotografia'],
    ['Clube de Leitura Atual', 'Livros'], ['Cinema e Séries em Debate', 'Filmes e Séries'],
    ['Pets, Cuidados e Adoção', 'Pets'], ['Corrida e Vida Ativa', 'Esportes'],
    ['Futebol entre Amigos', 'Futebol'], ['Música Brasileira Independente', 'Música'],
    ['Design e Criatividade Digital', 'Design'], ['Marketing para Pequenos Negócios', 'Marketing'],
    ['Networking Profissional Brasil', 'Profissões'], ['Carros, Manutenção e Dicas', 'Carros e Motos'],
    ['Motos e Estradas do Brasil', 'Carros e Motos'], ['Jardinagem Dentro de Casa', 'Sustentabilidade'],
    ['Sustentabilidade no Dia a Dia', 'Sustentabilidade'], ['Pais e Mães Conectados', 'Amizade'],
    ['Comunidade Local São Paulo', 'Cidades'], ['Games Cooperativos Brasil', 'Games e Jogos'],
    ['Artesanato e Produtos Autorais', 'Artesanato'], ['Finanças Pessoais Organizadas', 'Educação Financeira'],
    ['Estudos Universitários em Rede', 'Educação'], ['Notícias de Tecnologia e Apps', 'Tecnologia'],
    ['Figurinhas Criativas Brasil', 'Figurinhas e Stickers'], ['Anime e Cultura Japonesa', 'Desenhos e Animes'],
    ['Moda Consciente e Beleza', 'Moda e Beleza'], ['Eventos e Feiras do Brasil', 'Eventos'],
    ['Imóveis e Moradia Colaborativa', 'Imobiliária'], ['Memes e Humor Brasileiro', 'Memes e Humor'],
    ['Vídeos e Criadores Independentes', 'Vídeos'], ['Mundo das Redes Sociais', 'Redes Sociais'],
    ['Frases que Inspiram', 'Frases e Mensagens'], ['Comunidade de Música e Arte', 'Artesanato'],
];

return array_map(
    static fn(array $item): array => [
        'title' => $item[0],
        'category' => $item[1],
        'description' => 'Conteúdo demonstrativo criado para apresentar o catálogo, as categorias e a experiência visual do Portal Grupos WhatsApp.',
    ],
    $items
);
