import { AdvancedFilterDialog } from '@/components/enrollments/advanced-filter-dialog';
import { ENROLLMENT_COLUMNS } from '@/components/enrollments/columns';
import { EnrollmentFormDialog } from '@/components/enrollments/enrollment-form-dialog';
import { EnrollmentPagination } from '@/components/enrollments/enrollment-pagination';
import { EnrollmentTable } from '@/components/enrollments/enrollment-table';
import { EnrollmentToolbar } from '@/components/enrollments/enrollment-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useEnrollmentTable } from '@/hooks/use-enrollment-table';
import { apiFetch } from '@/lib/api';
import type { Enrollment, EnrollmentStatus, Semester } from '@/types/enrollment';
import { Head } from '@inertiajs/react';
import { useState } from 'react';

interface EnrollmentsPageProps {
    statuses: EnrollmentStatus[];
    semesters: Semester[];
}

export default function EnrollmentsIndex({ statuses, semesters }: EnrollmentsPageProps) {
    const table = useEnrollmentTable();

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Enrollment | null>(null);
    const [advancedFilterOpen, setAdvancedFilterOpen] = useState(false);
    const [pendingDelete, setPendingDelete] = useState<Enrollment | null>(null);
    const [deleting, setDeleting] = useState(false);

    const openCreate = () => {
        setEditing(null);
        setFormOpen(true);
    };

    const openEdit = (enrollment: Enrollment) => {
        setEditing(enrollment);
        setFormOpen(true);
    };

    const confirmDelete = async () => {
        if (!pendingDelete) {
            return;
        }

        setDeleting(true);

        try {
            await apiFetch(route('enrollments.destroy', pendingDelete.id), { method: 'DELETE' });
            table.refetch();
        } finally {
            setDeleting(false);
            setPendingDelete(null);
        }
    };

    return (
        <>
            <Head title="KRS Mahasiswa" />

            <div className="mx-auto flex max-w-7xl flex-col gap-4 p-4 sm:p-6">
                <div>
                    <h1 className="text-xl font-semibold">Kartu Rencana Studi (KRS)</h1>
                    <p className="text-muted-foreground text-sm">Kelola pengambilan mata kuliah mahasiswa per tahun ajaran.</p>
                </div>

                <EnrollmentToolbar
                    search={table.search}
                    onSearchChange={table.setSearch}
                    statuses={statuses}
                    quickStatus={table.quickStatus}
                    onQuickStatusChange={table.setQuickStatus}
                    semesters={semesters}
                    quickSemester={table.quickSemester}
                    onQuickSemesterChange={table.setQuickSemester}
                    onOpenAdvancedFilter={() => setAdvancedFilterOpen(true)}
                    onOpenCreate={openCreate}
                    exportHref={`${route('enrollments.export')}?${table.exportQueryString}`}
                />

                {table.error && <p className="text-destructive text-sm">{table.error}</p>}

                <div className="text-muted-foreground flex flex-wrap items-center gap-2 text-xs">
                    <span>Klik header kolom untuk sort. Shift+klik header lain untuk menambah sort multi-kolom.</span>
                    {table.sorts.length > 0 && (
                        <>
                            {table.sorts.map((sort, index) => (
                                <Badge key={sort.field} variant="secondary">
                                    {index + 1}. {ENROLLMENT_COLUMNS.find((c) => c.key === sort.field)?.label} ({sort.dir})
                                </Badge>
                            ))}
                            <button type="button" className="underline" onClick={() => table.setSorts([])}>
                                Bersihkan sort
                            </button>
                        </>
                    )}
                </div>

                <EnrollmentTable
                    rows={table.rows}
                    loading={table.loading}
                    sorts={table.sorts}
                    onToggleSort={table.toggleSort}
                    onEdit={openEdit}
                    onDelete={setPendingDelete}
                />

                <EnrollmentPagination meta={table.meta} onPageChange={table.setPage} onPageSizeChange={table.setPageSize} />
            </div>

            <EnrollmentFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                enrollment={editing}
                statuses={statuses}
                semesters={semesters}
                onSaved={table.refetch}
            />

            <AdvancedFilterDialog
                open={advancedFilterOpen}
                onOpenChange={setAdvancedFilterOpen}
                logic={table.advancedLogic}
                onLogicChange={table.setAdvancedLogic}
                conditions={table.advancedConditions}
                onConditionsChange={table.setAdvancedConditions}
            />

            <Dialog open={pendingDelete !== null} onOpenChange={(open) => !open && setPendingDelete(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus KRS?</DialogTitle>
                    </DialogHeader>
                    <p className="text-muted-foreground text-sm">
                        KRS {pendingDelete?.student_nim} — {pendingDelete?.course_code} akan dihapus (soft delete). Data dapat dipulihkan dari
                        database bila diperlukan.
                    </p>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setPendingDelete(null)}>
                            Batal
                        </Button>
                        <Button variant="destructive" disabled={deleting} onClick={() => void confirmDelete()}>
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
