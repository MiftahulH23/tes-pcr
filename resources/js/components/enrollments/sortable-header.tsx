import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { FilterableColumn, SortRule } from '@/types/enrollment';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useRef } from 'react';

interface SortableHeaderProps {
    field: FilterableColumn;
    label: string;
    sorts: SortRule[];
    onToggle: (field: FilterableColumn, multi: boolean) => void;
}

const LONG_PRESS_MS = 500;

/** Click to sort by this column; shift-click (or press-and-hold on touch) to add it as a secondary sort key. */
export function SortableHeader({ field, label, sorts, onToggle }: SortableHeaderProps) {
    const position = sorts.findIndex((s) => s.field === field);
    const active = position >= 0 ? sorts[position] : null;

    const longPressTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const longPressFired = useRef(false);

    const clearLongPress = () => {
        if (longPressTimer.current) {
            clearTimeout(longPressTimer.current);
            longPressTimer.current = null;
        }
    };

    return (
        <TableHead
            role="button"
            tabIndex={0}
            className={cn('cursor-pointer touch-manipulation whitespace-nowrap outline-none select-none', active && 'text-foreground font-semibold')}
            onClick={(e) => {
                if (longPressFired.current) {
                    longPressFired.current = false;
                    return;
                }
                onToggle(field, e.shiftKey);
            }}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onToggle(field, e.shiftKey);
                }
            }}
            onTouchStart={() => {
                longPressFired.current = false;
                clearLongPress();
                longPressTimer.current = setTimeout(() => {
                    longPressFired.current = true;
                    onToggle(field, true);
                }, LONG_PRESS_MS);
            }}
            onTouchMove={clearLongPress}
            onTouchEnd={clearLongPress}
        >
            <span className="inline-flex items-center gap-1">
                {label}
                {active?.dir === 'asc' && <ArrowUp className="size-3.5" />}
                {active?.dir === 'desc' && <ArrowDown className="size-3.5" />}
                {!active && <ArrowUpDown className="size-3.5 opacity-30" />}
                {sorts.length > 1 && position >= 0 && <sup className="text-muted-foreground text-[10px]">{position + 1}</sup>}
            </span>
        </TableHead>
    );
}
