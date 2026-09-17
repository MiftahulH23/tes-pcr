import { ENROLLMENT_COLUMNS } from '@/components/enrollments/columns';
import { SortableHeader } from '@/components/enrollments/sortable-header';
import { StatusBadge } from '@/components/enrollments/status-badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { Enrollment, FilterableColumn, SortRule } from '@/types/enrollment';
import { Pencil, Trash2 } from 'lucide-react';

interface EnrollmentTableProps {
    rows: Enrollment[];
    loading: boolean;
    sorts: SortRule[];
    onToggleSort: (field: FilterableColumn, multi: boolean) => void;
    onEdit: (enrollment: Enrollment) => void;
    onDelete: (enrollment: Enrollment) => void;
}

export function EnrollmentTable({ rows, loading, sorts, onToggleSort, onEdit, onDelete }: EnrollmentTableProps) {
    return (
        <div className="space-y-1">
            <p className="text-muted-foreground text-xs sm:hidden">Geser tabel ke kanan untuk melihat kolom lainnya →</p>
            <div className="overflow-x-auto rounded-lg border">
                {/* w-full on the base Table shrinks/wraps cells to fit instead of overflowing on
                narrow screens; min-w plus nowrap cells force natural width so this wrapper's
                overflow-x-auto actually kicks in and scrolls instead of squashing columns. */}
                <Table className="min-w-[900px]">
                    <TableHeader>
                        <TableRow>
                            {ENROLLMENT_COLUMNS.map((column) => (
                                <SortableHeader key={column.key} field={column.key} label={column.label} sorts={sorts} onToggle={onToggleSort} />
                            ))}
                            <TableCell className="text-muted-foreground text-xs font-medium whitespace-nowrap uppercase">Aksi</TableCell>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {loading && rows.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={ENROLLMENT_COLUMNS.length + 1} className="text-muted-foreground h-24 text-center">
                                    Memuat data...
                                </TableCell>
                            </TableRow>
                        )}

                        {!loading && rows.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={ENROLLMENT_COLUMNS.length + 1} className="text-muted-foreground h-24 text-center">
                                    Tidak ada data KRS yang cocok.
                                </TableCell>
                            </TableRow>
                        )}

                        {rows.map((row) => (
                            <TableRow key={row.id}>
                                <TableCell className="whitespace-nowrap">{row.student_nim}</TableCell>
                                <TableCell className="whitespace-nowrap">{row.student_name}</TableCell>
                                <TableCell className="whitespace-nowrap">{row.course_code}</TableCell>
                                <TableCell className="whitespace-nowrap">{row.course_name}</TableCell>
                                <TableCell className="whitespace-nowrap">{row.semester}</TableCell>
                                <TableCell className="whitespace-nowrap">{row.academic_year}</TableCell>
                                <TableCell className="whitespace-nowrap">
                                    <StatusBadge status={row.status} />
                                </TableCell>
                                <TableCell className="whitespace-nowrap">
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" aria-label="Ubah" onClick={() => onEdit(row)}>
                                            <Pencil className="size-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" aria-label="Hapus" onClick={() => onDelete(row)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
