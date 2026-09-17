import { Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ENROLLMENT_COLUMNS, OPERATORS_BY_COLUMN, valuePlaceholder } from '@/components/enrollments/columns';
import { cn } from '@/lib/utils';
import type { FilterCondition, FilterLogic, FilterableColumn } from '@/types/enrollment';

interface AdvancedFilterDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    logic: FilterLogic;
    onLogicChange: (logic: FilterLogic) => void;
    conditions: FilterCondition[];
    onConditionsChange: (conditions: FilterCondition[]) => void;
}

function emptyCondition(): FilterCondition {
    const field: FilterableColumn = 'student_nim';

    return { id: crypto.randomUUID(), field, op: OPERATORS_BY_COLUMN[field][0].value, value: '' };
}

export function AdvancedFilterDialog({ open, onOpenChange, logic, onLogicChange, conditions, onConditionsChange }: AdvancedFilterDialogProps) {
    const updateCondition = (id: string, patch: Partial<FilterCondition>) => {
        onConditionsChange(conditions.map((c) => (c.id === id ? { ...c, ...patch } : c)));
    };

    const removeCondition = (id: string) => {
        onConditionsChange(conditions.filter((c) => c.id !== id));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Advanced Filter</DialogTitle>
                </DialogHeader>

                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground text-sm">Gabungkan kondisi dengan:</span>
                    {(['and', 'or'] as const).map((value) => (
                        <Button
                            key={value}
                            type="button"
                            size="sm"
                            variant={logic === value ? 'default' : 'outline'}
                            className={cn('uppercase')}
                            onClick={() => onLogicChange(value)}
                        >
                            {value}
                        </Button>
                    ))}
                </div>

                <div className="flex max-h-80 flex-col gap-2 overflow-y-auto">
                    {conditions.length === 0 && <p className="text-muted-foreground py-4 text-center text-sm">Belum ada kondisi filter.</p>}

                    {conditions.map((condition) => (
                        <div key={condition.id} className="flex items-center gap-2">
                            <Select
                                value={condition.field}
                                onValueChange={(field: FilterableColumn) =>
                                    updateCondition(condition.id, { field, op: OPERATORS_BY_COLUMN[field][0].value })
                                }
                            >
                                <SelectTrigger className="w-40 shrink-0">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ENROLLMENT_COLUMNS.map((column) => (
                                        <SelectItem key={column.key} value={column.key}>
                                            {column.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={condition.op} onValueChange={(op) => updateCondition(condition.id, { op: op as FilterCondition['op'] })}>
                                <SelectTrigger className="w-40 shrink-0">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {OPERATORS_BY_COLUMN[condition.field].map((operator) => (
                                        <SelectItem key={operator.value} value={operator.value}>
                                            {operator.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Input
                                value={condition.value}
                                onChange={(e) => updateCondition(condition.id, { value: e.target.value })}
                                placeholder={valuePlaceholder(condition.op)}
                                className="min-w-0 flex-1"
                            />

                            <Button type="button" variant="ghost" size="icon" onClick={() => removeCondition(condition.id)}>
                                <X className="size-4" />
                            </Button>
                        </div>
                    ))}
                </div>

                <Button type="button" variant="outline" size="sm" onClick={() => onConditionsChange([...conditions, emptyCondition()])}>
                    <Plus className="size-4" />
                    Tambah kondisi
                </Button>

                <DialogFooter>
                    <Button type="button" variant="ghost" onClick={() => onConditionsChange([])}>
                        Bersihkan semua
                    </Button>
                    <Button type="button" onClick={() => onOpenChange(false)}>
                        Terapkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
