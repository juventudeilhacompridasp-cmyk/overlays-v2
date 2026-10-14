// Lista dos prints da landing page: cada um é uma tela REAL do produto renderizada com dados fictícios (nenhum dado de
// cliente). `name` vira `public/marketing/{name}.webp`; os textos da galeria ficam em LandingContent::gallery (PHP) e o
// teste Pest confere que os dois listam os mesmos nomes. Largura/altura são do navegador; a saída é sempre 1280 x 720.
export const OUT_WIDTH = 1280;
export const OUT_HEIGHT = 720;

export const SHOTS = [
    { name: 'placar', width: 1920, height: 1080 },
    { name: 'gol', width: 1920, height: 1080 },
    { name: 'escalacao', width: 1920, height: 1080 },
    { name: 'tabela', width: 1920, height: 1080 },
    { name: 'penaltis', width: 1920, height: 1080 },
    { name: 'patrocinio', width: 1920, height: 1080 },
    { name: 'operador', width: 1366, height: 768 },
];
