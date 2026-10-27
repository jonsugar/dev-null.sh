import { layoutRect } from './ui-scale.ts';

export function measureCommitSize(card: HTMLElement | null): void {
    if (card === null || getComputedStyle(card).contentVisibility !== 'auto') {
        return;
    }

    // New offscreen cards need their real height before Inertia restores the scroll position.
    const visibility = card.style.contentVisibility;
    card.style.contentVisibility = 'visible';
    card.style.containIntrinsicBlockSize = `auto ${layoutRect(card).height}px`;
    card.style.contentVisibility = visibility;
}
