import { useId, useRef, useState } from 'react';
import { Slider } from '@/components/ui/slider';
import { useUiScale } from '@/hooks/use-ui-scale';
import { uiScales } from '@/lib/ui-scale';

export default function UiScaleSlider() {
    const { uiScale, updateUiScale } = useUiScale();
    const [position, setPosition] = useState(() =>
        uiScales.findIndex((scale) => scale.value === uiScale),
    );
    const control = useRef<HTMLDivElement>(null);
    const description = useId();
    const selected = uiScales[position];

    return (
        <div ref={control} className="max-w-sm scroll-mt-36">
            <p
                id={description}
                className="mb-2 h-5 text-right font-mono text-xs tabular-nums"
            >
                {selected.label} · {selected.value}%
            </p>
            <Slider
                thumbProps={{
                    'aria-label': 'Interface size',
                    'aria-describedby': description,
                    'aria-valuetext': `${selected.label}, ${selected.value}%`,
                }}
                min={0}
                max={3}
                step={1}
                value={[position]}
                onValueChange={([value]) => setPosition(value)}
                onValueCommit={([value]) => {
                    updateUiScale(uiScales[value].value);
                    control.current?.scrollIntoView({ block: 'nearest' });
                }}
                className="h-11"
            />
            <div
                aria-hidden
                className="relative mx-2 h-7 text-xs text-muted-foreground"
            >
                {uiScales.map((scale, index) => (
                    <span
                        key={scale.value}
                        className="absolute top-0 flex -translate-x-1/2 flex-col items-center gap-1 first:translate-x-0 first:items-start last:-translate-x-full last:items-end"
                        style={{ left: `${(index / 3) * 100}%` }}
                    >
                        <span className="h-1 w-px bg-input" />
                        {scale.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
