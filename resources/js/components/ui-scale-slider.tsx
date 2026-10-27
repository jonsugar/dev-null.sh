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
        <div ref={control} className="min-w-0 max-w-sm flex-1 scroll-mt-36">
            <div className="flex items-center gap-2">
                <span className="text-xs font-semibold">aA</span>
                <Slider
                    thumbProps={{
                        'aria-label': 'Interface size',
                        'aria-describedby': description,
                        'aria-valuetext': `${selected.label}, ${selected.value}%`,
                    }}
                    min={0}
                    max={uiScales.length - 1}
                    step={1}
                    value={[position]}
                    onValueChange={([value]) => {
                        setPosition(value);
                        updateUiScale(uiScales[value].value);
                    }}
                    onValueCommit={() => {
                        control.current?.scrollIntoView({ block: 'nearest' });
                    }}
                />
                <span className="text-l font-semibold">aA</span>
            </div>
        </div>
    );
}
