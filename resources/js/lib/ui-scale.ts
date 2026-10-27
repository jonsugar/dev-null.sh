export const uiScales = [
    { label: 'XSmall', value: 90 },
    { label: 'Small', value: 95 },
    { label: 'Default', value: 100 },
    { label: 'Large', value: 105 },
    { label: 'XLarge', value: 110 },
    { label: 'XXLarge', value: 117.5 },
    { label: 'XXXLarge', value: 125 },
] as const;

export type UiScale = (typeof uiScales)[number]['value'];

/** Screen measurements include zoom; CSS dimensions and element scroll offsets do not. */
export function layoutPixels(pixels: number): number {
    const scale =
        typeof document === 'undefined'
            ? 1
            : Number(
                  document.documentElement.style.getPropertyValue('--ui-scale'),
              ) || 1;

    return pixels / scale;
}

export function layoutRect(element: Element): DOMRect {
    const rect = element.getBoundingClientRect();
    const factor = layoutPixels(1);

    if (factor === 1) {
        return rect;
    }

    return new DOMRect(
        rect.x * factor,
        rect.y * factor,
        rect.width * factor,
        rect.height * factor,
    );
}

export function applyUiScale(scale: UiScale): void {
    document.documentElement.style.setProperty(
        '--ui-scale',
        String(scale / 100),
    );
    // Zoom can move elements without changing their observed CSS dimensions.
    window.dispatchEvent(new Event('resize'));
}
