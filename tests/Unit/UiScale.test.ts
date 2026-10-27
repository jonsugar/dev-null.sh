import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    applyUiScale,
    layoutPixels,
    layoutRect,
} from '../../resources/js/lib/ui-scale.ts';

await test('server measurements use the default scale without a document', () => {
    assert.equal(layoutPixels(250), 250);
});

await test('all sizes convert screen measurements back to CSS dimensions and refresh layout', () => {
    const names = ['document', 'window', 'DOMRect'] as const;
    const originals = names.map((name) =>
        Object.getOwnPropertyDescriptor(globalThis, name),
    );
    const properties = new Map<string, string>();
    const target = new EventTarget();
    const measured: number[] = [];
    target.addEventListener('resize', () => measured.push(layoutPixels(250)));

    Object.assign(globalThis, {
        document: {
            documentElement: {
                style: {
                    getPropertyValue: (name: string) =>
                        properties.get(name) ?? '',
                    setProperty: (name: string, value: string) =>
                        properties.set(name, value),
                },
            },
        },
        window: target,
        DOMRect: class {
            x: number;
            y: number;
            width: number;
            height: number;
            constructor(x: number, y: number, width: number, height: number) {
                this.x = x;
                this.y = y;
                this.width = width;
                this.height = height;
            }
        },
    });

    try {
        assert.equal(layoutPixels(250), 250);
        for (const [scale, expected] of [
            [90, 250 / 0.9],
            [100, 250],
            [110, 250 / 1.1],
            [125, 200],
        ] as const) {
            applyUiScale(scale);
            assert.equal(measured.at(-1), expected);
        }
        const element = {
            getBoundingClientRect: () => ({
                x: 125,
                y: -250,
                width: 500,
                height: 75,
            }),
        } as Element;
        const rect = layoutRect(element);
        assert.deepEqual(
            [rect.x, rect.y, rect.width, rect.height],
            [100, -200, 400, 60],
        );
        assert.equal(measured.length, 4);
    } finally {
        names.forEach((name, index) => {
            const descriptor = originals[index];
            if (descriptor) Object.defineProperty(globalThis, name, descriptor);
            else Reflect.deleteProperty(globalThis, name);
        });
    }
});
