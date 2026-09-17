import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import { ENROLLMENT_COLUMNS } from '@/components/enrollments/columns';
import { SortableHeader } from '@/components/enrollments/sortable-header';
import { StatusBadge } from '@/components/enrollments/status-badge';
import type { Enrollment, FilterableColumn, SortRule } from '@/types/enrollment';

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
        <div className="overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        {ENROLLMENT_COLUMNS.map((column) => (
                            <SortableHeader key={column.key} field={column.key} label={column.label} sorts={sorts} onToggle={onToggleSort} />
                        ))}
                        <TableCell className="text-muted-foreground text-xs font-medium uppercase">Aksi</TableCell>
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
                            <TableCell>{row.student_nim}</TableCell>
                            <TableCell>{row.student_name}</TableCell>
                            <TableCell>{row.course_code}</TableCell>
                            <TableCell>{row.course_name}</TableCell>
                            <TableCell>{row.semester}</TableCell>
                            <TableCell>{row.academic_year}</TableCell>
                            <TableCell>
                                <StatusBadge status={row.status} />
                            </TableCell>
                            <TableCell>
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
    );
}
