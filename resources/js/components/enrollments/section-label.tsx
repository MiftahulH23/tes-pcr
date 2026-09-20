import { Separator } from '@/components/ui/separator';
import type { ReactNode } from 'react';

export function SectionLabel({ children }: { children: ReactNode }) {
    return (
        <div className="flex items-center gap-2">
            <span className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{children}</span>
            <Separator className="flex-1" />
        </div>
    );
}
