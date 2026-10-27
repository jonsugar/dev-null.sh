import { usePage } from '@inertiajs/react';
import { setCookie } from '@/hooks/use-appearance';
import { useSharedState } from '@/hooks/use-shared-state';
import { applyUiScale } from '@/lib/ui-scale';
import type { UiScale } from '@/lib/ui-scale';

export function useUiScale() {
    const { uiScale: initialScale } = usePage().props;
    const [uiScale, setUiScale] = useSharedState<UiScale>(
        'ui-scale',
        initialScale,
    );

    const updateUiScale = (scale: UiScale): void => {
        setCookie('ui_scale', String(scale));
        applyUiScale(scale);
        setUiScale(scale);
    };

    return { uiScale, updateUiScale };
}
