import assert from 'node:assert/strict';
import * as React from 'react';
import type { ComponentProps, ReactElement } from 'react';
import { afterEach, test, vi } from 'vite-plus/test';
import type { Slider } from '../../resources/js/components/ui/slider';

let position: number | undefined;
let pageScale = 100;
const scrollIntoView = vi.fn();

vi.doMock('@inertiajs/react', () => ({
    usePage: () => ({ props: { uiScale: pageScale } }),
}));
vi.doMock('react', () => ({
    ...React,
    useId: () => 'size-description',
    useRef: () => ({ current: { scrollIntoView } }),
    useState: (initial: () => number) => {
        position ??= initial();
        return [
            position,
            (next: number) => {
                position = next;
            },
        ];
    },
    useSyncExternalStore: (_subscribe: unknown, read: () => unknown) => read(),
}));

const { default: UiScaleSlider } =
    await import('../../resources/js/components/ui-scale-slider');
const { useUiScale } = await import('../../resources/js/hooks/use-ui-scale');

afterEach(() => vi.unstubAllGlobals());

test('previews while dragging, saves on commit, and keeps the choice over stale page props', () => {
    const properties = new Map<string, string>();
    const document = {
        cookie: '',
        documentElement: {
            style: {
                setProperty: (name: string, value: string) =>
                    properties.set(name, value),
            },
        },
    };
    const resize = vi.fn();
    vi.stubGlobal('document', document);
    vi.stubGlobal('window', { dispatchEvent: resize });
    const control = () => {
        const root = UiScaleSlider() as ReactElement<{
            children: React.ReactNode[];
        }>;
        return root.props.children[1] as ReactElement<
            ComponentProps<typeof Slider>
        >;
    };

    assert.deepEqual(control().props.value, [1]);
    control().props.onValueChange?.([3]);
    assert.equal(
        control().props.thumbProps?.['aria-valuetext'],
        'Largest, 125%',
    );
    assert.equal(document.cookie, '');
    assert.equal(resize.mock.calls.length, 0);

    control().props.onValueCommit?.([3]);
    assert.match(
        document.cookie,
        /^ui_scale=125;path=\/;max-age=31536000;SameSite=Lax$/,
    );
    assert.equal(properties.get('--ui-scale'), '1.25');
    assert.equal(scrollIntoView.mock.calls.length, 1);
    assert.equal(resize.mock.calls.length, 1);

    pageScale = 90;
    assert.equal(useUiScale().uiScale, 125);
    position = undefined;
    assert.deepEqual(control().props.value, [3]);

    for (const [value, scale] of [
        [0, 90],
        [1, 100],
        [2, 110],
    ] as const) {
        control().props.onValueChange?.([value]);
        control().props.onValueCommit?.([value]);
        assert.equal(useUiScale().uiScale, scale);
        assert.equal(properties.get('--ui-scale'), String(scale / 100));
    }
});
