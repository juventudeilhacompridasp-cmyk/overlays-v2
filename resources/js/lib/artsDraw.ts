// Desenho das artes (ADR 0009): executa as operações de `buildArt` num canvas 2D. A prévia e o PNG baixado usam este mesmo código.
import type { ArtStyle, FontKey, Op } from './arts';

/** Pilhas de fontes do sistema (sem download): trocar por fontes web embutidas é só mudar aqui. */
export const ART_FONTS: Record<FontKey, string> = {
    sans: '"Inter","Segoe UI","Helvetica Neue",Arial,system-ui,sans-serif',
    display: 'Impact,"Haettenschweiler","Arial Narrow Bold","Arial Black",sans-serif',
    condensed: '"Roboto Condensed","Oswald","Arial Narrow","Helvetica Neue",Arial,sans-serif',
    serif: 'Georgia,"Times New Roman",serif',
    mono: '"SF Mono",Menlo,Consolas,"Courier New",monospace',
    inter: '"Inter","Segoe UI",Arial,sans-serif',
    oswald: '"Oswald","Arial Narrow",Impact,sans-serif',
    bebas: '"Bebas Neue",Impact,"Arial Narrow",sans-serif',
    anton: '"Anton",Impact,"Arial Narrow",sans-serif',
    montserrat: '"Montserrat","Segoe UI",Arial,sans-serif',
    'roboto-condensed': '"Roboto Condensed","Arial Narrow",Arial,sans-serif',
    playfair: '"Playfair Display",Georgia,"Times New Roman",serif',
    marker: '"Permanent Marker","Comic Sans MS",cursive',
    barlow: '"Barlow Condensed","Arial Narrow",Arial,sans-serif',
    archivo: '"Archivo Black","Arial Black",Impact,sans-serif',
};

const WEIGHTS = { normal: 400, bold: 700, black: 900 } as const;

type Images = Map<string, HTMLImageElement>;

/** Carrega as imagens (data URI) de uma lista de operações. Imagem que falha é ignorada (a arte sai sem ela). */
export async function loadImages(ops: Op[], images: Images = new Map()): Promise<Images> {
    const sources = [...new Set(ops.flatMap((op) => (op.t === 'image' || op.t === 'bgimage' ? [op.src] : [])))].filter((src) => !images.has(src));

    await Promise.all(
        sources.map(
            (src) =>
                new Promise<void>((resolve) => {
                    const image = new Image();
                    image.onload = () => {
                        images.set(src, image);
                        resolve();
                    };
                    image.onerror = () => resolve();
                    image.src = src;
                }),
        ),
    );

    return images;
}

function rounded(ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, radius: number): void {
    const r = Math.max(0, Math.min(radius, w / 2, h / 2));
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

function paintBackground(ctx: CanvasRenderingContext2D, style: ArtStyle, W: number, H: number): void {
    const bg = style.background;

    if (bg.mode === 'gradient') {
        const angle = ((bg.angle - 90) * Math.PI) / 180;
        const cx = W / 2;
        const cy = H / 2;
        const half = Math.abs((W / 2) * Math.cos(angle)) + Math.abs((H / 2) * Math.sin(angle));
        const gradient = ctx.createLinearGradient(cx - Math.cos(angle) * half, cy - Math.sin(angle) * half, cx + Math.cos(angle) * half, cy + Math.sin(angle) * half);
        gradient.addColorStop(0, bg.color);
        gradient.addColorStop(1, bg.color2);
        ctx.fillStyle = gradient;
    } else {
        ctx.fillStyle = bg.color;
    }

    ctx.fillRect(0, 0, W, H);
}

/** Textura (listras/grade/pontos) e sombra de baixo para cima: por cima da imagem e da película. */
function paintTexture(ctx: CanvasRenderingContext2D, style: ArtStyle, W: number, H: number): void {
    const bg = style.background;

    if (bg.pattern !== 'none') {
        const u = Math.min(W, H) / 100;
        ctx.save();
        ctx.globalAlpha = bg.pattern_opacity;
        ctx.strokeStyle = style.palette.text;
        ctx.fillStyle = style.palette.text;
        ctx.lineWidth = 0.25 * u;

        if (bg.pattern === 'stripes') {
            for (let offset = -H; offset < W; offset += 4 * u) {
                ctx.beginPath();
                ctx.moveTo(offset, H);
                ctx.lineTo(offset + H, 0);
                ctx.stroke();
            }
        } else if (bg.pattern === 'grid') {
            for (let x = 0; x < W; x += 5 * u) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, H);
                ctx.stroke();
            }
            for (let y = 0; y < H; y += 5 * u) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(W, y);
                ctx.stroke();
            }
        } else {
            for (let x = 2.5 * u; x < W; x += 5 * u) {
                for (let y = 2.5 * u; y < H; y += 5 * u) {
                    ctx.beginPath();
                    ctx.arc(x, y, 0.35 * u, 0, Math.PI * 2);
                    ctx.fill();
                }
            }
        }

        ctx.restore();
    }

    if (bg.shade > 0) {
        const shade = ctx.createLinearGradient(0, H * 0.4, 0, H);
        shade.addColorStop(0, 'rgba(0,0,0,0)');
        shade.addColorStop(1, `rgba(0,0,0,${bg.shade})`);
        ctx.fillStyle = shade;
        ctx.fillRect(0, 0, W, H);
    }
}

