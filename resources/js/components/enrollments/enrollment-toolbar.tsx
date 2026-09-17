import { Download, Info, ListFilter, Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { EnrollmentStatus, Semester } from '@/types/enrollment';

interface EnrollmentToolbarProps {
    search: string;
    onSearchChange: (value: string) => void;
    statuses: EnrollmentStatus[];
    quickStatus: EnrollmentStatus[];
    onQuickStatusChange: (statuses: EnrollmentStatus[]) => void;
    semesters: Semester[];
    quickSemester: Semester[];
    onQuickSemesterChange: (semesters: Semester[]) => void;
    onOpenAdvancedFilter: () => void;
    onOpenCreate: () => void;
    exportHref: string;
    exportRowCount: number;
    exportFiltered: boolean;
}

function toggleValue<T>(list: T[], value: T): T[] {
    return list.includes(value) ? list.filter((v) => v !== value) : [...list, value];
}

export function EnrollmentToolbar({
    search,
    onSearchChange,
    statuses,
    quickStatus,
    onQuickStatusChange,
    semesters,
    quickSemester,
    onQuickSemesterChange,
    onOpenAdvancedFilter,
    onOpenCreate,
    exportHref,
    exportRowCount,
    exportFiltered,
}: EnrollmentToolbarProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:w-72">
                    <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                    <Input
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Cari NIM, nama, atau kode MK..."
                        className="pl-8"
                    />
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            Status {quickStatus.length > 0 && `(${quickStatus.length})`}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {statuses.map((status) => (
                            <DropdownMenuCheckboxItem
                                key={status}
                                checked={quickStatus.includes(status)}
                                onCheckedChange={() => onQuickStatusChange(toggleValue(quickStatus, status))}
                                onSelect={(e) => e.preventDefault()}
                            >
                                {status}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            Semester {quickSemester.length > 0 && `(${quickSemester.length})`}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter Semester</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {semesters.map((semester) => (
                            <DropdownMenuCheckboxItem
                                key={semester}
                                checked={quickSemester.includes(semester)}
                                onCheckedChange={() => onQuickSemesterChange(toggleValue(quickSemester, semester))}
                                onSelect={(e) => e.preventDefault()}
                            >
                                {semester}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button variant="outline" size="sm" onClick={onOpenAdvancedFilter}>
                    <ListFilter className="size-4" />
                    Advanced Filter
                </Button>
            </div>

            <div className="flex items-center gap-2">
                <TooltipProvider delayDuration={200}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="outline" size="sm" asChild>
                                <a href={exportHref}>
                                    <Download className="size-4" />
                                    Export CSV
                                    <Info className="text-muted-foreground size-3.5" />
                                </a>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent className="max-w-64 text-center">
                            {exportFiltered ? (
                                <p>
                                    Export mengikuti pencarian/filter yang aktif — akan menghasilkan{' '}
                                    <span className="font-medium">{exportRowCount.toLocaleString('id-ID')} baris</span>. Kosongkan semua
                                    filter untuk export seluruh data.
                                </p>
                            ) : (
                                <p>
                                    Tidak ada filter aktif — export akan menghasilkan seluruh{' '}
                                    <span className="font-medium">{exportRowCount.toLocaleString('id-ID')} baris</span>.
                                </p>
                            )}
                            <p className="text-muted-foreground mt-1">Untuk data besar, proses ini bisa memakan beberapa waktu.</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <Button size="sm" onClick={onOpenCreate}>
                    <Plus className="size-4" />
                    Tambah KRS
                </Button>
            </div>
        </div>
    );
}
