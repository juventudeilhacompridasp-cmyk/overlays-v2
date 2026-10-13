import { CSSProperties } from 'react';
import { OverlayAppearance } from './overlay';

export function overlayPosition(position: OverlayAppearance['position']): string {
    return {
        'top-left': 'items-start justify-start',
        'top-center': 'items-start justify-center',
        'top-right': 'items-start justify-end',
    }[position];
}

export function overlayMotion(motion: OverlayAppearance['motion']): string {
    return motion === 'dynamic' ? 'overlay-motion-dynamic' : motion === 'subtle' ? 'overlay-motion-subtle' : '';
}

export function overlayStyle(appearance: OverlayAppearance): CSSProperties {
    return {
        '--overlay-accent': appearance.accent_color,
        '--overlay-background': appearance.background_color,
        '--overlay-text': appearance.text_color,
    } as CSSProperties;
}