/** Foto de fundo: `cover` preenche cortando o excesso; `contain` mostra inteira. */
function paintBgImage(ctx: CanvasRenderingContext2D, image: CanvasImageSource & { width: number; height: number }, fit: 'cover' | 'contain', opacity: number, W: number, H: number): void {
    const k = fit === 'cover' ? Math.max(W / image.width, H / image.height) : Math.min(W / image.width, H / image.height);
    const w = image.width * k;
    const h = image.height * k;

    ctx.save();
    ctx.globalAlpha = opacity;
    ctx.drawImage(image, (W - w) / 2, (H - h) / 2, w, h);
    ctx.restore();
}

/** Desenha a arte inteira no canvas (já com o tamanho final em pixels). */
export function drawArt(canvas: HTMLCanvasElement, ops: Op[], style: ArtStyle, images: Images): void {
    const ctx = canvas.getContext('2d');

    if (!ctx) return;

    const W = canvas.width;
    const H = canvas.height;
    ctx.clearRect(0, 0, W, H);

    for (const op of ops) {
        switch (op.t) {
            case 'background':
                paintBackground(ctx, style, W, H);
                break;
            case 'bgimage': {
                const image = images.get(op.src);
                if (image) paintBgImage(ctx, image, op.fit, op.opacity, W, H);
                break;
            }
            case 'texture':
                paintTexture(ctx, style, W, H);
                break;
            case 'rect':
                ctx.save();
                ctx.globalAlpha = op.alpha ?? 1;
                ctx.fillStyle = op.fill;
                if (op.radius) {
                    rounded(ctx, op.x, op.y, op.w, op.h, op.radius);
                    ctx.fill();
                } else {
                    ctx.fillRect(op.x, op.y, op.w, op.h);
                }
                ctx.restore();
                break;
            case 'frame':
                ctx.save();
                ctx.strokeStyle = op.color;
                ctx.lineWidth = op.width;
                rounded(ctx, op.x, op.y, op.w, op.h, op.radius);
                ctx.stroke();
                ctx.restore();
                break;
            case 'shape':
                ctx.save();
                ctx.globalAlpha = op.alpha ?? 1;
                ctx.fillStyle = op.fill;
                if (op.shape === 'circle') {
                    ctx.beginPath();
                    ctx.arc(op.x + op.w / 2, op.y + op.h / 2, op.w / 2, 0, Math.PI * 2);
                    ctx.fill();
                } else {
                    rounded(ctx, op.x, op.y, op.w, op.h, op.shape === 'badge' ? op.w * 0.12 : op.w * 0.22);
                    ctx.fill();
                }
                ctx.restore();
                break;
            case 'poly':
                ctx.save();
                ctx.fillStyle = op.fill;
                ctx.beginPath();
                ctx.moveTo(op.points[0], op.points[1]);
                for (let i = 2; i < op.points.length; i += 2) ctx.lineTo(op.points[i], op.points[i + 1]);
                ctx.closePath();
                ctx.fill();
                ctx.restore();
                break;
            case 'image': {
                const image = images.get(op.src);
                if (!image) break;
                // "contain": mantém a proporção do escudo dentro da caixa.
                const scale = Math.min(op.w / image.naturalWidth, op.h / image.naturalHeight);
                const w = image.naturalWidth * scale;
                const h = image.naturalHeight * scale;
                ctx.drawImage(image, op.x + (op.w - w) / 2, op.y + (op.h - h) / 2, w, h);
                break;
            }
            case 'text': {
                const font = op.font === 'heading' ? style.type.heading : style.type.body;
                const weight = op.weight ?? (op.font === 'heading' ? style.type.weight : 'bold');
                const upper = op.upper ?? (op.font === 'heading' && style.type.uppercase);
                const content = upper ? op.text.toUpperCase() : op.text;
                const spacing = op.font === 'heading' ? style.type.spacing : 0;
                const draw = (size: number) => {
                    ctx.font = `${WEIGHTS[weight]} ${size}px ${ART_FONTS[font]}`;
                    (ctx as CanvasRenderingContext2D & { letterSpacing?: string }).letterSpacing = `${spacing * size}px`;
                };

                let size = op.size;
                draw(size);
                const width = ctx.measureText(content).width;
                if (width > op.maxW && width > 0) {
                    size = (size * op.maxW) / width;
                    draw(size);
                }
                ctx.save();
                ctx.globalAlpha = op.alpha ?? 1;
                ctx.fillStyle = op.color;
                ctx.textAlign = op.align;
                ctx.textBaseline = 'middle';
                ctx.fillText(content, op.x, op.y);
                ctx.restore();
                break;
            }
        }
    }
}
