import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { FilterableColumn, SortRule } from '@/types/enrollment';

interface SortableHeaderProps {
    field: FilterableColumn;
    label: string;
    sorts: SortRule[];
    onToggle: (field: FilterableColumn, multi: boolean) => void;
}

/** Click to sort by this column; shift-click to add it as a secondary sort key. */
export function SortableHeader({ field, label, sorts, onToggle }: SortableHeaderProps) {
    const position = sorts.findIndex((s) => s.field === field);
    const active = position >= 0 ? sorts[position] : null;

    return (
        <TableHead
            role="button"
            tabIndex={0}
            className={cn('cursor-pointer select-none whitespace-nowrap', active && 'text-foreground font-semibold')}
            onClick={(e) => onToggle(field, e.shiftKey)}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onToggle(field, e.shiftKey);
                }
            }}
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
