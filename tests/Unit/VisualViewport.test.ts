import assert from 'node:assert/strict';
import { test } from 'node:test';
import { applyUiScale } from '../../resources/js/lib/ui-scale.ts';
import {
    isKeyboardOpen,
    trackVisualViewport,
} from '../../resources/js/lib/visual-viewport.ts';

await test('the keyboard opens for a focused field that loses room', () => {
    assert.equal(isKeyboardOpen(false, true, 300), true);
    assert.equal(isKeyboardOpen(false, true, 40), false);
});

await test('losing room without a focused field is not the keyboard', () => {
    assert.equal(isKeyboardOpen(false, false, 300), false);
});

await test('an open keyboard stays open until the room comes back', () => {
    assert.equal(isKeyboardOpen(true, false, 300), true);
    assert.equal(isKeyboardOpen(true, false, 40), false);
});

await test('scaling refreshes viewport lengths without treating magnification as a keyboard', () => {
    const names = [
        'document',
        'window',
        'HTMLElement',
        'HTMLInputElement',
        'HTMLTextAreaElement',
    ] as const;
    const originals = names.map((name) =>
        Object.getOwnPropertyDescriptor(globalThis, name),
    );
    const properties = new Map<string, string>();
    const attributes = new Set<string>();
    const viewport = {
        height: 800,
        offsetTop: 0,
        scale: 1,
        addEventListener: () => undefined,
    };
    const target = Object.assign(new EventTarget(), {
        visualViewport: viewport,
    });
    const events = new Map<string, () => void>();
    class Field {}
    const field = new Field();

    Object.assign(globalThis, {
        window: target,
        HTMLElement: Field,
        HTMLInputElement: Field,
        HTMLTextAreaElement: Field,
        document: {
            activeElement: field,
            addEventListener: (name: string, update: () => void) =>
                events.set(name, update),
            documentElement: {
                clientHeight: 800,
                style: {
                    getPropertyValue: (name: string) =>
                        properties.get(name) ?? '',
                    setProperty: (name: string, value: string) =>
                        properties.set(name, value),
                },
                hasAttribute: (name: string) => attributes.has(name),
                toggleAttribute: (name: string, enabled: boolean) =>
                    enabled ? attributes.add(name) : attributes.delete(name),
            },
        },
    });

    try {
        trackVisualViewport();
        applyUiScale(125);
        assert.equal(properties.get('--visual-viewport-height'), '640px');
        assert.equal(properties.get('--visual-viewport-bottom'), '0px');
        assert.equal(attributes.has('data-keyboard-open'), false);

        viewport.height = 500;
        viewport.offsetTop = 50;
        events.get('focusin')?.();
        assert.equal(properties.get('--visual-viewport-top'), '40px');
        assert.equal(properties.get('--visual-viewport-height'), '400px');
        assert.equal(properties.get('--visual-viewport-bottom'), '200px');
        assert.equal(attributes.has('data-keyboard-open'), true);
    } finally {
        names.forEach((name, index) => {
            const descriptor = originals[index];
            if (descriptor) Object.defineProperty(globalThis, name, descriptor);
            else Reflect.deleteProperty(globalThis, name);
        });
    }
});
